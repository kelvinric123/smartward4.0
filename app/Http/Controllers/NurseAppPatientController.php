<?php

namespace App\Http\Controllers;

use App\Models\BloodTransfusion;
use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\ConsultantOrderHandover;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\MedicationAdministration;
use App\Models\Nurse;
use App\Models\NursingCarePlanItem;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\WardNotification;
use App\Services\DischargeSummaryService;
use App\Services\NurseApp\NurseAppActor;
use App\Services\NurseApp\NurseAppPatientBundle;
use App\Services\NursingPlan\NursingCarePlan;
use App\Services\NursingPlan\NursingCarePlanRules;
use App\Services\ShiftHandover;
use App\Support\AdmissionTimeline;
use App\Support\FluidBalanceChart;
use App\Support\NursingCarePlanLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Nurse app: one patient, and what a nurse does for them at the bedside -
 * consultant orders, the I/O chart, medication doses, blood transfusions,
 * infusions and alerts. The mobile twin of the ward dashboard's Patient
 * Details tabs.
 *
 * Every action applies the same rules as the ward dashboard's own
 * controllers (ConsultantOrderController, FluidBalanceController,
 * MedicationMonitoringController) and answers with the patient's refreshed
 * bundle, so the app redraws from the server's view of the record.
 *
 * Times from the app are sent as "minutes ago" rather than clock times, so a
 * phone set to the wrong time zone can never misfile an entry.
 */
class NurseAppPatientController extends Controller
{
    /** How far back a dose, entry or assessment may be timed (as on the ward dashboard). */
    private const BACKDATE_MINUTES = 24 * 60;

    // ------------------------------------------------------------- reading

    /**
     * GET /api/nurse/patients/{patient}?io_day=Y-m-d
     */
    public function show(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        return response()->json([
            'success' => true,
            'patient' => NurseAppPatientBundle::build($patient, $nurse, $request->input('io_day')),
        ]);
    }

    /**
     * GET /api/nurse/patients/{patient}/timeline - every event of the current
     * admission, day by day, newest day first (the discharge summary's
     * clinical timeline).
     */
    public function timeline(Request $request, Patient $patient, DischargeSummaryService $summaries): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $episode = $summaries->admissionsFor($patient)->first()?->episode
            ?? $summaries->unloggedEpisodeFor($patient);

        if (!$episode) {
            return response()->json(['success' => true, 'timeline' => ['days' => [], 'total' => 0, 'categories' => []]]);
        }

        $timeline = $summaries->summaryForEpisode($episode)['timeline'];

        $days = collect($timeline['days'])->map(function (array $block) {
            if ($block['empty']) {
                return [
                    'empty' => true,
                    'label' => $block['from']->isSameDay($block['to'])
                        ? 'Nothing recorded on ' . $block['from']->format('D d M')
                        : 'Nothing recorded ' . $block['from']->format('d M') . ' - ' . $block['to']->format('d M'),
                ];
            }

            return [
                'empty' => false,
                'label' => ($block['number'] >= 1 ? 'Day ' . $block['number'] : 'Before admission'),
                'date_label' => $block['date']->format('D, d M Y'),
                'roster' => $block['roster'],
                'events' => $block['events']->reverse()->map(fn(array $event) => [
                    'time' => $event['at']->format('H:i'),
                    'category' => $event['category'],
                    'title' => $event['title'],
                    'detail' => $event['detail'],
                    'note' => $event['note'],
                    'by' => $event['by'],
                    'tone' => $event['tone'],
                    'badge' => $event['badge'],
                ])->values()->all(),
            ];
        })->reverse()->values()->all();

