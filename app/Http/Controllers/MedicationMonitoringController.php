<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequireDeletePassphrase;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Medication monitoring from the Medications tab of Patient Details: add an
 * order, document each dose (given, held or refused), stop an order, and undo
 * a dose recorded by mistake. Every outcome, saved or not, comes back to the
 * Medications tab.
 */
class MedicationMonitoringController extends Controller
{
    private const TAB = 'medications';

    /**
     * Add a medication order. The first dose is either due at the start time
     * or recorded as given straight away.
     */
    public function store(Request $request)
    {
        $patientId = $request->integer('patient_id') ?: null;

        $validated = $this->validateForTab($request, $patientId, [
            'patient_id' => 'required|exists:patients,id',
            'medication_id' => 'nullable|integer|exists:medications,id',
            'medication_name' => 'nullable|required_without:medication_id|string|max:120',
            'dose_amount' => 'required|numeric|gt:0|max:100000',
            'dose_unit' => ['required', Rule::in(PatientMedication::DOSE_UNITS)],
            'route' => ['required', Rule::in(array_keys(PatientMedication::ROUTES))],
            'infusion_volume_ml' => 'nullable|integer|min:1|max:' . \App\Models\FluidBalanceEntry::VOLUME_MAX,
            'frequency' => ['required', Rule::in(array_keys(PatientMedication::FREQUENCIES))],
            'interval_hours' => [
                'nullable',
                'required_if:frequency,custom',
                'numeric',
                'min:' . PatientMedication::MIN_INTERVAL_HOURS,
                'max:' . PatientMedication::MAX_INTERVAL_HOURS,
            ],
            'first_dose' => ['required', Rule::in(['due_now', 'due_at', 'given_now'])],
            'start_at' => [
                'nullable',
                'required_if:first_dose,due_at',
                'date',
                'after_or_equal:' . now()->subDay()->toDateTimeString(),
                'before_or_equal:' . now()->addDays(14)->toDateTimeString(),
            ],
            'instructions' => 'nullable|string|max:255',
        ], [
            'medication_name.required_without' => 'Choose a medication from the list or type its name.',
            'interval_hours.required_if' => 'Enter how many hours apart the doses are.',
            'start_at.required_if' => 'Enter when the first dose is due.',
            'start_at.after_or_equal' => 'The first dose time cannot be more than 24 hours ago.',
            'start_at.before_or_equal' => 'The first dose time cannot be more than 14 days ahead.',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $formulary = isset($validated['medication_id']) ? Medication::find($validated['medication_id']) : null;
        $givenNow = $validated['first_dose'] === 'given_now';
        $now = now();

        $order = PatientMedication::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'medication_id' => $formulary?->id,
            'medication_name' => $formulary?->name ?? trim($validated['medication_name']),
            'dose_amount' => $validated['dose_amount'],
            'dose_unit' => $validated['dose_unit'],
            'route' => $validated['route'],
            // Only an IV dose carries fluid onto the I/O chart
            'infusion_volume_ml' => $validated['route'] === \App\Services\FluidBalanceLinks::IV_ROUTE
                ? ($validated['infusion_volume_ml'] ?? null)
                : null,
            'frequency' => $validated['frequency'],
            'interval_minutes' => PatientMedication::intervalMinutesFor(
                $validated['frequency'],
                $validated['interval_hours'] ?? null
            ),
            'is_high_alert' => (bool) $formulary?->is_high_alert,
            'instructions' => $validated['instructions'] ?? null,
            'status' => PatientMedication::STATUS_ACTIVE,
            'start_at' => $validated['first_dose'] === 'due_at' && !empty($validated['start_at'])
                ? Carbon::parse($validated['start_at'])
                : $now,
            'created_by' => Auth::id(),
        ]);

        if ($givenNow) {
            $order->record(MedicationAdministration::STATUS_GIVEN, $now, null, Auth::id());
        } else {
            $order->reschedule();
        }

        Log::info('Medication order added', [
            'patient_medication_id' => $order->id,
            'patient_id' => $patient->id,
            'medication' => $order->medication_name,
            'order' => $order->summary(),
            'first_dose_given_now' => $givenNow,
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($patient->id)->with('success', $order->medication_name . ' ' . $order->summary()
            . ' added. ' . $this->nextDoseSentence($order));
    }

    /**
     * Document a dose: given, or held / refused with a reason. The next dose
     * is scheduled one interval from the time recorded.
     */
    public function administer(Request $request, PatientMedication $patientMedication)
    {
        $validated = $this->validateForTab($request, $patientMedication->patient_id, [
            'status' => ['required', Rule::in(array_keys(MedicationAdministration::STATUSES))],
            'administered_at' => [
                'nullable',
                'date',
                'after_or_equal:' . now()->subDay()->toDateTimeString(),
                'before_or_equal:' . now()->addMinutes(5)->toDateTimeString(),
            ],
            'notes' => [
                Rule::requiredIf(fn () => $request->input('status') !== MedicationAdministration::STATUS_GIVEN),
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'notes.required' => 'Give a reason when a dose is held or refused.',
            'administered_at.before_or_equal' => 'A dose cannot be recorded in the future.',
            'administered_at.after_or_equal' => 'A dose cannot be recorded more than 24 hours back.',
        ]);

        if (!$patientMedication->isActive()) {
            return $this->backToTab($patientMedication->patient_id)
                ->with('error', $patientMedication->medication_name . ' is no longer active.');
        }

        $at = empty($validated['administered_at']) ? now() : Carbon::parse($validated['administered_at']);
        $administration = $patientMedication->record(
            $validated['status'],
            $at,
            $validated['notes'] ?? null,
            Auth::id()
        );

        Log::info('Medication dose recorded', [
            'patient_medication_id' => $patientMedication->id,
            'patient_id' => $patientMedication->patient_id,
            'medication' => $patientMedication->medication_name,
            'status' => $administration->status,
            'administered_at' => $administration->administered_at?->toDateTimeString(),
            'due_at' => $administration->due_at?->toDateTimeString(),
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($patientMedication->patient_id)->with('success', $patientMedication->medication_name . ': '
            . strtolower($administration->statusLabel()) . ' at ' . $at->format('H:i') . '. '
            . $this->nextDoseSentence($patientMedication));
    }

    /**
     * Stop (discontinue) an order. It stays on record under "Stopped".
     */
    public function stop(Request $request, PatientMedication $patientMedication)
    {
        $validated = $this->validateForTab($request, $patientMedication->patient_id, [
            'stop_reason' => 'required|string|max:255',
        ], [
            'stop_reason.required' => 'Give a reason for stopping this medication.',
        ]);

        if (!$patientMedication->isActive()) {
            return $this->backToTab($patientMedication->patient_id)
                ->with('error', $patientMedication->medication_name . ' is no longer active.');
        }

        $patientMedication->stop($validated['stop_reason'], Auth::id());

        Log::info('Medication order stopped', [
            'patient_medication_id' => $patientMedication->id,
            'patient_id' => $patientMedication->patient_id,
            'medication' => $patientMedication->medication_name,
            'reason' => $validated['stop_reason'],
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($patientMedication->patient_id)
            ->with('success', $patientMedication->medication_name . ' stopped.');
    }

    /**
     * Remove the latest dose record of an order when it was recorded by
     * mistake, so an overdue dose is not hidden. Needs the delete passphrase.
     */
    public function undo(Request $request, MedicationAdministration $administration)
    {
        $order = $administration->order;

        if (!RequireDeletePassphrase::matches($request->input('delete_passphrase'))) {
            return $this->backToTab($order->patient_id)->with('error', 'Undo failed: invalid or missing passphrase.');
        }

        $latest = $order->administrations()->orderByDesc('administered_at')->orderByDesc('id')->first();

        if ($order->status === PatientMedication::STATUS_STOPPED || !$latest || !$latest->is($administration)) {
            return $this->backToTab($order->patient_id)
                ->with('error', 'Only the latest dose of a medication that is not stopped can be undone.');
        }

        $administration->delete();
        $order->reschedule();

        Log::warning('Medication dose record undone', [
            'patient_medication_id' => $order->id,
            'patient_id' => $order->patient_id,
            'medication' => $order->medication_name,
            'status' => $administration->status,
            'administered_at' => $administration->administered_at?->toDateTimeString(),
            'user_id' => Auth::id(),
        ]);

        return $this->backToTab($order->patient_id)->with('success', 'Dose record removed from '
            . $order->medication_name . '. ' . $this->nextDoseSentence($order));
    }

    /**
     * Patient Details on the Medications tab. The tab is named in the URL
     * rather than taken from the page the form came from, which may have been
     * loaded on another tab (e.g. after saving Additional Info).
     */
    private function tabUrl(int $patientId): string
    {
        return route('ward.patient-details', ['patient_id' => $patientId, 'active_tab' => self::TAB]);
    }

    private function backToTab(?int $patientId): RedirectResponse
    {
        return $patientId ? redirect()->to($this->tabUrl($patientId)) : back();
    }

    /**
     * Validate a Medications tab form. When it fails, the tab opens again
     * with the form filled in and the reasons listed.
     */
    private function validateForTab(Request $request, ?int $patientId, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            $exception = new ValidationException($validator);

            throw $patientId ? $exception->redirectTo($this->tabUrl($patientId)) : $exception;
        }

        return $validator->validated();
    }

    private function nextDoseSentence(PatientMedication $order): string
    {
        if ($order->status === PatientMedication::STATUS_COMPLETED) {
            return 'Single dose complete.';
        }

        if ($order->isPrn()) {
            $allowed = $order->nextAllowedAt();

            return $allowed && $allowed->isFuture()
                ? 'Next dose not before ' . $allowed->format('d M H:i') . '.'
                : 'Give when required.';
        }

        return $order->next_due_at ? 'Next dose due ' . $order->next_due_at->format('d M H:i') . '.' : '';
    }
}
