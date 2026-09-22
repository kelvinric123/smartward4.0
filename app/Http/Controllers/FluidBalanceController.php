<?php

namespace App\Http\Controllers;

use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\Patient;
use App\Support\FluidBalanceChart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The I/O Chart tab of Patient Details: record intake and output, strike out
 * an entry made in error, set the fluid plan (intake limit and minimum urine
 * output), and record signs of fluid overload. Every outcome, saved or not,
 * comes back to the I/O Chart tab.
 */
class FluidBalanceController extends Controller
{
    private const TAB = FluidBalanceChart::SETTINGS_TAB;

    /** How far back an entry or assessment may be timed, in hours. */
    private const BACKDATE_HOURS = 24;

    /**
     * Record one intake or output. It is timed now unless an earlier time is
     * given, and lands on whichever chart day that time falls in.
     */
    public function store(Request $request)
    {
        $patientId = $request->integer('patient_id') ?: null;

        $validated = $this->validateForTab($request, $patientId, [
            'patient_id' => 'required|exists:patients,id',
            'direction' => ['required', Rule::in([FluidBalanceEntry::DIRECTION_INTAKE, FluidBalanceEntry::DIRECTION_OUTPUT])],
            'category' => ['required', Rule::in(array_keys(FluidBalanceEntry::typesFor($request->input('direction'))))],
            'volume_ml' => 'required|integer|min:1|max:' . FluidBalanceEntry::VOLUME_MAX,
            'description' => 'nullable|string|max:120',
            'recorded_at' => $this->timeRules(),
        ], [
            'category.in' => 'Choose what was taken in or passed out.',
            'volume_ml.required' => 'Enter the volume in mL.',
            'volume_ml.max' => 'One entry can be at most ' . number_format(FluidBalanceEntry::VOLUME_MAX) . ' mL. Record larger volumes as separate entries.',
        ] + $this->timeMessages('recorded_at', 'An entry'));

        $patient = Patient::findOrFail($validated['patient_id']);
        $at = empty($validated['recorded_at']) ? now() : Carbon::parse($validated['recorded_at']);

        $entry = FluidBalanceEntry::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'direction' => $validated['direction'],
            'category' => $validated['category'],
            'volume_ml' => $validated['volume_ml'],
            'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'recorded_at' => $at,
            'recorded_by' => Auth::id(),
        ]);

        Log::info('I/O chart entry recorded', [
            'fluid_balance_entry_id' => $entry->id,
            'patient_id' => $patient->id,
            'direction' => $entry->direction,
            'category' => $entry->category,
            'volume_ml' => $entry->volume_ml,
            'recorded_at' => $at->toDateTimeString(),
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($patient->id, $this->dayOf($patient, $at))->with('success', ucfirst($entry->direction) . ' recorded: '
            . $entry->label() . ', ' . FluidBalanceEntry::formatMl($entry->volume_ml) . ' at ' . $at->format('H:i') . '.');
    }

    /**
     * Strike out an entry made in error. It stays on the chart, crossed
     * through with the reason, and no longer counts towards the totals.
     */
    public function void(Request $request, FluidBalanceEntry $entry)
    {
        $patient = $entry->patient;
        $day = $patient ? $this->dayOf($patient, $entry->recorded_at) : null;

        $validated = $this->validateForTab($request, $entry->patient_id, [
            'void_reason' => 'required|string|max:255',
        ], [
            'void_reason.required' => 'Give a reason for striking out this entry.',
        ], $day);

        if ($entry->isVoided()) {
            return $this->backToTab($entry->patient_id, $day)->with('error', 'That entry is already struck out.');
        }

        $entry->update([
            'voided_at' => now(),
            'voided_by' => Auth::id(),
            'void_reason' => trim($validated['void_reason']),
        ]);

        Log::warning('I/O chart entry struck out', [
            'fluid_balance_entry_id' => $entry->id,
            'patient_id' => $entry->patient_id,
            'direction' => $entry->direction,
            'category' => $entry->category,
            'volume_ml' => $entry->volume_ml,
            'recorded_at' => $entry->recorded_at?->toDateTimeString(),
            'reason' => $entry->void_reason,
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($entry->patient_id, $day)->with('success', 'Struck out: ' . $entry->label() . ', '
            . FluidBalanceEntry::formatMl($entry->volume_ml) . ' at ' . $entry->recorded_at->format('H:i') . '.');
    }

    /**
     * Set the fluid plan: the most the patient may take in per chart day and
     * the least urine expected per hour. Leaving both blank lifts the limits.
     */
    public function savePlan(Request $request)
    {
        $patientId = $request->integer('patient_id') ?: null;

        $validated = $this->validateForTab($request, $patientId, [
            'patient_id' => 'required|exists:patients,id',
            'intake_limit_ml' => 'nullable|integer|min:' . FluidBalancePlan::LIMIT_MIN . '|max:' . FluidBalancePlan::LIMIT_MAX,
            'urine_min_ml_per_hour' => 'nullable|integer|min:' . FluidBalancePlan::URINE_MIN . '|max:' . FluidBalancePlan::URINE_MAX,
            'notes' => 'nullable|string|max:255',
        ], [
            'intake_limit_ml.min' => 'The intake limit must be at least ' . FluidBalancePlan::LIMIT_MIN . ' mL.',
            'intake_limit_ml.max' => 'The intake limit can be at most ' . number_format(FluidBalancePlan::LIMIT_MAX) . ' mL.',
            'urine_min_ml_per_hour.min' => 'The urine target must be at least ' . FluidBalancePlan::URINE_MIN . ' mL/h.',
            'urine_min_ml_per_hour.max' => 'The urine target can be at most ' . FluidBalancePlan::URINE_MAX . ' mL/h.',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $limit = isset($validated['intake_limit_ml']) ? (int) $validated['intake_limit_ml'] : null;
        $urineMin = isset($validated['urine_min_ml_per_hour']) ? (int) $validated['urine_min_ml_per_hour'] : null;
        $notes = filled($validated['notes'] ?? null) ? trim($validated['notes']) : null;
        $current = FluidBalancePlan::currentFor($patient);

        if ($current ? $current->matches($limit, $urineMin, $notes) : ($limit === null && $urineMin === null && $notes === null)) {
            return $this->backToTab($patient->id)->with('success', 'Fluid plan unchanged.');
        }

        $plan = FluidBalancePlan::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'intake_limit_ml' => $limit,
            'urine_min_ml_per_hour' => $urineMin,
            'notes' => $notes,
            'set_by' => Auth::id(),
        ]);

        Log::info('Fluid plan set', [
            'fluid_balance_plan_id' => $plan->id,
            'patient_id' => $patient->id,
            'intake_limit_ml' => $limit,
            'urine_min_ml_per_hour' => $urineMin,
            'previous_plan_id' => $current?->id,
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($patient->id)->with('success', $plan->hasLimits()
            ? 'Fluid plan set: ' . $plan->summary() . '.'
            : ($current?->hasLimits() ? 'Fluid limits lifted.' : 'Fluid plan saved.'));
    }

    /**
     * Record a bedside check for fluid overload. Edema sites are only kept
     * when edema is present.
     */
    public function storeAssessment(Request $request)
    {
        $patientId = $request->integer('patient_id') ?: null;

        $validated = $this->validateForTab($request, $patientId, [
            'patient_id' => 'required|exists:patients,id',
            'edema_grade' => ['required', 'integer', Rule::in(array_keys(FluidOverloadAssessment::EDEMA_GRADES))],
            'edema_sites' => 'nullable|array',
            'edema_sites.*' => [Rule::in(array_keys(FluidOverloadAssessment::EDEMA_SITES))],
            'signs' => 'nullable|array',
            'signs.*' => [Rule::in(array_keys(FluidOverloadAssessment::SIGNS))],
            'weight_kg' => 'nullable|numeric|min:' . FluidOverloadAssessment::WEIGHT_MIN . '|max:' . FluidOverloadAssessment::WEIGHT_MAX,
            'notes' => 'nullable|string|max:255',
            'assessed_at' => $this->timeRules(),
        ], [
            'edema_grade.required' => 'Choose the edema grade (None if there is no edema).',
        ] + $this->timeMessages('assessed_at', 'An assessment'));

        $patient = Patient::findOrFail($validated['patient_id']);
        $grade = (int) $validated['edema_grade'];
        $at = empty($validated['assessed_at']) ? now() : Carbon::parse($validated['assessed_at']);

        $assessment = FluidOverloadAssessment::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'edema_grade' => $grade,
            'edema_sites' => $grade > 0 ? array_values(array_unique($validated['edema_sites'] ?? [])) : [],
            'signs' => array_values(array_unique($validated['signs'] ?? [])),
            'weight_kg' => isset($validated['weight_kg']) ? round((float) $validated['weight_kg'], 1) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            'assessed_at' => $at,
            'recorded_by' => Auth::id(),
        ]);

        Log::info('Fluid overload assessment recorded', [
            'fluid_overload_assessment_id' => $assessment->id,
            'patient_id' => $patient->id,
            'edema_grade' => $grade,
            'signs' => $assessment->signs,
            'weight_kg' => $assessment->weight_kg,
            'user_id' => Auth::id(),
        ]);

        $findings = $assessment->findings();

        return $this->backToTab($patient->id)->with('success', 'Assessment recorded: '
            . ($findings ? implode(', ', $findings) : 'no signs of fluid overload')
            . ($assessment->weight_kg !== null ? ', weight ' . $assessment->weight_kg . ' kg' : '') . '.');
    }

    /** An optional earlier time: no more than a day back, and not in the future. */
    private function timeRules(): array
    {
        return [
            'nullable',
            'date',
            'after_or_equal:' . now()->subHours(self::BACKDATE_HOURS)->toDateTimeString(),
            'before_or_equal:' . now()->addMinutes(5)->toDateTimeString(),
        ];
    }

    private function timeMessages(string $field, string $what): array
    {
        return [
            $field . '.after_or_equal' => $what . ' cannot be timed more than ' . self::BACKDATE_HOURS . ' hours back.',
            $field . '.before_or_equal' => $what . ' cannot be timed in the future.',
        ];
    }

    /** The chart day (the date it starts on) a moment falls in, for this patient's ward. */
    private function dayOf(Patient $patient, Carbon $at): string
    {
        return FluidBalanceChart::dayAt($patient->ward_id, $at)[0]->toDateString();
    }

    /**
     * Patient Details on the I/O Chart tab, on the given chart day when it is
     * not today's. The tab is named in the URL rather than taken from the page
     * the form came from, which may have been loaded on another tab.
     */
    private function tabUrl(int $patientId, ?string $day = null): string
    {
        $today = FluidBalanceChart::dayAt(Patient::whereKey($patientId)->value('ward_id'), now())[0]->toDateString();

        return route('ward.patient-details', array_filter([
            'patient_id' => $patientId,
            'active_tab' => self::TAB,
            'io_day' => $day !== null && $day !== $today ? $day : null,
        ]));
    }

    private function backToTab(?int $patientId, ?string $day = null): RedirectResponse
    {
        return $patientId ? redirect()->to($this->tabUrl($patientId, $day)) : back();
    }

    /**
     * Validate an I/O Chart form. When it fails, the tab opens again with the
     * form filled in and the reasons listed.
     */
    private function validateForTab(Request $request, ?int $patientId, array $rules, array $messages = [], ?string $day = null): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            $exception = new ValidationException($validator);

            throw $patientId ? $exception->redirectTo($this->tabUrl($patientId, $day)) : $exception;
        }

        return $validator->validated();
    }
}