        return response()->json([
            'success' => true,
            'timeline' => [
                'reference' => $episode->reference(),
                'admitted_label' => $episode->admittedAt->format('d M Y, H:i'),
                'is_discharged' => $episode->isDischarged(),
                'stay_label' => $episode->lengthOfStay(),
                'total' => $timeline['total'],
                'categories' => collect(AdmissionTimeline::CATEGORIES)
                    ->map(fn(string $label, string $key) => ['key' => $key, 'label' => $label, 'count' => $timeline['categories'][$key]['count'] ?? 0])
                    ->filter(fn(array $category) => $category['count'] > 0)
                    ->values()
                    ->all(),
                'days' => $days,
            ],
        ]);
    }

    // ---------------------------------------------------- consultant orders

    /**
     * POST /api/nurse/patients/{patient}/orders - write down an order from a
     * consultant. It goes to the nurse rostered to the bed for the shift on now.
     */
    public function storeOrder(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'instruction' => 'required|string|max:2000',
            'urgency' => ['required', Rule::in(array_keys(ConsultantOrder::URGENCIES))],
            'consultant_id' => 'required|exists:consultants,id',
        ], [
            'instruction.required' => 'Write down what the consultant ordered.',
            'consultant_id.required' => 'Choose the consultant who gave the order.',
        ]);

        $consultant = Consultant::findOrFail($validated['consultant_id']);
        $slot = ShiftHandover::slotsFor($patient)['current'] ?? null;
        $assigned = $slot['nurse'] ?? null;

        $order = ConsultantOrder::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'consultant_id' => $consultant->id,
            'consultant_name' => $consultant->name,
            'instruction' => trim($validated['instruction']),
            'urgency' => $validated['urgency'],
            'ordered_at' => now(),
            'assigned_nurse_id' => $assigned?->id,
            'shift_date' => $slot['date'] ?? null,
            'shift_code' => $slot['code'] ?? null,
            'status' => ConsultantOrder::STATUS_OPEN,
            'created_by' => NurseAppActor::userId($nurse),
        ]);

        $this->log('Consultant order added', $nurse, $patient, ['consultant_order_id' => $order->id, 'urgency' => $order->urgency]);

        return $this->done($nurse, $patient, $assigned
            ? 'Order added for ' . $assigned->name . '.'
            : 'Order added. No nurse is rostered to this bed for the shift on now, so it is unassigned.');
    }

    /**
     * POST /api/nurse/patients/{patient}/orders/{order}/complete
     */
    public function completeOrder(Request $request, Patient $patient, ConsultantOrder $order): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $order->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate(['outcome_note' => 'nullable|string|max:1000']);

        if (!$order->isOpen()) {
            return $this->refuse('That order is already closed.');
        }

        $order->update([
            'status' => ConsultantOrder::STATUS_DONE,
            'closed_at' => now(),
            'closed_by' => NurseAppActor::userId($nurse),
            'outcome_note' => filled($validated['outcome_note'] ?? null) ? trim($validated['outcome_note']) : null,
        ]);

        $this->log('Consultant order done', $nurse, $patient, ['consultant_order_id' => $order->id]);

        return $this->done($nurse, $patient, 'Order marked done.');
    }

    /**
     * POST /api/nurse/patients/{patient}/orders/{order}/cancel - needs a reason.
     */
    public function cancelOrder(Request $request, Patient $patient, ConsultantOrder $order): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $order->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'outcome_note' => 'required|string|max:1000',
        ], [
            'outcome_note.required' => 'Give a reason for cancelling the order.',
        ]);

        if (!$order->isOpen()) {
            return $this->refuse('That order is already closed.');
        }

        $order->update([
            'status' => ConsultantOrder::STATUS_CANCELLED,
            'closed_at' => now(),
            'closed_by' => NurseAppActor::userId($nurse),
            'outcome_note' => trim($validated['outcome_note']),
        ]);

        $this->log('Consultant order cancelled', $nurse, $patient, ['consultant_order_id' => $order->id]);

        return $this->done($nurse, $patient, 'Order cancelled.');
    }

    /**
     * POST /api/nurse/patients/{patient}/orders/handover - pass open orders
     * (all of them, or order_ids) to the next shift or, for ones left from a
     * shift that has ended, to the shift on now. Each pass is logged.
     */
    public function handoverOrders(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'to' => ['required', Rule::in(['next', 'current'])],
            'order_ids' => 'nullable|array',
            'order_ids.*' => 'integer',
            'note' => 'nullable|string|max:1000',
        ]);

        $target = ShiftHandover::slotsFor($patient)[$validated['to']] ?? null;
        if (!$target) {
            return $this->refuse('There is no shift to pass the orders to. Check the ward\'s shift settings.');
        }

        $orders = ConsultantOrder::where('patient_id', $patient->id)
            ->open()
            ->when(!empty($validated['order_ids']), fn($query) => $query->whereIn('id', $validated['order_ids']))
            ->get()
            ->reject(fn(ConsultantOrder $order) => $order->isInSlot($target));

        if ($orders->isEmpty()) {
            return $this->refuse('Those orders are already with the ' . $target['label'] . ' shift.');
        }

        $to = $target['nurse'];
        $userId = NurseAppActor::userId($nurse);

        DB::transaction(function () use ($orders, $target, $to, $validated, $userId) {
            foreach ($orders as $order) {
                ConsultantOrderHandover::create([
                    'consultant_order_id' => $order->id,
                    'from_nurse_id' => $order->assigned_nurse_id,
                    'from_shift_date' => $order->shift_date,
                    'from_shift_code' => $order->shift_code,
                    'to_nurse_id' => $to?->id,
                    'to_shift_date' => $target['date'],
                    'to_shift_code' => $target['code'],
                    'note' => filled($validated['note'] ?? null) ? trim($validated['note']) : null,
                    'handed_over_by' => $userId,
                ]);

                $order->update([
                    'assigned_nurse_id' => $to?->id,
                    'shift_date' => $target['date'],
                    'shift_code' => $target['code'],
                ]);
            }
        });

        $this->log('Consultant orders handed over', $nurse, $patient, ['order_ids' => $orders->pluck('id')->all(), 'to' => $target['label']]);

        $count = $orders->count() . ' ' . Str::plural('order', $orders->count());

        return $this->done($nurse, $patient, $to
            ? $count . ' passed to ' . $to->name . ' (' . $target['label'] . ').'
            : $count . ' passed to the ' . $target['label'] . ' shift. No nurse is rostered to this bed for it yet.');
    }

    // ------------------------------------------------------------ I/O chart

    /**
     * POST /api/nurse/patients/{patient}/io/entries
     */
    public function storeIoEntry(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'direction' => ['required', Rule::in([FluidBalanceEntry::DIRECTION_INTAKE, FluidBalanceEntry::DIRECTION_OUTPUT])],
            'category' => ['required', Rule::in(array_keys(FluidBalanceEntry::typesFor($request->input('direction'))))],
            'volume_ml' => 'required|integer|min:1|max:' . FluidBalanceEntry::VOLUME_MAX,
            'description' => 'nullable|string|max:120',
            'minutes_ago' => 'nullable|integer|min:0|max:' . self::BACKDATE_MINUTES,
        ], [
            'category.in' => 'Choose what was taken in or passed out.',
            'volume_ml.required' => 'Enter the volume in mL.',
            'volume_ml.max' => 'One entry can be at most ' . number_format(FluidBalanceEntry::VOLUME_MAX) . ' mL. Record larger volumes as separate entries.',
            'minutes_ago.max' => 'An entry cannot be timed more than 24 hours back.',
        ]);

        $at = $this->timeFrom($validated['minutes_ago'] ?? null);

        $entry = FluidBalanceEntry::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'direction' => $validated['direction'],
            'category' => $validated['category'],
            'volume_ml' => $validated['volume_ml'],
            'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'recorded_at' => $at,
            'recorded_by' => NurseAppActor::userId($nurse),
        ]);

        $this->log('I/O chart entry recorded', $nurse, $patient, ['fluid_balance_entry_id' => $entry->id, 'volume_ml' => $entry->volume_ml]);

        return $this->done($nurse, $patient, ucfirst($entry->direction) . ' recorded: ' . $entry->label() . ', '
            . FluidBalanceEntry::formatMl($entry->volume_ml) . ' at ' . $at->format('H:i') . '.', $this->chartDay($patient, $at));
    }

    /**
     * POST /api/nurse/patients/{patient}/io/entries/{entry}/void - strike out
     * an entry made in error (it stays on the chart, no longer counted).
     */
    public function voidIoEntry(Request $request, Patient $patient, FluidBalanceEntry $entry): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $entry->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:255',
        ], [
            'void_reason.required' => 'Give a reason for striking out this entry.',
        ]);

        if ($entry->isVoided()) {
            return $this->refuse('That entry is already struck out.');
        }

        $entry->update([
            'voided_at' => now(),
            'voided_by' => NurseAppActor::userId($nurse),
            'void_reason' => trim($validated['void_reason']),
        ]);

        $this->log('I/O chart entry struck out', $nurse, $patient, ['fluid_balance_entry_id' => $entry->id, 'reason' => $entry->void_reason]);

        return $this->done($nurse, $patient, 'Struck out: ' . $entry->label() . ', ' . FluidBalanceEntry::formatMl($entry->volume_ml)
            . ' at ' . $entry->recorded_at->format('H:i') . '.', $this->chartDay($patient, $entry->recorded_at));
    }

    /**
     * POST /api/nurse/patients/{patient}/io/plan - intake limit per chart day
     * and minimum urine per hour. Both blank lifts the limits.
     */
    public function saveIoPlan(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'intake_limit_ml' => 'nullable|integer|min:' . FluidBalancePlan::LIMIT_MIN . '|max:' . FluidBalancePlan::LIMIT_MAX,
            'urine_min_ml_per_hour' => 'nullable|integer|min:' . FluidBalancePlan::URINE_MIN . '|max:' . FluidBalancePlan::URINE_MAX,
            'notes' => 'nullable|string|max:255',
        ], [
            'intake_limit_ml.min' => 'The intake limit must be at least ' . FluidBalancePlan::LIMIT_MIN . ' mL.',
            'intake_limit_ml.max' => 'The intake limit can be at most ' . number_format(FluidBalancePlan::LIMIT_MAX) . ' mL.',
            'urine_min_ml_per_hour.min' => 'The urine target must be at least ' . FluidBalancePlan::URINE_MIN . ' mL/h.',
            'urine_min_ml_per_hour.max' => 'The urine target can be at most ' . FluidBalancePlan::URINE_MAX . ' mL/h.',
        ]);

        $limit = isset($validated['intake_limit_ml']) ? (int) $validated['intake_limit_ml'] : null;
        $urineMin = isset($validated['urine_min_ml_per_hour']) ? (int) $validated['urine_min_ml_per_hour'] : null;
        $notes = filled($validated['notes'] ?? null) ? trim($validated['notes']) : null;
        $current = FluidBalancePlan::currentFor($patient);

        if ($current ? $current->matches($limit, $urineMin, $notes) : ($limit === null && $urineMin === null && $notes === null)) {
            return $this->done($nurse, $patient, 'Fluid plan unchanged.');
        }

        $plan = FluidBalancePlan::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'intake_limit_ml' => $limit,
            'urine_min_ml_per_hour' => $urineMin,
            'notes' => $notes,
            'set_by' => NurseAppActor::userId($nurse),
        ]);

        $this->log('Fluid plan set', $nurse, $patient, ['fluid_balance_plan_id' => $plan->id, 'previous_plan_id' => $current?->id]);

        return $this->done($nurse, $patient, $plan->hasLimits()
            ? 'Fluid plan set: ' . $plan->summary() . '.'
            : ($current?->hasLimits() ? 'Fluid limits lifted.' : 'Fluid plan saved.'));
    }

    /**
     * POST /api/nurse/patients/{patient}/io/assessments - bedside check for
     * fluid overload (edema, signs, weight).
     */
    public function storeIoAssessment(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'edema_grade' => ['required', 'integer', Rule::in(array_keys(FluidOverloadAssessment::EDEMA_GRADES))],
            'edema_sites' => 'nullable|array',
            'edema_sites.*' => [Rule::in(array_keys(FluidOverloadAssessment::EDEMA_SITES))],
            'signs' => 'nullable|array',
            'signs.*' => [Rule::in(array_keys(FluidOverloadAssessment::SIGNS))],
            'weight_kg' => 'nullable|numeric|min:' . FluidOverloadAssessment::WEIGHT_MIN . '|max:' . FluidOverloadAssessment::WEIGHT_MAX,
            'notes' => 'nullable|string|max:255',
            'minutes_ago' => 'nullable|integer|min:0|max:' . self::BACKDATE_MINUTES,
        ], [
            'edema_grade.required' => 'Choose the edema grade (None if there is no edema).',
        ]);

        $grade = (int) $validated['edema_grade'];

        $assessment = FluidOverloadAssessment::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'edema_grade' => $grade,
            'edema_sites' => $grade > 0 ? array_values(array_unique($validated['edema_sites'] ?? [])) : [],
            'signs' => array_values(array_unique($validated['signs'] ?? [])),
            'weight_kg' => isset($validated['weight_kg']) ? round((float) $validated['weight_kg'], 1) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'assessed_at' => $this->timeFrom($validated['minutes_ago'] ?? null),
            'recorded_by' => NurseAppActor::userId($nurse),
        ]);

        $this->log('Fluid overload assessment recorded', $nurse, $patient, ['fluid_overload_assessment_id' => $assessment->id]);

        $findings = $assessment->findings();

        return $this->done($nurse, $patient, 'Assessment recorded: '
            . ($findings ? implode(', ', $findings) : 'no signs of fluid overload')
            . ($assessment->weight_kg !== null ? ', weight ' . $assessment->weight_kg . ' kg' : '') . '.');
    }

    // ---------------------------------------------------------- medications

    /**
     * POST /api/nurse/patients/{patient}/medications/{medication}/doses -
     * given, or held / refused with a reason. The next dose is scheduled from
     * the time recorded.
     */
    public function recordDose(Request $request, Patient $patient, PatientMedication $medication): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $medication->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(MedicationAdministration::STATUSES))],
            'minutes_ago' => 'nullable|integer|min:0|max:' . self::BACKDATE_MINUTES,
            'notes' => [
                Rule::requiredIf(fn() => $request->input('status') !== MedicationAdministration::STATUS_GIVEN),
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'notes.required' => 'Give a reason when a dose is held or refused.',
            'minutes_ago.max' => 'A dose cannot be recorded more than 24 hours back.',
        ]);

        if (!$medication->isActive()) {
            return $this->refuse($medication->medication_name . ' is no longer active.');
        }

        $administration = $medication->record(
            $validated['status'],
            $this->timeFrom($validated['minutes_ago'] ?? null),
            filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            NurseAppActor::userId($nurse)
        );

        $this->log('Medication dose recorded', $nurse, $patient, [
            'patient_medication_id' => $medication->id,
            'status' => $administration->status,
        ]);

        return $this->done($nurse, $patient, $medication->medication_name . ': dose ' . strtolower($administration->statusLabel())
            . ' at ' . $administration->administered_at->format('H:i') . '.');
    }

    // ---------------------------------------------------- blood transfusion

    /**
     * POST /api/nurse/patients/{patient}/transfusions - register a unit. It
     * starts pending: nothing runs until the bedside checks are confirmed and
     * the unit is explicitly started.
     */
    public function storeTransfusion(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'unit_number' => 'required|string|max:64',
            'product_type' => ['required', Rule::in(BloodTransfusion::PRODUCT_TYPES)],
            'unit_blood_group' => ['nullable', Rule::in(BloodTransfusion::BLOOD_GROUPS)],
            'patient_blood_group' => ['nullable', Rule::in(BloodTransfusion::BLOOD_GROUPS)],
            'crossmatch_reference' => 'nullable|string|max:64',
            'unit_expires_at' => 'nullable|date',
            'volume_ml' => 'nullable|integer|min:' . BloodTransfusion::VOLUME_MIN . '|max:' . BloodTransfusion::VOLUME_MAX,
            'prescribed_minutes' => 'nullable|integer|min:' . BloodTransfusion::MINUTES_MIN . '|max:' . BloodTransfusion::MAX_RUNNING_MINUTES,
            'notes' => 'nullable|string|max:1000',
        ], [
            'unit_number.required' => 'Enter the unit number from the bag.',
            'product_type.required' => 'Choose the product.',
            'unit_expires_at.date' => 'Enter the expiry as a date and time, e.g. 2026-09-30 23:59.',
        ]);

        $unit = BloodTransfusion::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'unit_number' => trim($validated['unit_number']),
            'product_type' => $validated['product_type'],
            'unit_blood_group' => $validated['unit_blood_group'] ?? null,
            'patient_blood_group' => $validated['patient_blood_group'] ?? null,
            'crossmatch_reference' => filled($validated['crossmatch_reference'] ?? null) ? trim($validated['crossmatch_reference']) : null,
            'unit_expires_at' => $validated['unit_expires_at'] ?? null,
            'volume_ml' => $validated['volume_ml'] ?? null,
            'prescribed_minutes' => $validated['prescribed_minutes'] ?? null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'status' => BloodTransfusion::STATUS_PENDING,
            'created_by' => NurseAppActor::userId($nurse),
        ]);

        $this->log('Blood unit registered', $nurse, $patient, ['blood_transfusion_id' => $unit->id, 'unit_number' => $unit->unit_number]);

        return $this->done($nurse, $patient, 'Unit ' . $unit->unit_number . ' registered. Next: the bedside checks.');
    }

    /**
     * POST /api/nurse/patients/{patient}/transfusions/{transfusion}/checklist
     * - confirm or undo one pre-start check. The checks run in order: a step
     * is only accepted once everything before it is confirmed, and only the
     * most recent step can be undone. They are locked once the unit starts.
     */
    public function updateTransfusionChecklist(Request $request, Patient $patient, BloodTransfusion $transfusion): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $transfusion->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'step' => ['required', Rule::in(['check_crossmatch', 'check_product', 'check_expiry', 'check_identity'])],
            'action' => ['required', Rule::in(['confirm', 'undo'])],
        ]);

        if ($transfusion->isFinished()) {
            return $this->refuse('That unit is already finished.');
        }

        if ($transfusion->isRunning()) {
            return $this->refuse('The checks are locked once the unit has started.');
        }

        $step = collect($transfusion->checklistSteps())->firstWhere('key', $validated['step']);

        if ($validated['action'] === 'confirm') {
            if (!$step['unlocked']) {
                return $this->refuse('Confirm the earlier checks first.');
            }
            $transfusion->{$validated['step']} = true;
        } else {
            if (!$step['canUndo']) {
                return $this->refuse('Only the last confirmed check can be undone.');
            }
            $transfusion->{$validated['step']} = false;
        }

        $transfusion->checked_by = NurseAppActor::userId($nurse);
        $transfusion->checked_at = now();
        $transfusion->save();

        $this->log('Blood unit check ' . $validated['action'], $nurse, $patient, ['blood_transfusion_id' => $transfusion->id, 'step' => $validated['step']]);

        $done = $transfusion->completedStepCount();

        // The step labels already read as done ("Crossmatch confirmed")
        return $this->done($nurse, $patient, $validated['action'] === 'confirm'
            ? 'Check ' . $step['number'] . ' of 4 done: ' . $step['label'] . '.'
            : 'Check ' . $step['number'] . ' undone: ' . $step['label'] . '. ' . $done . ' of 4 done.');
    }

    /**
     * POST /api/nurse/patients/{patient}/transfusions/{transfusion}/start -
     * refused while any check is outstanding or anything critical is open, so
     * an expired or mismatched unit cannot be started from the app either.
     */
    public function startTransfusion(Request $request, Patient $patient, BloodTransfusion $transfusion): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $transfusion->patient_id);
        if ($denied) {
            return $denied;
        }

        if (!$transfusion->canStart()) {
            return $this->refuse(!$transfusion->isPending()
                ? 'That unit has already been started.'
                : ($transfusion->checksComplete()
                    ? 'Resolve the flagged problems before starting this unit.'
                    : 'Complete every pre-start check before starting this unit.'));
        }

        $transfusion->update([
            'status' => BloodTransfusion::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        $this->log('Blood unit started', $nurse, $patient, ['blood_transfusion_id' => $transfusion->id]);

        return $this->done($nurse, $patient, 'Unit ' . $transfusion->unit_number . ' started.');
    }

    /**
     * POST /api/nurse/patients/{patient}/transfusions/{transfusion}/finish -
     * completed, or stopped early with a reason.
     */
    public function finishTransfusion(Request $request, Patient $patient, BloodTransfusion $transfusion): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $transfusion->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['completed', 'stopped'])],
            'stop_reason' => 'nullable|string|max:255',
        ]);

        if (!$transfusion->isRunning()) {
            return $this->refuse('That unit is not running.');
        }

        if ($validated['outcome'] === 'stopped' && !filled($validated['stop_reason'] ?? null)) {
            return $this->refuse('Give a reason when stopping a unit early.');
        }

        $transfusion->update([
            'status' => $validated['outcome'] === 'completed'
                ? BloodTransfusion::STATUS_COMPLETED
                : BloodTransfusion::STATUS_STOPPED,
            'completed_at' => now(),
            'stop_reason' => filled($validated['stop_reason'] ?? null) ? trim($validated['stop_reason']) : null,
        ]);

        $this->log('Blood unit ' . $validated['outcome'], $nurse, $patient, ['blood_transfusion_id' => $transfusion->id]);

        return $this->done($nurse, $patient, 'Unit ' . $transfusion->unit_number . ' ' . $validated['outcome'] . '.');
    }

    // ---------------------------------------------------------- nursing plan

    /**
     * POST /api/nurse/patients/{patient}/care-plan - add a nursing diagnosis,
     * from a library template (as edited) or in the nurse's own words.
     */
    public function storeCarePlanItem(Request $request, Patient $patient): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate(NursingCarePlanRules::item(), NursingCarePlanRules::messages());

        if (!empty($validated['template_key']) && NursingCarePlanItem::where('patient_id', $patient->id)
            ->where('status', NursingCarePlanItem::STATUS_ACTIVE)
            ->where('template_key', $validated['template_key'])
            ->exists()) {
            return $this->refuse(NursingCarePlanLibrary::TEMPLATES[$validated['template_key']]['diagnosis'] . ' is already in the plan.');
        }

        $item = NursingCarePlan::add($patient, $validated, NurseAppActor::userId($nurse));

        $this->log('Care plan item added', $nurse, $patient, ['nursing_care_plan_item_id' => $item->id]);

        return $this->done($nurse, $patient, '"' . $item->diagnosis . '" added to the care plan.');
    }

    /**
     * POST /api/nurse/patients/{patient}/care-plan/{item}/update - change the
     * goal, the "related to" or the interventions.
     */
    public function updateCarePlanItem(Request $request, Patient $patient, NursingCarePlanItem $item): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $item->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate(NursingCarePlanRules::update(), NursingCarePlanRules::messages());

        if (!$item->isActive()) {
            return $this->refuse('That diagnosis is closed.');
        }

        NursingCarePlan::update($item, $validated);

        $this->log('Care plan item updated', $nurse, $patient, ['nursing_care_plan_item_id' => $item->id]);

        return $this->done($nurse, $patient, '"' . $item->diagnosis . '" updated.');
    }

    /**
     * POST /api/nurse/patients/{patient}/care-plan/{item}/evaluate - this
     * shift's evaluation: met, partly met or not met.
     */
    public function evaluateCarePlanItem(Request $request, Patient $patient, NursingCarePlanItem $item): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $item->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate(NursingCarePlanRules::evaluation($request), NursingCarePlanRules::messages());

        if (!$item->isActive()) {
            return $this->refuse('That diagnosis is closed.');
        }

        $evaluation = NursingCarePlan::evaluate($item, $validated['outcome'], $validated['note'] ?? null, NurseAppActor::userId($nurse));

        $this->log('Care plan item evaluated', $nurse, $patient, ['nursing_care_plan_item_id' => $item->id, 'outcome' => $evaluation->outcome]);

        return $this->done($nurse, $patient, '"' . $item->diagnosis . '" evaluated: ' . $evaluation->outcomeLabel() . '.');
    }

    /**
     * POST /api/nurse/patients/{patient}/care-plan/{item}/close - resolved
     * (goal reached) or discontinued (no longer relevant, with a reason).
     */
    public function closeCarePlanItem(Request $request, Patient $patient, NursingCarePlanItem $item): JsonResponse
    {
        [$nurse, $denied] = $this->authorise($request, $patient, $item->patient_id);
        if ($denied) {
            return $denied;
        }

        $validated = $request->validate(NursingCarePlanRules::close($request), NursingCarePlanRules::messages());

        if (!$item->isActive()) {
            return $this->refuse('That diagnosis is already closed.');
        }

        NursingCarePlan::close($item, $validated['status'], $validated['note'] ?? null, NurseAppActor::userId($nurse));

        $this->log('Care plan item ' . $validated['status'], $nurse, $patient, ['nursing_care_plan_item_id' => $item->id]);

        return $this->done($nurse, $patient, '"' . $item->diagnosis . '" ' . $validated['status'] . '.');
    }

    // --------------------------------------------------------------- alerts

    /**
     * POST /api/nurse/notifications/{notification}/respond
     */
    public function respondNotification(Request $request, WardNotification $notification): JsonResponse
    {
        $patient = Patient::find($notification->patient_id);
        if (!$patient) {
            return $this->refuse('That alert is no longer linked to a patient.', 404);
        }

        [$nurse, $denied] = $this->authorise($request, $patient);
        if ($denied) {
            return $denied;
        }

        if ($notification->status === WardNotification::STATUS_RESPONDED) {
            return $this->refuse('That alert has already been answered.', 409);
        }

        $notification->update([
            'status' => WardNotification::STATUS_RESPONDED,
            'responded_at' => now(),
            'responded_by' => NurseAppActor::userId($nurse),
        ]);

        $this->log('Notification responded', $nurse, $patient, ['notification_id' => $notification->id, 'type' => $notification->type]);

        return $this->done($nurse, $patient, 'Marked as answered.');
    }

    // -------------------------------------------------------------- helpers

    /**
     * The signed-in nurse, or the response refusing the request: 401 without
     * a valid token, 403 for a patient not on their wards, 404 when a record
     * in the URL belongs to another patient.
     *
     * @return array{0: ?Nurse, 1: ?JsonResponse}
     */
    private function authorise(Request $request, Patient $patient, ?int $recordPatientId = null): array
    {
        $nurse = NurseAppActor::nurse($request);

        if (!$nurse) {
            return [null, response()->json(['success' => false, 'message' => 'Unauthenticated. Please log in again.'], 401)];
        }

        if ($recordPatientId !== null && (int) $recordPatientId !== (int) $patient->id) {
            return [null, $this->refuse('That record does not belong to this patient.', 404)];
        }

        if (!NurseAppActor::canAccess($nurse, $patient)) {
            return [null, $this->refuse('This patient is not on your ward, or is no longer admitted.', 403)];
        }

        return [$nurse, null];
    }

    /** The action's result plus the patient as it now stands. */
    private function done(Nurse $nurse, Patient $patient, string $message, ?string $ioDay = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'patient' => NurseAppPatientBundle::build($patient->fresh(), $nurse, $ioDay),
        ]);
    }

    private function refuse(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function timeFrom($minutesAgo): Carbon
    {
        return $minutesAgo ? now()->subMinutes((int) $minutesAgo) : now();
    }

    /** The chart day (the date it starts on) a moment falls in. */
    private function chartDay(Patient $patient, $at): string
    {
        return FluidBalanceChart::dayAt($patient->ward_id, $at)[0]->toDateString();
    }

    private function log(string $what, Nurse $nurse, Patient $patient, array $context = []): void
    {
        Log::info('Nurse app: ' . $what, $context + [
            'nurse_id' => $nurse->id,
            'patient_id' => $patient->id,
        ]);
    }
}
