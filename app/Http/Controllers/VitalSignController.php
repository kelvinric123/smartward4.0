<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VitalSign;
use App\Models\Patient;
use App\Models\AdmissionLog;
use App\Http\Middleware\RequireDeletePassphrase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VitalSignController extends Controller
{
    /**
     * Display the vital signs record page with search functionality
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));
        $patientId = $request->input('patient_id');
        $admissionId = $request->input('admission_id');

        // Get all patients for the dropdown
        $patients = Patient::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'mrn', 'rn', 'status', 'admitted_at']);

        // Query vital signs with patient relationship
        $query = VitalSign::with(['patient', 'recordedBy', 'operator', 'gatewayDevice.ward'])
            ->orderBy('recorded_at', 'desc');

        // Filter by search term
        if ($search !== '') {
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('mrn', 'like', '%' . $search . '%');
            });
        }

        // Filter by specific patient
        if ($patientId) {
            $query->where('patient_id', $patientId);
        }

        // Filter by specific admission
        if ($admissionId) {
            $query->where('admission_id', $admissionId);
        }

        $vitalSigns = $query->paginate(25)->withQueryString();

        // Get unique admissions for the selected patient (for dropdown)
        $admissions = [];
        $selectedPatient = null;
        if ($patientId) {
            $selectedPatient = Patient::find($patientId);
            $admissions = $this->getPatientAdmissions($patientId);
            $admissions = $this->getPatientAdmissions($patientId);
        }

        // Get gateways (API users) and nurses for binding modal
        $gateways = \App\Models\ApiUser::where('is_active', true)->orderBy('name')->get();
        $nurses = \App\Models\Nurse::where('is_active', true)->orderBy('name')->get();

        // Get active bindings
        $activeBindings = \App\Models\GatewayNurseBinding::with(['apiUser', 'nurse'])
            ->where('start_at', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', now());
            })
            ->get();

        return view('vital-signs.index', [
            'vitalSigns' => $vitalSigns,
            'patients' => $patients,
            'selectedPatient' => $selectedPatient,
            'admissions' => $admissions,
            'search' => $search,
            'patientId' => $patientId,
            'admissionId' => $admissionId,
            'gateways' => $gateways,
            'nurses' => $nurses,
            'activeBindings' => $activeBindings,
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    /**
     * Store a new vital sign reading
     */
    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'systolic_bp' => 'nullable|integer|min:40|max:300',
            'diastolic_bp' => 'nullable|integer|min:20|max:200',
            'pulse_rate' => 'nullable|integer|min:20|max:250',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'spo2' => 'nullable|integer|min:50|max:100',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'reading_type' => 'nullable|in:single,full',
            'notes' => 'nullable|string|max:500',
            'recorded_at' => 'nullable|date',
        ] + $this->oxygenRules());

        $patient = Patient::findOrFail($request->patient_id);

        // Determine admission ID
        $admissionId = $patient->getCurrentAdmissionId();
        if (!$admissionId) {
            // For non-warded patients, create a general admission ID
            $admissionId = 'GEN-' . $patient->id . '-' . now()->format('Ymd');
        }

        // Determine reading type
        $readingType = $request->input('reading_type', 'single');
        $filledCount = collect([
            $request->systolic_bp,
            $request->diastolic_bp,
            $request->pulse_rate,
            $request->temperature,
            $request->spo2,
            $request->respiratory_rate,
        ])->filter(fn($v) => $v !== null)->count();

        if ($filledCount >= 5) {
            $readingType = 'full';
        }

        $vitalSign = VitalSign::create([
            'patient_id' => $request->patient_id,
            'admission_id' => $admissionId,
            'recorded_by' => Auth::id(),
            'systolic_bp' => $request->systolic_bp,
            'diastolic_bp' => $request->diastolic_bp,
            'pulse_rate' => $request->pulse_rate,
            'temperature' => $request->temperature,
            'spo2' => $request->spo2,
            'respiratory_rate' => $request->respiratory_rate,
            'reading_type' => $readingType,
            'notes' => $request->notes,
            'recorded_at' => $request->recorded_at ?? now(),
        ] + $this->oxygenValues($request));

        Log::info('Vital sign recorded', [
            'vital_sign_id' => $vitalSign->id,
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'admission_id' => $admissionId,
            'reading_type' => $readingType,
            'user_id' => Auth::id(),
        ]);

        return back()->with('success', 'Vital signs recorded successfully for ' . $patient->name);
    }

    /**
     * Get vital signs for a specific patient (for modal in ward dashboard)
     */
    public function patientVitals(Request $request): View
    {
        $patientId = $request->input('patient_id');
        $admissionId = $request->input('admission_id');
        $dateParam = $request->input('date'); // Date for IHH chart pagination (Y-m-d format)

        $patient = null;
        $vitalSigns = collect();
        $admissions = [];
        $selectedDate = $dateParam ? \Carbon\Carbon::parse($dateParam)->startOfDay() : now()->startOfDay();
        $hasPreviousDay = false;
        $hasNextDay = false;

        if ($patientId) {
            $patient = Patient::with(['ward', 'consultant'])->find($patientId);

            if ($patient) {
                $admissions = $this->getPatientAdmissions($patientId);

                // Base query for date-filtered vitals (for IHH chart)
                $dateQuery = VitalSign::where('patient_id', $patientId);
                if ($admissionId) {
                    $dateQuery->where('admission_id', $admissionId);
                }

                // Check if there are vitals before the selected date
                $hasPreviousDay = (clone $dateQuery)
                    ->where('recorded_at', '<', $selectedDate)
                    ->exists();

                // Check if there are vitals after the selected date (but not in the future)
                $tomorrow = $selectedDate->copy()->addDay();
                $hasNextDay = $selectedDate->lt(now()->startOfDay()) && (clone $dateQuery)
                    ->where('recorded_at', '>=', $tomorrow)
                    ->where('recorded_at', '<=', now())
                    ->exists();

                // Get all vitals (for list/graph views) - ordered desc
                $allVitalsQuery = VitalSign::where('patient_id', $patientId)
                    ->orderBy('recorded_at', 'desc');
                if ($admissionId) {
                    $allVitalsQuery->where('admission_id', $admissionId);
                }
                $vitalSigns = $allVitalsQuery->take(50)->get();
            }
        }

        return view('vital-signs.patient-vitals', [
            // The same panel is shown read-only in the ward dashboard modal and
            // editable in the patient details vitals tab (?edit=1)
            'editable' => $request->boolean('edit'),
            'patient' => $patient,
            'vitalSigns' => $vitalSigns,
            'admissions' => $admissions,
            'selectedAdmissionId' => $admissionId,
            'selectedDate' => $selectedDate,
            'hasPreviousDay' => $hasPreviousDay,
            'hasNextDay' => $hasNextDay,
        ]);
    }

    /**
     * Get patient admissions for dropdown
     */
    private function getPatientAdmissions($patientId): array
    {
        // Get admissions from vital signs
        $vitalSignAdmissions = VitalSign::where('patient_id', $patientId)
            ->whereNotNull('admission_id')
            ->select('admission_id')
            ->distinct()
            ->pluck('admission_id')
            ->toArray();

        // Get admissions from admission logs
        $admissionLogs = AdmissionLog::where('patient_id', $patientId)
            ->whereIn('action', ['admit', 'check-in'])
            ->orderBy('created_at', 'desc')
            ->get(['id', 'action', 'admitted_at', 'created_at', 'ward_id', 'bed_number']);

        $admissions = [];

        foreach ($admissionLogs as $log) {
            $admissionId = 'ADM-' . $patientId . '-' . ($log->admitted_at ?? $log->created_at)->format('YmdHis');
            $admissions[$admissionId] = [
                'id' => $admissionId,
                'label' => ($log->admitted_at ?? $log->created_at)->format('Y-m-d H:i') . ' (Bed: ' . ($log->bed_number ?? 'N/A') . ')',
                'date' => $log->admitted_at ?? $log->created_at,
            ];
        }

        // Add any admissions that only exist in vital signs
        foreach ($vitalSignAdmissions as $admId) {
            if (!isset($admissions[$admId])) {
                $admissions[$admId] = [
                    'id' => $admId,
                    'label' => $admId,
                    'date' => null,
                ];
            }
        }

        // Sort by date descending
        uasort($admissions, function ($a, $b) {
            if (!$a['date'] && !$b['date'])
                return 0;
            if (!$a['date'])
                return 1;
            if (!$b['date'])
                return -1;
            return $b['date']->timestamp - $a['date']->timestamp;
        });

        return array_values($admissions);
    }

    /**
     * Get latest vital signs for a patient (for bed box display)
     */
    public function latestVitals(Request $request)
    {
        $patientId = $request->input('patient_id');

        if (!$patientId) {
            return response()->json(['error' => 'Patient ID required'], 400);
        }

        $latestVital = VitalSign::where('patient_id', $patientId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$latestVital) {
            return response()->json(['message' => 'No vital signs recorded'], 404);
        }

        return response()->json([
            'id' => $latestVital->id,
            'blood_pressure' => $latestVital->blood_pressure,
            'systolic_bp' => $latestVital->systolic_bp,
            'diastolic_bp' => $latestVital->diastolic_bp,
            'pulse_rate' => $latestVital->pulse_rate,
            'temperature' => $latestVital->temperature,
            'spo2' => $latestVital->spo2,
            'respiratory_rate' => $latestVital->respiratory_rate,
            'reading_type' => $latestVital->reading_type,
            'recorded_at' => $latestVital->recorded_at->format('Y-m-d H:i'),
            'time_ago' => $latestVital->recorded_at->diffForHumans(),
        ]);
    }

    /**
     * Check for new vital signs from bound gateways (for browser notifications).
     */
    /**
     * Check for new vital signs (for browser notifications).
     * Returns ANY new vital sign recorded after the 'since' timestamp.
     */
    public function checkNewVitalSigns(Request $request)
    {
        $since = $request->input('since'); // ISO timestamp or seconds since epoch
        $sinceTime = $since ? \Carbon\Carbon::parse($since) : now()->subSeconds(10);

        // Get new vital signs recorded since the given time
        $newVitals = VitalSign::with(['patient', 'operator', 'recordedBy'])
            ->where('recorded_at', '>', $sinceTime)
            // prevent fetching future dated records that might be manually entered erroneously
            ->where('recorded_at', '<=', now())
            ->orderBy('recorded_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($vital) {
                // Determine source name
                $source = 'Manual';
                if ($vital->operator) {
                    // Try to find if this operator is currently bound to a gateway
                    // This is just for display, not for filtering
                    $binding = \App\Models\GatewayNurseBinding::with('apiUser')
                        ->where('nurse_id', $vital->operator_id)
                        ->where('start_at', '<=', $vital->recorded_at)
                        ->where(function ($q) use ($vital) {
                        $q->whereNull('end_at')
                            ->orWhere('end_at', '>=', $vital->recorded_at);
                    })
                        ->first();

                    $source = $binding ? $binding->apiUser->name : ($vital->operator->name . ' (Device)');
                } elseif ($vital->recordedBy) {
                    $source = $vital->recordedBy->name;
                }

                return [
                    'id' => $vital->id,
                    'patient_name' => $vital->patient->name ?? 'Unknown',
                    'patient_mrn' => $vital->patient->mrn ?? '',
                    'source' => $source, // Renamed from 'gateway' to 'source' to be more generic
                    'gateway' => $source, // Keep 'gateway' for backward compatibility if needed temporarily
                    'systolic_bp' => $vital->systolic_bp,
                    'diastolic_bp' => $vital->diastolic_bp,
                    'pulse_rate' => $vital->pulse_rate,
                    'temperature' => $vital->temperature,
                    'spo2' => $vital->spo2,
                    'respiratory_rate' => $vital->respiratory_rate,
                    'recorded_at' => $vital->recorded_at->format('H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'new_vitals' => $newVitals,
            'count' => $newVitals->count(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Remove the specified vital sign from storage (soft delete).
     */
    /**
     * Validation rules for the oxygen fields, shared by create and update.
     */
    private function oxygenRules(): array
    {
        return [
            'oxygen_delivery' => ['nullable', Rule::in(array_keys(VitalSign::OXYGEN_DELIVERY_OPTIONS))],
            'oxygen_flow_rate' => 'nullable|numeric|min:0|max:100',
            'fio2_percent' => 'nullable|integer|min:21|max:100',
        ];
    }

    /**
     * Oxygen columns to write. Room air carries no flow rate or FiO2.
     */
    private function oxygenValues(Request $request): array
    {
        $delivery = $request->input('oxygen_delivery') ?: null;
        $onOxygen = $delivery !== null && $delivery !== VitalSign::OXYGEN_ROOM_AIR;

        return [
            'oxygen_delivery' => $delivery,
            'oxygen_flow_rate' => $onOxygen ? $request->input('oxygen_flow_rate') : null,
            'fio2_percent' => $onOxygen ? $request->input('fio2_percent') : null,
        ];
    }

    /**
     * Correct a manually entered reading.
     *
     * Readings pushed in by a monitor gateway stay read-only, and the same passphrase
     * that protects deletions is required here.
     */
    public function update(Request $request, VitalSign $vitalSign)
    {
        if (!$vitalSign->isManualEntry()) {
            return back()->with('error', 'Readings received from a monitor cannot be edited.');
        }

        if (!RequireDeletePassphrase::matches($request->input('delete_passphrase'))) {
            return back()->with('error', 'Edit failed: Invalid or missing passphrase.');
        }

        $validated = $request->validate([
            'systolic_bp' => 'nullable|integer|min:40|max:300',
            'diastolic_bp' => 'nullable|integer|min:20|max:200',
            'pulse_rate' => 'nullable|integer|min:20|max:250',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'spo2' => 'nullable|integer|min:50|max:100',
            'respiratory_rate' => 'nullable|integer|min:5|max:60',
            'notes' => 'nullable|string|max:500',
            'recorded_at' => 'nullable|date',
        ] + $this->oxygenRules());

        $vitalSign->fill([
            'systolic_bp' => $validated['systolic_bp'] ?? null,
            'diastolic_bp' => $validated['diastolic_bp'] ?? null,
            'pulse_rate' => $validated['pulse_rate'] ?? null,
            'temperature' => $validated['temperature'] ?? null,
            'spo2' => $validated['spo2'] ?? null,
            'respiratory_rate' => $validated['respiratory_rate'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => Auth::id(),
        ] + $this->oxygenValues($request));

        if (!empty($validated['recorded_at'])) {
            $vitalSign->recorded_at = $validated['recorded_at'];
        }

        $filledCount = collect([
            $vitalSign->systolic_bp,
            $vitalSign->diastolic_bp,
            $vitalSign->pulse_rate,
            $vitalSign->temperature,
            $vitalSign->spo2,
            $vitalSign->respiratory_rate,
        ])->filter(fn ($value) => $value !== null)->count();
        $vitalSign->reading_type = $filledCount >= 5 ? 'full' : 'single';

        $vitalSign->save();

        Log::info('Vital sign updated', [
            'vital_sign_id' => $vitalSign->id,
            'patient_id' => $vitalSign->patient_id,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', 'Vital signs updated successfully.');
    }

    public function destroy(VitalSign $vitalSign)
    {
        // Record who deleted it before soft deleting
        $vitalSign->deleted_by = Auth::id();
        $vitalSign->save();

        $vitalSign->delete();

        Log::info('Vital sign deleted', [
            'vital_sign_id' => $vitalSign->id,
            'patient_id' => $vitalSign->patient_id,
            'deleted_by' => Auth::id(),
        ]);

        return back()->with('success', 'Vital sign record removed successfully.');
    }
}





































