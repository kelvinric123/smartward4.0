<?php

namespace App\Http\Controllers;

use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\ConsultantOrderHandover;
use App\Models\FluidBalancePlan;
use App\Models\Patient;
use App\Services\ShiftHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The Consultant Orders tab of the ward dashboard's patient details. An order
 * goes to the nurse the roster puts on the patient's bed for the shift on now,
 * and is passed shift to shift until it is done or cancelled.
 */
class ConsultantOrderController extends Controller
{
    /** How far either side of now an order's assigned time may be set. */
    private const ASSIGNED_WINDOW_DAYS = 7;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'instruction' => 'required|string|max:2000',
            // An optional fluid restriction, which becomes the I/O fluid plan (FluidBalanceLinks)
            'fluid_limit_ml' => 'nullable|integer|between:' . FluidBalancePlan::LIMIT_MIN . ',' . FluidBalancePlan::LIMIT_MAX,
            'urine_min_ml_per_hour' => 'nullable|integer|between:' . FluidBalancePlan::URINE_MIN . ',' . FluidBalancePlan::URINE_MAX,
            'urgency' => ['required', Rule::in(array_keys(ConsultantOrder::URGENCIES))],
            'consultant_id' => 'required|exists:consultants,id',
            'ordered_at' => [
                'nullable', 'date',
                'after_or_equal:' . now()->subDays(self::ASSIGNED_WINDOW_DAYS)->toDateTimeString(),
                'before_or_equal:' . now()->addDays(self::ASSIGNED_WINDOW_DAYS)->toDateTimeString(),
            ],
        ], [
            'ordered_at.after_or_equal' => 'The assigned time can be at most ' . self::ASSIGNED_WINDOW_DAYS . ' days ago.',
            'ordered_at.before_or_equal' => 'The assigned time can be at most ' . self::ASSIGNED_WINDOW_DAYS . ' days ahead.',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $consultant = Consultant::findOrFail($validated['consultant_id']);
        $slot = ShiftHandover::slotsFor($patient)['current'];

        ConsultantOrder::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'consultant_id' => $consultant->id,
            'consultant_name' => $consultant->name,
            'instruction' => trim($validated['instruction']),
            'fluid_limit_ml' => $validated['fluid_limit_ml'] ?? null,
            'urine_min_ml_per_hour' => $validated['urine_min_ml_per_hour'] ?? null,
            'urgency' => $validated['urgency'],
            'ordered_at' => $validated['ordered_at'] ?? now(),
            'assigned_nurse_id' => $slot['nurse']?->id,
            'shift_date' => $slot['date'] ?? null,
            'shift_code' => $slot['code'] ?? null,
            'status' => ConsultantOrder::STATUS_OPEN,
            'created_by' => Auth::id(),
        ]);

        $nurse = $slot['nurse'] ?? null;

        return $this->backToTab()->with('success', $nurse
            ? 'Order added for ' . $nurse->name . '.'
            : 'Order added. No nurse is rostered to this bed for the shift on now, so it is unassigned.');
    }

    public function complete(Request $request, ConsultantOrder $consultantOrder)
    {
        $validated = $request->validate([
            'outcome_note' => 'nullable|string|max:1000',
        ]);

        if (!$consultantOrder->isOpen()) {
            return $this->backToTab()->with('error', 'That order is already closed.');
        }

        $consultantOrder->update([
            'status' => ConsultantOrder::STATUS_DONE,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'outcome_note' => $validated['outcome_note'] ?? null,
        ]);

        return $this->backToTab()->with('success', 'Order marked done.');
    }

    public function cancel(Request $request, ConsultantOrder $consultantOrder)
    {
        $validated = $request->validate([
            'outcome_note' => 'required|string|max:1000',
        ], [
            'outcome_note.required' => 'Give a reason for cancelling the order.',
        ]);

        if (!$consultantOrder->isOpen()) {
            return $this->backToTab()->with('error', 'That order is already closed.');
        }

        $consultantOrder->update([
            'status' => ConsultantOrder::STATUS_CANCELLED,
            'closed_at' => now(),
            'closed_by' => Auth::id(),
            'outcome_note' => $validated['outcome_note'],
        ]);

        return $this->backToTab()->with('success', 'Order cancelled.');
    }

    /**
     * Pass open orders on to the next shift, or, for one left over from a
     * shift that has already ended, to the shift on now. Each goes to the
     * nurse the roster puts on the patient's bed for that shift, and each
     * pass is logged.
     */
    public function handover(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer',
            'to' => ['required', Rule::in(['next', 'current'])],
            'note' => 'nullable|string|max:1000',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $target = ShiftHandover::slotsFor($patient)[$validated['to']];

        if (!$target) {
            return $this->backToTab()->with('error', 'There is no shift to pass the orders to. Check the ward\'s shift settings.');
        }

        $orders = ConsultantOrder::where('patient_id', $patient->id)
            ->open()
            ->whereIn('id', $validated['order_ids'])
            ->get()
            ->reject(fn (ConsultantOrder $order) => $order->isInSlot($target));

        if ($orders->isEmpty()) {
            return $this->backToTab()->with('error', 'Those orders are already with the ' . $target['label'] . ' shift.');
        }

        $nurse = $target['nurse'];

        DB::transaction(function () use ($orders, $target, $nurse, $validated) {
            foreach ($orders as $order) {
                ConsultantOrderHandover::create([
                    'consultant_order_id' => $order->id,
                    'from_nurse_id' => $order->assigned_nurse_id,
                    'from_shift_date' => $order->shift_date,
                    'from_shift_code' => $order->shift_code,
                    'to_nurse_id' => $nurse?->id,
                    'to_shift_date' => $target['date'],
                    'to_shift_code' => $target['code'],
                    'note' => $validated['note'] ?? null,
                    'handed_over_by' => Auth::id(),
                ]);

                $order->update([
                    'assigned_nurse_id' => $nurse?->id,
                    'shift_date' => $target['date'],
                    'shift_code' => $target['code'],
                ]);
            }
        });

        $count = $orders->count() . ' ' . Str::plural('order', $orders->count());

        return $this->backToTab()->with('success', $nurse
            ? $count . ' passed to ' . $nurse->name . ' (' . $target['label'] . ').'
            : $count . ' passed to the ' . $target['label'] . ' shift. No nurse is rostered to this bed for it yet.');
    }

    /**
     * Back to the patient details, on this tab. Only the tab is flashed, so a
     * saved form does not come back filled in.
     */
    private function backToTab()
    {
        return back()->withInput(['active_tab' => 'orders']);
    }
}
