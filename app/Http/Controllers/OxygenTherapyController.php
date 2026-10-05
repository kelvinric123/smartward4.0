<?php

namespace App\Http\Controllers;

use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Support\OxygenTherapyChart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The Oxygen Therapy tab of Patient Details: change the patient's oxygen (device, flow
 * rate or FiO2, and the SpO2 target), change them to room air, or strike out a change
 * made in error. Every outcome, saved or not, comes back to the Oxygen Therapy tab.
 */
class OxygenTherapyController extends Controller
{
    private const TAB = OxygenTherapyChart::SETTINGS_TAB;

    /** How far back a change may be timed, in hours. */
    private const BACKDATE_HOURS = 24;

    /**
     * Record a change of oxygen. It starts now unless an earlier time is given.
     * Room air carries no flow rate or FiO2.
     */
    public function store(Request $request)
    {
        $patientId = $request->integer('patient_id') ?: null;
        $delivery = (string) $request->input('oxygen_delivery');
        $onOxygen = $delivery !== '' && $delivery !== VitalSign::OXYGEN_ROOM_AIR;
        $model = OxygenTherapyChange::class;

        $validated = $this->validateForTab($request, $patientId, [
            'patient_id' => 'required|exists:patients,id',
            'oxygen_delivery' => ['required', Rule::in(array_keys(VitalSign::OXYGEN_DELIVERY_OPTIONS))],
            'oxygen_flow_rate' => 'nullable|numeric|min:' . $model::FLOW_MIN . '|max:' . $model::FLOW_MAX,
            'fio2_percent' => 'nullable|integer|min:' . $model::FIO2_MIN . '|max:' . $model::FIO2_MAX,
            'target_spo2_min' => 'nullable|required_with:target_spo2_max|integer|min:' . $model::TARGET_MIN . '|max:' . $model::TARGET_MAX,
            'target_spo2_max' => 'nullable|required_with:target_spo2_min|integer|min:' . $model::TARGET_MIN . '|max:' . $model::TARGET_MAX . '|gt:target_spo2_min',
            'notes' => 'nullable|string|max:255',
            'started_at' => $this->timeRules(),
        ], [
            'oxygen_delivery.required' => 'Choose how the oxygen is given, or Room Air.',
            'oxygen_flow_rate.min' => 'The flow rate must be at least ' . $model::FLOW_MIN . ' L/min.',
            'oxygen_flow_rate.max' => 'The flow rate can be at most ' . $model::FLOW_MAX . ' L/min.',
            'fio2_percent.min' => 'FiO₂ cannot be below ' . $model::FIO2_MIN . '% (room air).',
            'fio2_percent.max' => 'FiO₂ can be at most ' . $model::FIO2_MAX . '%.',
            'target_spo2_min.required_with' => 'Enter both ends of the SpO₂ target, or neither.',
            'target_spo2_max.required_with' => 'Enter both ends of the SpO₂ target, or neither.',
            'target_spo2_max.gt' => 'The top of the SpO₂ target must be above the bottom.',
            'started_at.after_or_equal' => 'A change cannot be timed more than ' . self::BACKDATE_HOURS . ' hours back.',
            'started_at.before_or_equal' => 'A change cannot be timed in the future.',
        ], function ($validator) use ($request, $onOxygen, $delivery) {
            // Without a setting the chart has nothing to show for the device
            if ($onOxygen && !filled($request->input('oxygen_flow_rate')) && !filled($request->input('fio2_percent'))) {
                $validator->errors()->add('oxygen_flow_rate', 'Enter the flow rate or the FiO₂ for '
                    . (VitalSign::OXYGEN_DELIVERY_OPTIONS[$delivery] ?? 'this device') . '.');
            }
        });

        $patient = Patient::findOrFail($validated['patient_id']);
        $at = empty($validated['started_at']) ? now() : Carbon::parse($validated['started_at']);
        $target = isset($validated['target_spo2_min'], $validated['target_spo2_max'])
            ? [(int) $validated['target_spo2_min'], (int) $validated['target_spo2_max']]
            : null;
        $notes = filled($validated['notes'] ?? null) ? trim($validated['notes']) : null;

        $change = new OxygenTherapyChange([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'oxygen_delivery' => $delivery,
            'oxygen_flow_rate' => $onOxygen && isset($validated['oxygen_flow_rate']) ? round((float) $validated['oxygen_flow_rate'], 1) : null,
            'fio2_percent' => $onOxygen && isset($validated['fio2_percent']) ? (int) $validated['fio2_percent'] : null,
            'target_spo2_min' => $target[0] ?? null,
            'target_spo2_max' => $target[1] ?? null,
            'notes' => $notes,
            'started_at' => $at,
            'recorded_by' => Auth::id(),
        ]);

        // Saving what is already in force, from now, would only repeat it
        $current = OxygenTherapyChart::current($patient);
        if ($current && empty($validated['started_at']) && $notes === null
            && $current['record']->hasSameOxygenAs($change) && $current['target'] === $target) {
            return $this->backToTab($patient->id)->with('success', 'Oxygen unchanged: still ' . $this->describe($change) . '.');
        }

        $change->save();

        Log::info('Oxygen therapy changed', [
            'oxygen_therapy_change_id' => $change->id,
            'patient_id' => $patient->id,
            'oxygen_delivery' => $change->oxygen_delivery,
            'oxygen_flow_rate' => $change->oxygen_flow_rate,
            'fio2_percent' => $change->fio2_percent,
            'target_spo2' => $target,
            'started_at' => $at->toDateTimeString(),
            'previous' => $current['key'] ?? null,
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($patient->id)->with('success', ($change->isOnOxygen()
            ? 'Oxygen changed to ' . $this->describe($change)
            : 'Changed to room air') . ' at ' . $at->format('H:i') . '.');
    }

    /**
     * Strike out a change made in error. It stays in the history, crossed through with
     * the reason, and the oxygen goes back to what was in force before it.
     */
    public function void(Request $request, OxygenTherapyChange $change)
    {
        $validated = $this->validateForTab($request, $change->patient_id, [
            'void_reason' => 'required|string|max:255',
        ], [
            'void_reason.required' => 'Give a reason for striking out this change.',
        ]);

        if ($change->isVoided()) {
            return $this->backToTab($change->patient_id)->with('error', 'That change is already struck out.');
        }

        $change->update([
            'voided_at' => now(),
            'voided_by' => Auth::id(),
            'void_reason' => trim($validated['void_reason']),
        ]);

        Log::warning('Oxygen therapy change struck out', [
            'oxygen_therapy_change_id' => $change->id,
            'patient_id' => $change->patient_id,
            'oxygen_delivery' => $change->oxygen_delivery,
            'oxygen_flow_rate' => $change->oxygen_flow_rate,
            'fio2_percent' => $change->fio2_percent,
            'started_at' => $change->started_at?->toDateTimeString(),
            'reason' => $change->void_reason,
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($change->patient_id)->with('success', 'Struck out: '
            . $this->describe($change) . ' from ' . $change->started_at->format('H:i') . '.');
    }

    /** "Nasal Cannula / Prongs 2 L/min", or "Room Air". */
    private function describe(OxygenTherapyChange $change): string
    {
        return $change->oxygenDeliveryLabel() . ($change->isOnOxygen() && $change->oxygenSettingsLabel()
            ? ' ' . $change->oxygenSettingsLabel()
            : '');
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

    /**
     * Patient Details on the Oxygen Therapy tab. The tab is named in the URL rather than
     * taken from the page the form came from, which may have been loaded on another tab.
     */
    private function backToTab(?int $patientId): RedirectResponse
    {
        return $patientId
            ? redirect()->route('ward.patient-details', ['patient_id' => $patientId, 'active_tab' => self::TAB])
            : back();
    }

    /**
     * Validate an Oxygen Therapy form. When it fails, the tab opens again with the
     * form filled in and the reasons listed.
     */
    private function validateForTab(Request $request, ?int $patientId, array $rules, array $messages = [], ?callable $after = null): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($after) {
            $validator->after($after);
        }

        if ($validator->fails()) {
            $exception = new ValidationException($validator);

            throw $patientId
                ? $exception->redirectTo(route('ward.patient-details', ['patient_id' => $patientId, 'active_tab' => self::TAB]))
                : $exception;
        }

        return $validator->validated();
    }
}
