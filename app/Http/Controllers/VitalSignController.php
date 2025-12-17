<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VitalSign;
use App\Models\Patient;
use App\Models\AdmissionLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            ->get(['id', 'name', 'mrn', 'status', 'admitted_at']);

        // Query vital signs with patient relationship
        $query = VitalSign::with(['patient', 'recordedBy'])
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
        }

        return view('vital-signs.index', [
            'vitalSigns' => $vitalSigns,
            'patients' => $patients,
            'selectedPatient' => $selectedPatient,
            'admissions' => $admissions,
            'search' => $search,
            'patientId' => $patientId,
            'admissionId' => $admissionId,
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
        ]);

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
        ]);

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

        $patient = null;
        $vitalSigns = collect();
        $admissions = [];

        if ($patientId) {
            $patient = Patient::with(['ward', 'consultant'])->find($patientId);
            
            if ($patient) {
                $admissions = $this->getPatientAdmissions($patientId);
                
                $query = VitalSign::where('patient_id', $patientId)
                    ->orderBy('recorded_at', 'desc');

                if ($admissionId) {
                    $query->where('admission_id', $admissionId);
                }

                $vitalSigns = $query->take(50)->get();
            }
        }

        return view('vital-signs.patient-vitals', [
            'patient' => $patient,
            'vitalSigns' => $vitalSigns,
            'admissions' => $admissions,
            'selectedAdmissionId' => $admissionId,
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
            if (!$a['date'] && !$b['date']) return 0;
            if (!$a['date']) return 1;
            if (!$b['date']) return -1;
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
}





































