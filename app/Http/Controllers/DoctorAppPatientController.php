<?php

namespace App\Http\Controllers;

use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\FluidBalancePlan;
use App\Models\Patient;
use App\Services\DoctorApp\DoctorAppPatientChart;
use App\Services\ShiftHandover;
use App\Support\DoctorAppAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * The doctor app's patient chart: I/O, medications and consultant orders
 * for one patient under the consultant's care, and writing or cancelling
 * the consultant's own orders. Every action answers with the refreshed
 * chart, so the app simply redraws from it.
 *
 *   GET  /api/doctor/patients/{patient}/chart?io_day=Y-m-d
 *   POST /api/doctor/patients/{patient}/orders               { instruction, urgency, fluid_limit_ml?, urine_min_ml_per_hour? }
 *   POST /api/doctor/patients/{patient}/orders/{order}/cancel { reason }
 *
 * An order sits with the nurse rostered to the bed for the shift on now,
 * exactly as one entered on the ward dashboard. A fluid restriction on it
 * becomes the patient's I/O fluid plan (App\Services\FluidBalanceLinks).
 */
class DoctorAppPatientController extends Controller
{
    public function show(Request $request, Patient $patient): JsonResponse
    {
        [$consultant, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        return $this->chart($patient, $consultant, $request->query('io_day'));
    }

    public function storeOrder(Request $request, Patient $patient): JsonResponse
    {
        [$consultant, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'instruction' => 'required|string|max:2000',
            'urgency' => ['required', Rule::in(array_keys(ConsultantOrder::URGENCIES))],
            'fluid_limit_ml' => 'nullable|integer|between:' . FluidBalancePlan::LIMIT_MIN . ',' . FluidBalancePlan::LIMIT_MAX,
            'urine_min_ml_per_hour' => 'nullable|integer|between:' . FluidBalancePlan::URINE_MIN . ',' . FluidBalancePlan::URINE_MAX,
        ], [
            'instruction.required' => 'Write the order.',
            'fluid_limit_ml.between' => 'The intake limit must be between ' . number_format(FluidBalancePlan::LIMIT_MIN)
                . ' and ' . number_format(FluidBalancePlan::LIMIT_MAX) . ' mL per day.',
            'urine_min_ml_per_hour.between' => 'The urine target must be between ' . FluidBalancePlan::URINE_MIN
                . ' and ' . FluidBalancePlan::URINE_MAX . ' mL/h.',
        ]);

        $slot = ShiftHandover::slotsFor($patient)['current'];
        $nurse = $slot['nurse'] ?? null;

        $order = ConsultantOrder::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'consultant_id' => $consultant->id,
            'consultant_name' => $consultant->name,
            'instruction' => trim($validated['instruction']),
            'fluid_limit_ml' => $validated['fluid_limit_ml'] ?? null,
            'urine_min_ml_per_hour' => $validated['urine_min_ml_per_hour'] ?? null,
            'urgency' => $validated['urgency'],
            'ordered_at' => now(),
            'assigned_nurse_id' => $nurse?->id,
            'shift_date' => $slot['date'] ?? null,
            'shift_code' => $slot['code'] ?? null,
            'status' => ConsultantOrder::STATUS_OPEN,
            // Written in the app: the consultant is on the order, there is no web user behind it
            'created_by' => null,
        ]);

        Log::info('Doctor app order written', [
            'consultant_order_id' => $order->id,
            'consultant_id' => $consultant->id,
            'patient_id' => $patient->id,
        ]);

        $message = $nurse
            ? 'Order sent to ' . $nurse->name . '.'
            : 'Order sent. No nurse is rostered to this bed for the shift on now, so it waits unassigned.';
        if ($order->fluid_limit_ml !== null || $order->urine_min_ml_per_hour !== null) {
            $message .= ' The fluid plan on the I/O chart now follows it.';
        }

        return $this->chart($patient, $consultant, null, $message, 201);
    }

    public function cancelOrder(Request $request, Patient $patient, ConsultantOrder $order): JsonResponse
    {
        [$consultant, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        if ((int) $order->patient_id !== (int) $patient->id) {
            return $this->refuse('That order is not on this patient.', 404);
        }
        if (!$order->isOpen()) {
            return $this->refuse('That order is already closed.');
        }
        if ((int) $order->consultant_id !== (int) $consultant->id) {
            return $this->refuse('Only the consultant who wrote an order can cancel it from the app.', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:900',
        ], [
            'reason.required' => 'Give a reason for cancelling the order.',
        ]);

        $order->update([
            'status' => ConsultantOrder::STATUS_CANCELLED,
            'closed_at' => now(),
            'closed_by' => null,
            'outcome_note' => 'Cancelled by ' . $consultant->name . ' in the doctor app: ' . trim($validated['reason']),
        ]);

        Log::info('Doctor app order cancelled', [
            'consultant_order_id' => $order->id,
            'consultant_id' => $consultant->id,
            'patient_id' => $patient->id,
        ]);

        return $this->chart($patient, $consultant, null, 'Order cancelled.');
    }

    // ------------------------------------------------------------------

    /**
     * The consultant behind the token, or the response refusing them.
     *
     * @return array{0: ?Consultant, 1: ?JsonResponse}
     */
    private function authorise(Request $request, Patient $patient): array
    {
        $consultant = DoctorAppAccess::consultant($request);
        if (!$consultant) {
            return [null, response()->json(['success' => false, 'message' => 'Unauthenticated. Please log in again.'], 401)];
        }

        if (!DoctorAppAccess::isUnderCare($consultant, $patient)) {
            return [null, response()->json(['success' => false, 'message' => 'This patient is not under your care.'], 403)];
        }

        return [$consultant, null];
    }

    private function chart(Patient $patient, Consultant $consultant, ?string $ioDay, ?string $message = null, int $status = 200): JsonResponse
    {
        // A fresh copy, so the chart shows what was just saved
        $patient = $patient->fresh() ?? $patient;

        return response()->json([
            'success' => true,
            'message' => $message,
            'chart' => DoctorAppPatientChart::build($patient, $consultant, $ioDay),
        ], $status);
    }

    private function refuse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
