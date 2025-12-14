<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Consultant;
use App\Models\Nurse;
use App\Models\Anaesthetist;
use App\Models\Ward;
use App\Models\Bed;
use App\Models\AdmissionLog;
use App\Models\PatientMovement;
use App\Models\PatientReferral;
use App\Models\WardDashboardSetting;
use App\Models\VitalSign;
use App\Models\ShiftSetting;
use App\Models\WardScheduleAssignment;
use App\Models\DietType;
use App\Models\IsolationType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WardDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get all wards
        $wards = Ward::where('is_active', true)->get();
        
        // Get selected ward (default to first ward)
        $selectedWardId = $request->input('ward_id', $wards->first()->id ?? null);
        $selectedWard = Ward::find($selectedWardId);
        
        if (!$selectedWard) {
            $selectedWard = $wards->first();
            $selectedWardId = $selectedWard ? $selectedWard->id : null;
        }
        
        // If no ward exists, return empty view
        if (!$selectedWard || !$selectedWardId) {
            $defaultBedBoxConfig = [
                'patient_name' => ['key' => 'patient_name', 'visible' => true, 'order' => 0],
                'consultant' => ['key' => 'consultant', 'visible' => true, 'order' => 1],
                'nurse' => ['key' => 'nurse', 'visible' => true, 'order' => 2],
                'admitted_duration' => ['key' => 'admitted_duration', 'visible' => true, 'order' => 3],
                'ews' => ['key' => 'ews', 'visible' => true, 'order' => 4],
                'mrn' => ['key' => 'mrn', 'visible' => true, 'order' => 5],
                'admit_button' => ['key' => 'admit_button', 'visible' => true, 'order' => 6],
            ];
            $defaultPatientInfoConfig = [
                'nursing_level' => ['key' => 'nursing_level', 'visible' => true],
                'diet_type' => ['key' => 'diet_type', 'visible' => true],
                'fall_risk' => ['key' => 'fall_risk', 'visible' => true],
                'isolation_type' => ['key' => 'isolation_type', 'visible' => true],
                'allergies' => ['key' => 'allergies', 'visible' => true],
            ];
            $defaultDashboardDisplay = [
                'patient_name_mask' => 'full',
                'fullscreen_mode' => 'medium',
                'fullscreen_text_size' => 'medium',
            ];
            return view('wards.dashboard', [
                'wards' => $wards,
                'beds' => [],
                'statistics' => [
                    'available' => 0,
                    'cleaning' => 0,
                    'patients' => 0,
                    'consultants' => 0,
                    'anaesthetists' => 0,
                    'nurses' => 0,
                    'ratio' => '0:0',
                    'occupancy' => 0,
                ],
                'selectedWard' => null,
                'consultantPatients' => [],
                'nursePatients' => [],
                'anaesthetistPatients' => [],
                'bedBoxConfig' => $defaultBedBoxConfig,
                'patientInfoConfig' => $defaultPatientInfoConfig,
                'dashboardDisplay' => $defaultDashboardDisplay,
            ]);
        }
        
        // Sync bed status with patient assignments
        $this->syncBedsWithPatients($selectedWardId);
        
        // Get patients for the selected ward
        $wardPatients = Patient::where('ward_id', $selectedWardId)
            ->where('is_active', true)
            ->with(['consultant', 'nurse', 'anaesthetist'])
            ->get();

        // Load movements for these patients (for current location & upcoming schedules)
        $movementsByPatient = PatientMovement::whereIn('patient_id', $wardPatients->pluck('id'))
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->groupBy('patient_id');
        
        // Get all active staff
        $consultants = Consultant::where('is_active', true)->get();
        $nurses = Nurse::where('is_active', true)->get();
        $anaesthetists = Anaesthetist::where('is_active', true)->get();
        
        // Generate bed data for the selected ward
        $beds = $this->generateBedData($selectedWard, $wardPatients, $consultants, $nurses, $movementsByPatient);
        
        // Calculate statistics based on admitted patients only
        $admittedPatients = Patient::where('ward_id', $selectedWardId)
            ->where('is_active', true)
            ->where('status', 'admitted')
            ->get();
        
        // Count unique staff assigned to admitted patients
        $uniqueConsultantIds = collect();
        $uniqueNurseIds = collect();
        $uniqueAnaesthetistIds = collect();
        
        foreach ($admittedPatients as $patient) {
            // Count consultants (including multiple via bed_consultant)
            if ($patient->consultant_id) {
                $uniqueConsultantIds->push($patient->consultant_id);
            }
            
            // Get consultants from bed_consultant pivot table
            $bed = Bed::where('ward_id', $selectedWardId)
                ->where('patient_id', $patient->id)
                ->first();
            
            if ($bed) {
                $bedConsultants = $bed->consultants()->pluck('consultants.id');
                $uniqueConsultantIds = $uniqueConsultantIds->merge($bedConsultants);
            }
            
            // Count nurses
            if ($patient->nurse_id) {
                $uniqueNurseIds->push($patient->nurse_id);
            }
            
            // Count anaesthetists
            if ($patient->anaesthetist_id) {
                $uniqueAnaesthetistIds->push($patient->anaesthetist_id);
            }
        }
        
        $consultantCount = $uniqueConsultantIds->unique()->count();
        $nurseCount = $uniqueNurseIds->unique()->count();
        $anaesthetistCount = $uniqueAnaesthetistIds->unique()->count();
        $patientCount = $admittedPatients->count();
        
        // Calculate nurse:patient ratio
        $ratio = '0:0';
        if ($patientCount > 0) {
            if ($nurseCount > 0) {
                // Calculate ratio as nurse:patient
                $gcd = $this->gcd($nurseCount, $patientCount);
                $ratioNurse = $nurseCount / $gcd;
                $ratioPatient = $patientCount / $gcd;
                $ratio = "$ratioNurse:$ratioPatient";
            } else {
                $ratio = "0:$patientCount";
            }
        }
        
        $statistics = [
            'available' => count(array_filter($beds, fn($bed) => $bed['status'] === 'available')),
            'cleaning' => count(array_filter($beds, fn($bed) => $bed['status'] === 'cleaning')),
            'patients' => $patientCount,
            'consultants' => $consultantCount,
            'anaesthetists' => $anaesthetistCount,
            'nurses' => $nurseCount,
            'ratio' => $ratio,
            'occupancy' => round((count(array_filter($beds, fn($bed) => $bed['status'] === 'occupied')) / max(count($beds), 1)) * 100),
        ];
        
        // Prepare staff-patient groupings for modals
        $consultantPatients = $this->getConsultantPatients($selectedWardId);
        $nursePatients = $this->getNursePatients($selectedWardId);
        $anaesthetistPatients = $this->getAnaesthetistPatients($selectedWardId);
        
        // Get bed box display settings
        $defaultBedBoxDisplay = [
            ['key' => 'patient_name', 'visible' => true, 'order' => 0],
            ['key' => 'consultant', 'visible' => true, 'order' => 1],
            ['key' => 'nurse', 'visible' => true, 'order' => 2],
            ['key' => 'admitted_duration', 'visible' => true, 'order' => 3],
            ['key' => 'ews', 'visible' => true, 'order' => 4],
            ['key' => 'mrn', 'visible' => true, 'order' => 5],
            ['key' => 'admit_button', 'visible' => true, 'order' => 6],
        ];

        $defaultPatientInfoDisplay = [
            ['key' => 'nursing_level', 'visible' => true],
            ['key' => 'diet_type', 'visible' => true],
            ['key' => 'fall_risk', 'visible' => true],
            ['key' => 'isolation_type', 'visible' => true],
            ['key' => 'allergies', 'visible' => true],
        ];
        
        $userSettings = WardDashboardSetting::where('user_id', Auth::id())->first();
        $bedBoxDisplay = $userSettings && is_array($userSettings->bed_box_display) 
            ? $userSettings->bed_box_display 
            : $defaultBedBoxDisplay;
        $patientInfoDisplay = $userSettings && is_array($userSettings->patient_info_display) 
            ? $userSettings->patient_info_display 
            : $defaultPatientInfoDisplay;
        
        // Dashboard display settings (patient name asterisk & fullscreen mode)
        $defaultDashboardDisplay = [
            'patient_name_mask' => 'full', // Options: full, first_only, last_only, initials, first_last_initial, all_asterisk
            'fullscreen_mode' => 'medium', // Options: small (8), medium (6), large (4)
            'fullscreen_text_size' => 'medium', // Options: small, medium, large
        ];
        $dashboardDisplay = $userSettings && is_array($userSettings->dashboard_display) 
            ? array_merge($defaultDashboardDisplay, $userSettings->dashboard_display)
            : $defaultDashboardDisplay;
        
        // Bed box vitals mode (demo/real/off)
        $bedBoxVitalsMode = $userSettings ? ($userSettings->bed_box_vitals_mode ?? 'demo') : 'demo';
        
        // Convert to keyed array for easy access in blade
        $bedBoxConfig = collect($bedBoxDisplay)->keyBy('key')->toArray();
        $patientInfoConfig = collect($patientInfoDisplay)->keyBy('key')->toArray();
        
        // Debug logging
        Log::info('Ward Dashboard Data', [
            'ward_id' => $selectedWardId,
            'consultant_count' => count($consultantPatients),
            'nurse_count' => count($nursePatients),
            'anaesthetist_count' => count($anaesthetistPatients),
            'consultants' => $consultantPatients,
        ]);
        
        return view('wards.dashboard', compact(
            'wards', 
            'beds', 
            'statistics', 
            'selectedWard',
            'consultantPatients',
            'nursePatients',
            'anaesthetistPatients',
            'bedBoxConfig',
            'patientInfoConfig',
            'dashboardDisplay',
            'bedBoxVitalsMode'
        ));
    }
    
    private function syncBedsWithPatients($wardId)
    {
        // Sync bed records in the Bed table with patient assignments
        $beds = Bed::where('ward_id', $wardId)->get();
        
        foreach ($beds as $bed) {
            // Check if bed has a patient assigned through the patient table
            $patient = Patient::where('ward_id', $wardId)
                ->where('bed_number', $bed->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
                ->first();
            
            if ($patient) {
                // Update bed status based on patient status
                // pending_discharge is still considered occupied
                $bedStatus = $patient->status === 'prebook' ? 'reserved' : 'occupied';
                $bed->update([
                    'status' => $bedStatus,
                    'patient_id' => $patient->id,
                ]);
            } else {
                // No patient assigned, mark bed as available
                if ($bed->status !== 'maintenance') {
                    $bed->update([
                        'status' => 'available',
                        'patient_id' => null,
                    ]);
                }
            }
        }
    }
    
    private function generateBedData($ward, $wardPatients, $consultants, $nurses, $movementsByPatient)
    {
        $beds = [];
        // Use actual ward beds (aligns with Beds index)
        $wardBeds = Bed::where('ward_id', $ward->id)
            ->where('is_active', true)
            ->orderBy('bed_number')
            ->get();
        
        // If no beds are defined, fallback to capacity-based placeholders (legacy)
        if ($wardBeds->isEmpty() && $ward->capacity > 0) {
            for ($i = 1; $i <= $ward->capacity; $i++) {
                $wardBeds->push(new Bed([
                    'bed_number' => 'B' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'status' => 'available',
                ]));
            }
        }
        
        // Create a mapping of bed numbers to patients
        $bedPatientMap = [];
        foreach ($wardPatients as $patient) {
            if ($patient->bed_number) {
                $bedPatientMap[$patient->bed_number] = $patient;
            }
        }

        // Ensure shift settings exist for this ward (create defaults if missing)
        $existingShifts = ShiftSetting::where('ward_id', $ward->id)->count();
        if ($existingShifts === 0) {
            $defaults = ShiftSetting::getDefaults();
            foreach ($defaults as $default) {
                ShiftSetting::create(array_merge($default, [
                    'ward_id' => $ward->id,
                    'is_active' => true,
                ]));
            }
        }

        // Get current shift and nurse assignments for today
        $currentShift = ShiftSetting::getCurrentShift($ward->id);
        $currentShiftCode = $currentShift ? $currentShift->shift_code : null;
        $today = now()->toDateString();

        // Get all nurse assignments for this ward, today, and current shift
        $nurseAssignments = [];
        if ($currentShiftCode) {
            $assignments = WardScheduleAssignment::where('ward_id', $ward->id)
                ->where('scheduled_date', $today)
                ->where('shift', $currentShiftCode)
                ->with('nurse')
                ->get();
            
            foreach ($assignments as $assignment) {
                $nurseAssignments[$assignment->bed_id] = [
                    'nurse_id' => $assignment->nurse_id,
                    'nurse_name' => $assignment->nurse ? $assignment->nurse->name : null,
                    'shift' => $assignment->shift,
                ];
            }
        }
        
        // Each "section" groups 9 beds together (Section 1 = beds 1-9, Section 2 = 10-18, etc.)
        foreach ($wardBeds as $index => $wardBed) {
            $section = (int) floor($index / 9) + 1;
            $bedNumber = $wardBed->bed_number;
            if (isset($bedPatientMap[$bedNumber])) {
                $patient = $bedPatientMap[$bedNumber];

                // Get movement info for this patient
                $patientMovements = $movementsByPatient[$patient->id] ?? collect();

                // Current active movement (sent but not yet returned)
                $currentMovement = $patientMovements->first(function ($movement) {
                    return $movement->status === 'sent' && is_null($movement->returned_at);
                });

                // Next scheduled future movement
                $nextScheduledMovement = $patientMovements
                    ->filter(function ($movement) {
                        return $movement->status === 'scheduled'
                            && $movement->scheduled_at
                            && $movement->scheduled_at->greaterThanOrEqualTo(now());
                    })
                    ->sortBy('scheduled_at')
                    ->first();
                
                // Calculate days and hours since admission
                $admittedAt = $patient->admitted_at ?? $patient->booked_at ?? now();
                $diff = now()->diff($admittedAt);
                $days = $diff->days;
                $hours = $diff->h;
                
                // Determine status - pending_discharge patients are still occupying bed
                $status = $patient->status === 'prebook' ? 'reserved' : 'occupied';
                
                // Check if patient is pending discharge
                $isPendingDischarge = $patient->status === 'pending_discharge' || $patient->pending_discharge_at !== null;
                
                // Get latest vital signs and calculate EWS
                $latestVitals = VitalSign::where('patient_id', $patient->id)
                    ->orderBy('recorded_at', 'desc')
                    ->first();
                
                $ewsData = $this->calculateEWS($latestVitals);
                
                // Get nurse on duty from schedule assignment
                $nurseOnDuty = $nurseAssignments[$wardBed->id] ?? null;

                $beds[] = [
                    'number' => $bedNumber,
                    'bed_id' => $wardBed->id,
                    'status' => $status,
                    'patient_status' => $patient->status, // Raw patient status for display
                    'is_pending_discharge' => $isPendingDischarge,
                    'pending_discharge_at' => $patient->pending_discharge_at ? $patient->pending_discharge_at->format('Y-m-d H:i') : null,
                    'section' => $section,
                    'patient_id' => $patient->id,
                    'mrn' => $patient->mrn,
                    'patient_name' => $patient->name,
                    'consultant' => $patient->consultant ? $patient->consultant->name : 'Not Assigned',
                    'nurse' => $patient->nurse ? $patient->nurse->name : 'Not Assigned',
                    'nurse_on_duty' => $nurseOnDuty ? $nurseOnDuty['nurse_name'] : null,
                    'current_shift' => $currentShiftCode,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'days' => $days,
                    'hours' => $hours,
                    'ews' => $ewsData['score'],
                    'ews_has_vitals' => $ewsData['has_vitals'],
                    'booked_datetime' => $patient->booked_at ? $patient->booked_at->format('Y-m-d H:i') : null,
                    'current_movement_id' => $currentMovement ? $currentMovement->id : null,
                    'current_movement_location' => $currentMovement ? $currentMovement->location : null,
                    'current_movement_status' => $currentMovement ? $currentMovement->status : null,
                    'current_movement_sent_at' => $currentMovement && $currentMovement->sent_at
                        ? $currentMovement->sent_at->format('Y-m-d H:i')
                        : null,
                    'is_outside' => $currentMovement !== null,
                    'next_movement_location' => $nextScheduledMovement ? $nextScheduledMovement->location : null,
                    'next_movement_time_display' => $nextScheduledMovement && $nextScheduledMovement->scheduled_at
                        ? $nextScheduledMovement->scheduled_at->format('Y-m-d H:i')
                        : null,
                    'next_movement_time_iso' => $nextScheduledMovement && $nextScheduledMovement->scheduled_at
                        ? $nextScheduledMovement->scheduled_at->toIso8601String()
                        : null,
                    // Clinical indicators
                    'nursing_level' => $patient->nursing_level ?? 'none',
                    'diet_types' => $patient->diet_types ?? [],
                    'diet_types_display' => $patient->diet_types 
                        ? collect($patient->diet_types)->map(fn($dt) => DietType::getDisplayName($dt))->implode(', ')
                        : 'Regular diet',
                    'has_nbm' => $patient->diet_types && collect($patient->diet_types)->map(fn($dt) => strtoupper($dt))->intersect(['NPO', 'NBM', 'NPD'])->isNotEmpty(),
                    'fall_risk' => $patient->fall_risk ?? 'none',
                    'isolation_type' => $patient->isolation_type ?? 'none',
                    'isolation_type_name' => $patient->isolation_type && $patient->isolation_type !== 'none' ? IsolationType::getDisplayName($patient->isolation_type) : 'None',
                    'allergies' => $patient->allergies ?? [],
                ];
            } else {
                // Get nurse on duty from schedule assignment for empty beds too
                $nurseOnDuty = $nurseAssignments[$wardBed->id] ?? null;

                $beds[] = [
                    'number' => $bedNumber,
                    'bed_id' => $wardBed->id,
                    'status' => $wardBed->status ?? 'available',
                    'patient_status' => null,
                    'is_pending_discharge' => false,
                    'pending_discharge_at' => null,
                    'section' => $section,
                    'patient_id' => null,
                    'mrn' => null,
                    'patient_name' => null,
                    'consultant' => null,
                    'nurse' => null,
                    'nurse_on_duty' => $nurseOnDuty ? $nurseOnDuty['nurse_name'] : null,
                    'current_shift' => $currentShiftCode,
                    'gender' => null,
                    'age' => null,
                    'days' => null,
                    'hours' => null,
                    'ews' => null,
                    'ews_has_vitals' => false,
                    'booked_datetime' => null,
                    'current_movement_id' => null,
                    'current_movement_location' => null,
                    'current_movement_status' => null,
                    'current_movement_sent_at' => null,
                    'is_outside' => false,
                    'next_movement_location' => null,
                    'next_movement_time_display' => null,
                    'next_movement_time_iso' => null,
                    // Clinical indicators (null for available beds)
                    'nursing_level' => null,
                    'diet_types' => null,
                    'diet_types_display' => null,
                    'has_nbm' => false,
                    'fall_risk' => null,
                    'isolation_type' => null,
                    'isolation_type_name' => null,
                    'allergies' => null,
                ];
            }
        }
        
        return $beds;
    }
    
    public function admitPatient(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'ward_id' => 'required|exists:wards,id',
            'bed_number' => 'required|string',
            'consultant_id' => 'nullable|exists:consultants,id',
            'anaesthetist_id' => 'nullable|exists:anaesthetists,id',
        ]);
        
        try {
            // Check if bed is already occupied
            $existingPatient = Patient::where('ward_id', $request->ward_id)
                ->where('bed_number', $request->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
                ->first();
                
            if ($existingPatient) {
                Log::warning('Admission failed: Bed already occupied', [
                    'bed_number' => $request->bed_number,
                    'ward_id' => $request->ward_id,
                    'attempted_patient_id' => $request->patient_id,
                    'existing_patient_id' => $existingPatient->id,
                    'user_id' => Auth::id(),
                ]);
                return back()->with('error', 'This bed is already occupied!');
            }
            
            $patient = Patient::findOrFail($request->patient_id);
            $consultant = $request->consultant_id ? Consultant::find($request->consultant_id) : null;
            $anaesthetist = $request->anaesthetist_id ? Anaesthetist::find($request->anaesthetist_id) : null;
            $ward = Ward::findOrFail($request->ward_id);
            
            $admittedAt = now();
            
            // Update patient record (nurse is assigned via ward schedule, not during admission)
            $patient->update([
                'ward_id' => $request->ward_id,
                'bed_number' => $request->bed_number,
                'consultant_id' => $request->consultant_id,
                'anaesthetist_id' => $request->anaesthetist_id,
                'admitted_at' => $admittedAt,
                'status' => 'admitted',
            ]);
            
            // Update bed status in Bed table
            $bedRecord = Bed::where('ward_id', $request->ward_id)
                ->where('bed_number', $request->bed_number)
                ->first();
            if ($bedRecord) {
                $bedRecord->update([
                    'status' => 'occupied',
                    'patient_id' => $patient->id,
                    'anaesthetist_id' => $request->anaesthetist_id,
                ]);
            }
            
            // Create admission log
            AdmissionLog::create([
                'patient_id' => $patient->id,
                'ward_id' => $request->ward_id,
                'user_id' => Auth::id(),
                'bed_number' => $request->bed_number,
                'action' => 'admit',
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'consultant_name' => $consultant ? $consultant->name : null,
                'nurse_name' => null, // Nurse is now assigned via ward schedule
                'gender' => $patient->gender,
                'age' => $patient->age,
                'admitted_at' => $admittedAt,
            ]);
            
            Log::info('Patient admitted successfully', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'ward_id' => $request->ward_id,
                'ward_name' => $ward->ward_name,
                'bed_number' => $request->bed_number,
                'consultant' => $consultant ? $consultant->name : 'None',
                'admitted_at' => $admittedAt,
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('success', 'Patient admitted successfully!');
        } catch (\Exception $e) {
            Log::error('Patient admission failed', [
                'error' => $e->getMessage(),
                'patient_id' => $request->patient_id,
                'ward_id' => $request->ward_id,
                'bed_number' => $request->bed_number,
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('error', 'Failed to admit patient: ' . $e->getMessage());
        }
    }
    
    public function prebookPatient(Request $request)
    {
        $request->validate([
            'patient_id' => 'nullable|exists:patients,id',
            'ward_id' => 'required|exists:wards,id',
            'bed_number' => 'required|string',
            'consultant_id' => 'nullable|exists:consultants,id',
            'anaesthetist_id' => 'nullable|exists:anaesthetists,id',
            'gender' => 'nullable|in:Male,Female',
            'age' => 'nullable|integer|min:0|max:150',
            'notes' => 'nullable|string',
            'booked_at' => 'nullable|date',
        ]);
        
        try {
            // Check if bed is already occupied or prebooked
            $existingPatient = Patient::where('ward_id', $request->ward_id)
                ->where('bed_number', $request->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
                ->first();
                
            if ($existingPatient) {
                Log::warning('Prebook failed: Bed already occupied', [
                    'bed_number' => $request->bed_number,
                    'ward_id' => $request->ward_id,
                    'attempted_patient_id' => $request->patient_id,
                    'existing_patient_id' => $existingPatient->id,
                    'user_id' => Auth::id(),
                ]);
                return back()->with('error', 'This bed is already occupied or prebooked!');
            }
            
            $ward = Ward::findOrFail($request->ward_id);
            $consultant = $request->consultant_id ? Consultant::find($request->consultant_id) : null;
            $anaesthetist = $request->anaesthetist_id ? Anaesthetist::find($request->anaesthetist_id) : null;
            $bookedAt = $request->booked_at ? $request->booked_at : now();
            
            // If patient_id is provided, update existing patient
            if ($request->filled('patient_id')) {
                $patient = Patient::findOrFail($request->patient_id);
                
                // Update patient with optional fields (nurse is assigned via ward schedule)
                $updateData = [
                    'ward_id' => $request->ward_id,
                    'bed_number' => $request->bed_number,
                    'consultant_id' => $request->consultant_id,
                    'anaesthetist_id' => $request->anaesthetist_id,
                    'booked_at' => $bookedAt,
                    'status' => 'prebook',
                ];
                
                // Update patient's gender and age if provided
                if ($request->filled('gender')) {
                    $updateData['gender'] = $request->gender;
                }
                if ($request->filled('age')) {
                    $updateData['age'] = $request->age;
                }
                
                $patient->update($updateData);
            } else {
                // Create a placeholder patient for the prebook
                $patient = Patient::create([
                    'name' => 'Prebooked Bed ' . $request->bed_number,
                    'mrn' => 'PREBOOK-' . $request->bed_number . '-' . time(),
                    'rn' => 'PREBOOK-RN-' . $request->bed_number . '-' . time(),
                    'ic_passport' => 'PREBOOK-IC-' . $request->bed_number . '-' . time(),
                    'ward_id' => $request->ward_id,
                    'bed_number' => $request->bed_number,
                    'consultant_id' => $request->consultant_id,
                    'anaesthetist_id' => $request->anaesthetist_id,
                    'gender' => $request->gender ?? 'Male',
                    'age' => $request->age ?? 0,
                    'phone' => 'N/A',
                    'booked_at' => $bookedAt,
                    'status' => 'prebook',
                    'is_active' => true,
                ]);
            }
            
            // Update bed status in Bed table
            $bedRecord = Bed::where('ward_id', $request->ward_id)
                ->where('bed_number', $request->bed_number)
                ->first();
            if ($bedRecord) {
                $bedRecord->update([
                    'status' => 'reserved',
                    'patient_id' => $patient->id,
                    'anaesthetist_id' => $request->anaesthetist_id,
                ]);
            }
            
            // Create admission log for prebook
            AdmissionLog::create([
                'patient_id' => $patient->id,
                'ward_id' => $request->ward_id,
                'user_id' => Auth::id(),
                'bed_number' => $request->bed_number,
                'action' => 'prebook',
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'consultant_name' => $consultant ? $consultant->name : null,
                'nurse_name' => null, // Nurse is now assigned via ward schedule
                'gender' => $request->gender ?? $patient->gender,
                'age' => $request->age ?? $patient->age,
                'notes' => $request->notes,
                'booked_at' => $bookedAt,
            ]);
            
            Log::info('Patient prebooked successfully', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'ward_id' => $request->ward_id,
                'ward_name' => $ward->ward_name,
                'bed_number' => $request->bed_number,
                'consultant' => $consultant ? $consultant->name : 'None',
                'booked_at' => $bookedAt,
                'notes' => $request->notes ?? 'None',
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('success', 'Bed prebooked successfully!');
        } catch (\Exception $e) {
            Log::error('Patient prebook failed', [
                'error' => $e->getMessage(),
                'patient_id' => $request->patient_id,
                'ward_id' => $request->ward_id,
                'bed_number' => $request->bed_number,
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('error', 'Failed to prebook patient: ' . $e->getMessage());
        }
    }
    
    public function checkInPrebook(Request $request, $patientId)
    {
        try {
            $patient = Patient::findOrFail($patientId);
            
            if ($patient->status !== 'prebook') {
                Log::warning('Check-in failed: Patient not in prebook status', [
                    'patient_id' => $patientId,
                    'current_status' => $patient->status,
                    'user_id' => Auth::id(),
                ]);
                return back()->with('error', 'Patient is not in prebook status!');
            }
            
            $admittedAt = now();
            $consultant = $patient->consultant;
            $nurse = $patient->nurse;
            $ward = $patient->ward;
            
            $patient->update([
                'admitted_at' => $admittedAt,
                'status' => 'admitted',
            ]);
            
            // Update bed status in Bed table
            $bed = Bed::where('ward_id', $patient->ward_id)
                ->where('bed_number', $patient->bed_number)
                ->first();
            if ($bed) {
                $bed->update([
                    'status' => 'occupied',
                    'patient_id' => $patient->id,
                ]);
            }
            
            // Create admission log for check-in
            AdmissionLog::create([
                'patient_id' => $patient->id,
                'ward_id' => $patient->ward_id,
                'user_id' => Auth::id(),
                'bed_number' => $patient->bed_number,
                'action' => 'check-in',
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'consultant_name' => $consultant ? $consultant->name : null,
                'nurse_name' => $nurse ? $nurse->name : null,
                'gender' => $patient->gender,
                'age' => $patient->age,
                'admitted_at' => $admittedAt,
            ]);
            
            Log::info('Prebooked patient checked in successfully', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'ward_id' => $patient->ward_id,
                'ward_name' => $ward ? $ward->ward_name : 'Unknown',
                'bed_number' => $patient->bed_number,
                'admitted_at' => $admittedAt,
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('success', 'Patient checked in successfully!');
        } catch (\Exception $e) {
            Log::error('Check-in failed', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'user_id' => Auth::id(),
            ]);
            
            return back()->with('error', 'Failed to check in patient: ' . $e->getMessage());
        }
    }
    
    public function admissionLogs(Request $request)
    {
        $wardId = $request->input('ward_id');
        $action = $request->input('action');
        $bedNumber = trim((string) $request->input('bed_number', ''));
        $search = trim((string) $request->input('search', ''));
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        
        $query = AdmissionLog::with(['patient', 'ward', 'user'])
            ->orderBy('created_at', 'desc');
        
        if ($wardId) {
            $query->where('ward_id', $wardId);
        }
        
        if ($action) {
            $query->where('action', $action);
        }
        
        if ($bedNumber !== '') {
            $query->where('bed_number', 'like', '%' . $bedNumber . '%');
        }
        
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('mrn', 'like', '%' . $search . '%')
                    ->orWhere('patient_name', 'like', '%' . $search . '%');
            });
        }
        
        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }
        
        $logs = $query->paginate(50)->withQueryString();
        $wards = Ward::where('is_active', true)->get();
        $actions = AdmissionLog::select('action')->distinct()->pluck('action')->filter()->values();
        
        return view('wards.admission-logs', [
            'logs' => $logs,
            'wards' => $wards,
            'wardId' => $wardId,
            'actions' => $actions,
        ]);
    }

    /**
     * Simple patients list view (for iframe) with MRN / name search.
     */
    public function patientsList(Request $request): View
    {
        $wardId = $request->input('ward_id');
        $search = trim((string) $request->input('search', ''));

        $query = Patient::where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->with(['ward', 'consultant', 'nurse'])
            ->orderBy('name');

        if ($wardId) {
            $query->where('ward_id', $wardId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('mrn', 'like', '%' . $search . '%')
                  ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        $patients = $query->paginate(25)->withQueryString();
        $ward = $wardId ? Ward::find($wardId) : null;

        return view('wards.patients-list', [
            'patients' => $patients,
            'ward' => $ward,
            'wardId' => $wardId,
            'search' => $search,
        ]);
    }

    /**
     * Show patient details inside an iframe view with tabbed sections.
     */
    public function patientDetails(Request $request): View
    {
        $patientId = $request->input('patient_id');
        $activeTab = $request->input('active_tab', session()->getOldInput('active_tab', 'info'));

        $patient = null;
        if ($patientId) {
            $patient = Patient::with([
                    'ward',
                    'consultant',
                    'nurse',
                    'anaesthetist',
                    'movements' => function ($query) {
                        $query->orderBy('scheduled_at', 'desc');
                    },
                    'referrals' => function ($query) {
                        $query->with(['consultant', 'anaesthetist'])
                              ->orderBy('created_at', 'desc');
                    },
                ])
                ->where('is_active', true)
                ->find($patientId);
        }

        $consultants = Consultant::where('is_active', true)->orderBy('name')->get();
        $anaesthetists = Anaesthetist::where('is_active', true)->orderBy('name')->get();
        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();

        // Load per-user settings for which tabs are visible in Patient Details
        $defaultTabs = [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'movement' => true,
            'referral' => true,
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
        ];

        $patientDetailsTabs = $defaultTabs;
        $clinicalIndicatorOptions = $this->getDefaultClinicalIndicatorOptions();
        
        // Allow iframe callers to explicitly restrict tabs via ?tabs=info,additional
        if ($request->filled('tabs')) {
            $requested = collect(explode(',', $request->input('tabs')))
                ->map(fn($tab) => trim($tab))
                ->filter()
                ->unique()
                ->values();

            // start with all tabs false, then enable allowed requested keys that exist in defaults
            $patientDetailsTabs = collect($defaultTabs)
                ->map(fn() => false)
                ->toArray();

            foreach ($requested as $tab) {
                if (array_key_exists($tab, $defaultTabs)) {
                    $patientDetailsTabs[$tab] = true;
                }
            }

            // ensure active tab falls back to the first available tab
            if (!$patientDetailsTabs[$activeTab] ?? false) {
                $firstEnabled = collect($patientDetailsTabs)
                    ->filter()
                    ->keys()
                    ->first();
                $activeTab = $firstEnabled ?? $activeTab;
            }
        }

        $patientVitalsMode = 'demo'; // default - follows bed box vitals mode

        if (Auth::check()) {
            $settings = WardDashboardSetting::where('user_id', Auth::id())->first();
            if ($settings) {
                if (is_array($settings->patient_details_tabs)) {
                    $patientDetailsTabs = array_merge($patientDetailsTabs, $settings->patient_details_tabs);
                }
                if (is_array($settings->clinical_indicator_options)) {
                    $clinicalIndicatorOptions = array_merge($clinicalIndicatorOptions, $settings->clinical_indicator_options);
                }
                // Get patient vitals mode from bed box vitals mode setting
                $patientVitalsMode = $settings->bed_box_vitals_mode ?? 'demo';
            }
        }
        
        // Load diet types and isolation types from database and merge into options
        $dbDietTypes = DietType::where('is_active', true)->orderBy('name')->get();
        $dbIsolationTypes = IsolationType::where('is_active', true)->orderBy('name')->get();
        
        if ($dbDietTypes->isNotEmpty()) {
            $clinicalIndicatorOptions['diet_type'] = $dbDietTypes->map(function($dt) {
                return [
                    'value' => $dt->code,
                    'label' => $dt->name,
                    'color' => in_array(strtoupper($dt->code), ['NPO', 'NBM', 'NPD']) 
                        ? 'bg-red-100 text-red-700' 
                        : 'bg-orange-100 text-orange-700',
                ];
            })->toArray();
        }
        
        if ($dbIsolationTypes->isNotEmpty()) {
            $clinicalIndicatorOptions['isolation_type'] = $dbIsolationTypes->map(function($it) {
                return [
                    'value' => $it->code,
                    'label' => $it->name,
                    'color' => in_array(strtoupper($it->code), ['COVID', 'TB', 'AIR', 'AIRBORNE']) 
                        ? 'bg-red-100 text-red-700' 
                        : 'bg-yellow-100 text-yellow-700',
                ];
            })->toArray();
        }

        return view('wards.patient-details', [
            'patient' => $patient,
            'consultants' => $consultants,
            'anaesthetists' => $anaesthetists,
            'wards' => $wards,
            'activeTab' => $activeTab,
            'patientDetailsTabs' => $patientDetailsTabs,
            'clinicalIndicatorOptions' => $clinicalIndicatorOptions,
            'patientVitalsMode' => $patientVitalsMode,
        ]);
    }

    /**
     * Ward dashboard settings (loaded inside iframe from Settings button).
     */
    public function settings(Request $request): View
    {
        $defaultTabs = [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'movement' => true,
            'referral' => true,
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
        ];

        $defaultBedBoxDisplay = [
            ['key' => 'patient_name', 'visible' => true, 'order' => 0],
            ['key' => 'consultant', 'visible' => true, 'order' => 1],
            ['key' => 'nurse', 'visible' => true, 'order' => 2],
            ['key' => 'admitted_duration', 'visible' => true, 'order' => 3],
            ['key' => 'ews', 'visible' => true, 'order' => 4],
            ['key' => 'mrn', 'visible' => true, 'order' => 5],
            ['key' => 'admit_button', 'visible' => true, 'order' => 6],
        ];

        $defaultPatientInfoDisplay = [
            ['key' => 'nursing_level', 'visible' => true],
            ['key' => 'diet_type', 'visible' => true],
            ['key' => 'fall_risk', 'visible' => true],
            ['key' => 'isolation_type', 'visible' => true],
            ['key' => 'allergies', 'visible' => true],
        ];

        $settings = WardDashboardSetting::firstOrCreate(
            ['user_id' => Auth::id()],
            [
                'patient_details_tabs' => $defaultTabs,
                'bed_box_display' => $defaultBedBoxDisplay,
                'patient_info_display' => $defaultPatientInfoDisplay,
            ]
        );

        $tabs = $defaultTabs;
        if (is_array($settings->patient_details_tabs)) {
            $tabs = array_merge($defaultTabs, $settings->patient_details_tabs);
        }

        $bedBoxDisplay = $settings->bed_box_display ?? $defaultBedBoxDisplay;
        $patientInfoDisplay = $settings->patient_info_display ?? $defaultPatientInfoDisplay;
        
        // Dashboard display settings (patient name masking & fullscreen mode)
        $defaultDashboardDisplay = [
            'patient_name_mask' => 'full',
            'fullscreen_mode' => 'medium',
            'fullscreen_text_size' => 'medium',
        ];
        $dashboardDisplay = $settings->dashboard_display ?? $defaultDashboardDisplay;
        $dashboardDisplay = array_merge($defaultDashboardDisplay, $dashboardDisplay);
        
        // Clinical settings (EWS system, etc.)
        $defaultClinicalSettings = [
            'ews_system' => 'news2',
        ];
        $clinicalSettings = $settings->clinical_settings ?? $defaultClinicalSettings;
        $clinicalSettings = array_merge($defaultClinicalSettings, is_array($clinicalSettings) ? $clinicalSettings : []);
        
        // Get clinical indicator options (with defaults)
        $defaultClinicalOptions = $this->getDefaultClinicalIndicatorOptions();
        $clinicalIndicatorOptions = $settings->clinical_indicator_options ?? $defaultClinicalOptions;
        
        // Merge with defaults to ensure all keys exist
        $clinicalIndicatorOptions = array_merge($defaultClinicalOptions, $clinicalIndicatorOptions);
        
        // Load diet types and isolation types from database and merge into options
        $dbDietTypes = DietType::where('is_active', true)->orderBy('name')->get();
        $dbIsolationTypes = IsolationType::where('is_active', true)->orderBy('name')->get();
        
        if ($dbDietTypes->isNotEmpty()) {
            $clinicalIndicatorOptions['diet_type'] = $dbDietTypes->map(function($dt) {
                return [
                    'value' => $dt->code,
                    'label' => $dt->name,
                    'color' => in_array(strtoupper($dt->code), ['NPO', 'NBM', 'NPD']) 
                        ? 'bg-red-100 text-red-700' 
                        : 'bg-orange-100 text-orange-700',
                ];
            })->toArray();
        }
        
        if ($dbIsolationTypes->isNotEmpty()) {
            $clinicalIndicatorOptions['isolation_type'] = $dbIsolationTypes->map(function($it) {
                return [
                    'value' => $it->code,
                    'label' => $it->name,
                    'color' => in_array(strtoupper($it->code), ['COVID', 'TB', 'AIR', 'AIRBORNE']) 
                        ? 'bg-red-100 text-red-700' 
                        : 'bg-yellow-100 text-yellow-700',
                ];
            })->toArray();
        }

        // Vitals data mode settings (demo/real/off)
        $patientVitalsMode = $settings->patient_vitals_mode ?? 'demo';
        $bedBoxVitalsMode = $settings->bed_box_vitals_mode ?? 'demo';

        return view('wards.settings', [
            'tabs' => $tabs,
            'bedBoxDisplay' => $bedBoxDisplay,
            'patientInfoDisplay' => $patientInfoDisplay,
            'clinicalIndicatorOptions' => $clinicalIndicatorOptions,
            'dashboardDisplay' => $dashboardDisplay,
            'clinicalSettings' => $clinicalSettings,
            'patientVitalsMode' => $patientVitalsMode,
            'bedBoxVitalsMode' => $bedBoxVitalsMode,
        ]);
    }

    /**
     * Persist ward dashboard settings to database.
     */
    public function updateSettings(Request $request)
    {
        $settingType = $request->input('setting_type', 'patient_details');

        $settings = WardDashboardSetting::firstOrCreate(
            ['user_id' => Auth::id()]
        );

        if ($settingType === 'bed_box_display') {
            // Handle bed box display settings
            $bedBoxOrder = $request->input('bed_box_order');
            $bedBoxDisplay = json_decode($bedBoxOrder, true);
            
            if (is_array($bedBoxDisplay)) {
                $settings->bed_box_display = $bedBoxDisplay;
                $settings->save();
            }

            return back()->with('success', 'Bed box display settings updated successfully.');
        }

        if ($settingType === 'patient_info_display') {
            // Handle patient info display settings
            $patientInfoConfig = $request->input('patient_info_config');
            $patientInfoDisplay = json_decode($patientInfoConfig, true);
            
            if (is_array($patientInfoDisplay)) {
                $settings->patient_info_display = $patientInfoDisplay;
                $settings->save();
            }

            return back()->with('success', 'Patient info display settings updated successfully.');
        }

        if ($settingType === 'dashboard_display') {
            // Handle dashboard display settings (patient name mask & fullscreen mode)
            $dashboardConfig = $request->input('dashboard_display_config');
            $dashboardDisplay = json_decode($dashboardConfig, true);
            
            if (is_array($dashboardDisplay)) {
                $settings->dashboard_display = $dashboardDisplay;
                $settings->save();
            }

            return back()->with('success', 'Dashboard display settings updated successfully.');
        }

        if ($settingType === 'clinical_setting') {
            // Handle clinical settings (EWS system, etc.)
            $clinicalConfig = $request->input('clinical_setting_config');
            $clinicalSettings = json_decode($clinicalConfig, true);
            
            if (is_array($clinicalSettings)) {
                $settings->clinical_settings = $clinicalSettings;
                $settings->save();
            }

            return back()->with('success', 'Clinical settings updated successfully.');
        }

        if ($settingType === 'patient_vitals_mode') {
            // Handle patient vitals mode (demo/real/off)
            $mode = $request->input('patient_vitals_mode', 'demo');
            if (in_array($mode, ['demo', 'real', 'off'])) {
                $settings->patient_vitals_mode = $mode;
                $settings->save();
            }

            return back()->with('success', 'Patient vitals mode updated successfully.');
        }

        if ($settingType === 'bed_box_vitals_mode') {
            // Handle bed box vitals mode (demo/real/off)
            $mode = $request->input('bed_box_vitals_mode', 'demo');
            if (in_array($mode, ['demo', 'real', 'off'])) {
                $settings->bed_box_vitals_mode = $mode;
                $settings->save();
            }

            return back()->with('success', 'Bed box vitals mode updated successfully.');
        }

        // Handle patient details tabs settings (default)
        $defaultTabs = [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'movement' => true,
            'referral' => true,
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
        ];

        $inputTabs = $request->input('tabs', []);

        // Build boolean map: checked => true, unchecked => false
        $tabs = [];
        foreach ($defaultTabs as $key => $default) {
            $tabs[$key] = array_key_exists($key, $inputTabs);
        }

        $settings->patient_details_tabs = $tabs;
        $settings->save();

        return back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Store a new patient movement (scheduled procedure outside the ward).
     */
    public function storeMovement(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'location' => 'required|string|max:255',
            'location_type' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'scheduled_at' => 'required|date',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);

        $movement = PatientMovement::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'bed_number' => $patient->bed_number,
            'location' => $request->location,
            'location_type' => $request->location_type,
            'notes' => $request->notes,
            'scheduled_at' => $request->scheduled_at,
            'status' => 'scheduled',
        ]);

        Log::info('Patient movement scheduled', [
            'movement_id' => $movement->id,
            'patient_id' => $patient->id,
            'location' => $movement->location,
            'scheduled_at' => $movement->scheduled_at,
        ]);
        
        return redirect()->route('ward.patient-details', [
                'patient_id' => $patient->id,
                'active_tab' => 'movement',
            ])
            ->with('success', 'Patient movement scheduled successfully.');
    }

    /**
     * Mark a scheduled movement as "sent" (patient has left the ward).
     */
    public function sendMovement(Request $request, PatientMovement $movement)
    {
        if ($movement->status !== 'scheduled') {
            return redirect()->route('ward.patient-details', [
                    'patient_id' => $movement->patient_id,
                    'active_tab' => 'movement',
                ])
                ->with('error', 'Only scheduled movements can be sent.');
        }

        $movement->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Log::info('Patient movement sent', [
            'movement_id' => $movement->id,
            'patient_id' => $movement->patient_id,
            'location' => $movement->location,
        ]);
        
        return redirect()->route('ward.patient-details', [
                'patient_id' => $movement->patient_id,
                'active_tab' => 'movement',
            ])
            ->with('success', 'Patient marked as sent to ' . $movement->location . '.');
    }

    /**
     * Mark a sent movement as "returned" (patient back to ward).
     */
    public function returnMovement(Request $request, PatientMovement $movement)
    {
        // Get patient name for notification
        $patient = $movement->patient;
        $patientName = $patient ? $patient->name : 'Patient';
        
        if ($movement->status !== 'sent') {
            // Check if request came from dashboard
            if ($request->has('from_dashboard')) {
                return redirect()->route('ward.dashboard', ['ward_id' => $request->input('ward_id')])
                    ->with('error', 'Only sent movements can be marked as returned.');
            }
            return redirect()->route('ward.patient-details', [
                    'patient_id' => $movement->patient_id,
                    'active_tab' => 'movement',
                ])
                ->with('error', 'Only sent movements can be marked as returned.');
        }

        $movement->update([
            'status' => 'returned',
            'returned_at' => now(),
        ]);

        Log::info('Patient movement returned', [
            'movement_id' => $movement->id,
            'patient_id' => $movement->patient_id,
            'location' => $movement->location,
        ]);
        
        // Check if request came from dashboard - redirect back to dashboard with notification
        if ($request->has('from_dashboard')) {
            return redirect()->route('ward.dashboard', ['ward_id' => $request->input('ward_id')])
                ->with('success', $patientName . ' has been marked as returned from ' . $movement->location . '.');
        }
        
        return redirect()->route('ward.patient-details', [
                'patient_id' => $movement->patient_id,
                'active_tab' => 'movement',
            ])
            ->with('success', 'Patient marked as returned from ' . $movement->location . '.');
    }

    /**
     * Store a referral to an additional consultant or anaesthetist for the patient.
     */
    public function storeReferral(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'referral_type' => 'required|in:consultant,anaesthetist',
            'consultant_id' => 'nullable|required_if:referral_type,consultant|exists:consultants,id',
            'anaesthetist_id' => 'nullable|required_if:referral_type,anaesthetist|exists:anaesthetists,id',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);

        // Prevent referral to primary consultant and duplicate consultant referrals
        if ($request->referral_type === 'consultant') {
            if ($patient->consultant_id && (int) $request->consultant_id === (int) $patient->consultant_id) {
                return redirect()->route('ward.patient-details', [
                        'patient_id' => $patient->id,
                        'active_tab' => 'referral',
                    ])
                    ->with('error', 'Cannot create referral to the primary consultant assigned at admission.');
            }

            $existingConsultantReferral = PatientReferral::where('patient_id', $patient->id)
                ->where('referral_type', 'consultant')
                ->where('consultant_id', $request->consultant_id)
                ->exists();

            if ($existingConsultantReferral) {
                return redirect()->route('ward.patient-details', [
                        'patient_id' => $patient->id,
                        'active_tab' => 'referral',
                    ])
                    ->with('error', 'This consultant already has a referral for this patient.');
            }
        }

        // Prevent duplicate anaesthetist referrals (same anaesthetist referred more than once)
        if ($request->referral_type === 'anaesthetist') {
            $existingAnaesthetistReferral = PatientReferral::where('patient_id', $patient->id)
                ->where('referral_type', 'anaesthetist')
                ->where('anaesthetist_id', $request->anaesthetist_id)
                ->exists();

            if ($existingAnaesthetistReferral) {
                return redirect()->route('ward.patient-details', [
                        'patient_id' => $patient->id,
                        'active_tab' => 'referral',
                    ])
                    ->with('error', 'This anaesthetist already has a referral for this patient.');
            }
        }

        $referral = PatientReferral::create([
            'patient_id' => $patient->id,
            'referral_type' => $request->referral_type,
            'consultant_id' => $request->referral_type === 'consultant' ? $request->consultant_id : null,
            'anaesthetist_id' => $request->referral_type === 'anaesthetist' ? $request->anaesthetist_id : null,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'status' => 'active',
            'created_by' => Auth::id(),
        ]);

        Log::info('Patient referral created', [
            'referral_id' => $referral->id,
            'patient_id' => $patient->id,
            'referral_type' => $referral->referral_type,
            'consultant_id' => $referral->consultant_id,
            'anaesthetist_id' => $referral->anaesthetist_id,
        ]);
        
        return redirect()->route('ward.patient-details', [
                'patient_id' => $patient->id,
                'active_tab' => 'referral',
            ])
            ->with('success', 'Referral added successfully.');
    }

    /**
     * Transfer a patient to a different bed (and optionally ward).
     */
    public function transferBed(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'ward_id' => 'required|exists:wards,id',
            'bed_number' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);

        if ($patient->status !== 'admitted') {
            return redirect()->route('ward.patient-details', [
                    'patient_id' => $patient->id,
                    'active_tab' => 'transfer',
                ])
                ->with('error', 'Only admitted patients can be transferred.');
        }

        // Prevent transferring into an occupied/prebooked bed
        $existingPatient = Patient::where('ward_id', $request->ward_id)
            ->where('bed_number', $request->bed_number)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->first();

        if ($existingPatient) {
            return redirect()->route('ward.patient-details', [
                    'patient_id' => $patient->id,
                    'active_tab' => 'transfer',
                ])
                ->with('error', 'The target bed is already occupied or prebooked.');
        }

        $targetWard = Ward::findOrFail($request->ward_id);
        $newBed = Bed::where('ward_id', $request->ward_id)
            ->where('bed_number', $request->bed_number)
            ->first();

        if (!$newBed) {
            return redirect()->route('ward.patient-details', [
                    'patient_id' => $patient->id,
                    'active_tab' => 'transfer',
                ])
                ->with('error', 'The selected bed does not exist in the chosen ward.');
        }

        $oldWardId = $patient->ward_id;
        $oldBedNumber = $patient->bed_number;

        // Update patient to new ward/bed
        $patient->update([
            'ward_id' => $request->ward_id,
            'bed_number' => $request->bed_number,
        ]);

        // Free old bed
        if ($oldWardId && $oldBedNumber) {
            $oldBed = Bed::where('ward_id', $oldWardId)
                ->where('bed_number', $oldBedNumber)
                ->first();

            if ($oldBed) {
                $update = ['patient_id' => null];
                if ($oldBed->status !== 'maintenance') {
                    $update['status'] = 'available';
                }
                $oldBed->update($update);
            }
        }

        // Occupy new bed
        $newBed->update([
            'status' => 'occupied',
            'patient_id' => $patient->id,
        ]);

        // Log transfer in admission logs
        $consultant = $patient->consultant;
        $nurse = $patient->nurse;

        $notes = trim('Transfer from ' . ($oldBedNumber ?: 'N/A') . ' to ' . $request->bed_number .
            ($request->reason ? ('. Reason: ' . $request->reason) : ''));

        AdmissionLog::create([
            'patient_id' => $patient->id,
            'ward_id' => $request->ward_id,
            'user_id' => Auth::id(),
            'bed_number' => $request->bed_number,
            'action' => 'transfer',
            'patient_name' => $patient->name,
            'mrn' => $patient->mrn,
            'consultant_name' => $consultant ? $consultant->name : null,
            'nurse_name' => $nurse ? $nurse->name : null,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'notes' => $notes,
        ]);

        Log::info('Patient bed transferred', [
            'patient_id' => $patient->id,
            'from_ward_id' => $oldWardId,
            'from_bed' => $oldBedNumber,
            'to_ward_id' => $request->ward_id,
            'to_bed' => $request->bed_number,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('ward.patient-details', [
                'patient_id' => $patient->id,
                'active_tab' => 'transfer',
            ])
            ->with('success', 'Patient successfully transferred to ' . $targetWard->ward_name . ' bed ' . $request->bed_number . '.');
    }

    /**
     * Discharge patient from the ward.
     */
    public function dischargePatient(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'discharge_reason' => 'nullable|string',
            'discharge_notes' => 'nullable|string',
            'discharged_at' => 'nullable|date',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);

        if ($patient->status !== 'admitted') {
            return redirect()->route('ward.patient-details', [
                    'patient_id' => $patient->id,
                    'active_tab' => 'discharge',
                ])
                ->with('error', 'Only admitted patients can be discharged from the ward.');
        }

        $dischargedAt = $request->discharged_at ? now()->parse($request->discharged_at) : now();

        $wardId = $patient->ward_id;
        $bedNumber = $patient->bed_number;
        $consultant = $patient->consultant;
        $nurse = $patient->nurse;
        $ward = $patient->ward;

        // Update patient record
        $patient->update([
            'status' => 'discharged',
            'ward_id' => null,
            'bed_number' => null,
        ]);

        // Free bed
        if ($wardId && $bedNumber) {
            $bed = Bed::where('ward_id', $wardId)
                ->where('bed_number', $bedNumber)
                ->first();

            if ($bed) {
                $update = ['patient_id' => null];
                if ($bed->status !== 'maintenance') {
                    $update['status'] = 'available';
                }
                $bed->update($update);
            }
        }

        // Log discharge
        $notesParts = [];
        if ($request->discharge_reason) {
            $notesParts[] = 'Reason: ' . $request->discharge_reason;
        }
        if ($request->discharge_notes) {
            $notesParts[] = $request->discharge_notes;
        }

        AdmissionLog::create([
            'patient_id' => $patient->id,
            'ward_id' => $wardId,
            'user_id' => Auth::id(),
            'bed_number' => $bedNumber,
            'action' => 'discharge',
            'patient_name' => $patient->name,
            'mrn' => $patient->mrn,
            'consultant_name' => $consultant ? $consultant->name : null,
            'nurse_name' => $nurse ? $nurse->name : null,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'notes' => implode(' | ', $notesParts),
        ]);

        Log::info('Patient discharged from ward', [
            'patient_id' => $patient->id,
            'ward_id' => $wardId,
            'ward_name' => $ward ? $ward->ward_name : null,
            'bed_number' => $bedNumber,
            'discharged_at' => $dischargedAt,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('ward.patient-details', [
                'patient_id' => $patient->id,
                'active_tab' => 'discharge',
            ])
            ->with('success', 'Patient discharged from ward ' . ($ward ? $ward->ward_name : '') . '.');
    }
    
    /**
     * Get default clinical indicator options
     */
    private function getDefaultClinicalIndicatorOptions(): array
    {
        return [
            'nursing_level' => [
                ['value' => 'none', 'label' => 'None', 'color' => 'bg-gray-100 text-gray-600'],
                ['value' => 'level_1', 'label' => 'Level 1', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'level_2', 'label' => 'Level 2', 'color' => 'bg-blue-100 text-blue-700'],
                ['value' => 'level_3', 'label' => 'Level 3', 'color' => 'bg-yellow-100 text-yellow-700'],
                ['value' => 'level_4', 'label' => 'Level 4', 'color' => 'bg-red-100 text-red-700'],
            ],
            'diet_type' => [
                ['value' => 'npo', 'label' => 'NPO (Nil By Mouth)', 'color' => 'bg-red-100 text-red-700'],
                ['value' => 'clear_fluid', 'label' => 'Clear Fluid', 'color' => 'bg-blue-100 text-blue-700'],
                ['value' => 'full_fluid', 'label' => 'Full Fluid', 'color' => 'bg-cyan-100 text-cyan-700'],
                ['value' => 'soft_diet', 'label' => 'Soft Diet', 'color' => 'bg-orange-100 text-orange-700'],
                ['value' => 'regular', 'label' => 'Regular', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'vegetarian', 'label' => 'Vegetarian', 'color' => 'bg-lime-100 text-lime-700'],
                ['value' => 'diabetic', 'label' => 'Diabetic', 'color' => 'bg-purple-100 text-purple-700'],
                ['value' => 'renal', 'label' => 'Renal', 'color' => 'bg-pink-100 text-pink-700'],
                ['value' => 'low_salt', 'label' => 'Low Salt', 'color' => 'bg-amber-100 text-amber-700'],
                ['value' => 'halal', 'label' => 'Halal', 'color' => 'bg-emerald-100 text-emerald-700'],
                ['value' => 'kosher', 'label' => 'Kosher', 'color' => 'bg-indigo-100 text-indigo-700'],
                ['value' => 'gluten_free', 'label' => 'Gluten Free', 'color' => 'bg-rose-100 text-rose-700'],
            ],
            'fall_risk' => [
                ['value' => 'none', 'label' => 'None', 'color' => 'bg-gray-100 text-gray-600'],
                ['value' => 'low', 'label' => 'Low', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'moderate', 'label' => 'Moderate', 'color' => 'bg-yellow-100 text-yellow-700'],
                ['value' => 'high', 'label' => 'High', 'color' => 'bg-orange-100 text-orange-700'],
                ['value' => 'alert_active', 'label' => 'FR Alert Active', 'color' => 'bg-red-100 text-red-700'],
            ],
            'isolation_type' => [
                ['value' => 'none', 'label' => 'None', 'color' => 'bg-gray-100 text-gray-600'],
                ['value' => 'contact', 'label' => 'Contact', 'color' => 'bg-blue-100 text-blue-700'],
                ['value' => 'droplet', 'label' => 'Droplet', 'color' => 'bg-cyan-100 text-cyan-700'],
                ['value' => 'airborne', 'label' => 'Airborne', 'color' => 'bg-purple-100 text-purple-700'],
                ['value' => 'protective', 'label' => 'Protective', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'mrsa', 'label' => 'MRSA', 'color' => 'bg-orange-100 text-orange-700'],
                ['value' => 'vre', 'label' => 'VRE', 'color' => 'bg-pink-100 text-pink-700'],
                ['value' => 'cdiff', 'label' => 'C.Diff', 'color' => 'bg-amber-100 text-amber-700'],
                ['value' => 'covid', 'label' => 'COVID-19', 'color' => 'bg-red-100 text-red-700'],
                ['value' => 'tb', 'label' => 'TB', 'color' => 'bg-rose-100 text-rose-700'],
            ],
        ];
    }

    /**
     * Update patient clinical indicators (from Patient Details > Additional Info tab)
     */
    public function updatePatientClinical(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'nursing_level' => 'nullable|string',
            'diet_types' => 'nullable|array',
            'diet_types.*' => 'nullable|string',
            'fall_risk' => 'nullable|string',
            'isolation_type' => 'nullable|string',
            'allergies' => 'nullable|array',
            'new_allergy' => 'nullable|string|max:100',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);

        // Handle allergies
        $allergies = $request->input('allergies', []);
        if ($request->filled('new_allergy')) {
            $newAllergy = trim($request->new_allergy);
            if (!empty($newAllergy) && !in_array($newAllergy, $allergies)) {
                $allergies[] = $newAllergy;
            }
        }

        // Handle diet_types array
        $dietTypes = $request->input('diet_types', []);

        $patient->update([
            'nursing_level' => $request->nursing_level,
            'diet_types' => !empty($dietTypes) ? $dietTypes : null,
            'fall_risk' => $request->fall_risk,
            'isolation_type' => $request->isolation_type,
            'allergies' => !empty($allergies) ? $allergies : null,
        ]);

        Log::info('Patient clinical indicators updated', [
            'patient_id' => $patient->id,
            'nursing_level' => $patient->nursing_level,
            'diet_types' => $patient->diet_types,
            'fall_risk' => $patient->fall_risk,
            'isolation_type' => $patient->isolation_type,
            'allergies' => $patient->allergies,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('ward.patient-details', [
                'patient_id' => $patient->id,
                'active_tab' => 'additional',
            ])
            ->with('success', 'Clinical indicators updated successfully.');
    }

    /**
     * Update clinical indicator options (from Settings > Patient Info tab)
     */
    public function updateClinicalIndicatorOptions(Request $request)
    {
        $settings = WardDashboardSetting::firstOrCreate(
            ['user_id' => Auth::id()]
        );

        $clinicalOptions = $request->input('clinical_options');
        $options = json_decode($clinicalOptions, true);

        if (is_array($options)) {
            $settings->clinical_indicator_options = $options;
            $settings->save();
        }

        return back()->with('success', 'Clinical indicator options updated successfully.');
    }

    /**
     * Calculate Greatest Common Divisor (GCD) for ratio calculation
     */
    private function gcd($a, $b)
    {
        while ($b != 0) {
            $temp = $b;
            $b = $a % $b;
            $a = $temp;
        }
        return $a;
    }
    
    /**
     * Get patients grouped by consultant
     */
    private function getConsultantPatients($wardId)
    {
        $consultants = [];
        
        // Consider both currently admitted and prebooked patients in this ward
        $patients = Patient::where('ward_id', $wardId)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->with('consultant')
            ->get();
        
        Log::info('getConsultantPatients Debug', [
            'ward_id' => $wardId,
            'patient_count' => $patients->count(),
            'patients' => $patients->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'consultant_id' => $p->consultant_id,
                    'has_consultant_relation' => $p->consultant ? true : false,
                    'consultant_name' => $p->consultant ? $p->consultant->name : null,
                ];
            })->toArray()
        ]);
        
        foreach ($patients as $patient) {
            // Add primary consultant
            if ($patient->consultant_id && $patient->consultant) {
                $consultantId = $patient->consultant_id;
                $consultantName = $patient->consultant->name;
                
                if (!isset($consultants[$consultantId])) {
                    $consultants[$consultantId] = [
                        'id' => $consultantId,
                        'name' => $consultantName,
                        'patients' => []
                    ];
                }
                
                // Check if patient already added to avoid duplicates
                $patientExists = false;
                foreach ($consultants[$consultantId]['patients'] as $p) {
                    if ($p['id'] === $patient->id) {
                        $patientExists = true;
                        break;
                    }
                }
                
                if (!$patientExists) {
                    $consultants[$consultantId]['patients'][] = [
                        'id' => $patient->id,
                        'name' => $patient->name,
                        'mrn' => $patient->mrn,
                        'bed_number' => $patient->bed_number,
                    ];
                }
            }
            
            // Add consultants from bed_consultant pivot
            $bed = Bed::where('ward_id', $wardId)
                ->where('patient_id', $patient->id)
                ->with('consultants')
                ->first();
            
            if ($bed && $bed->consultants) {
                $bedConsultants = $bed->consultants;
                foreach ($bedConsultants as $consultant) {
                    if (!isset($consultants[$consultant->id])) {
                        $consultants[$consultant->id] = [
                            'id' => $consultant->id,
                            'name' => $consultant->name,
                            'patients' => []
                        ];
                    }
                    
                    // Check if patient already added
                    $patientExists = false;
                    foreach ($consultants[$consultant->id]['patients'] as $p) {
                        if ($p['id'] === $patient->id) {
                            $patientExists = true;
                            break;
                        }
                    }
                    
                    if (!$patientExists) {
                        $consultants[$consultant->id]['patients'][] = [
                            'id' => $patient->id,
                            'name' => $patient->name,
                            'mrn' => $patient->mrn,
                            'bed_number' => $patient->bed_number,
                        ];
                    }
                }
            }
        }
        
        return array_values($consultants);
    }
    
    /**
     * Get patients grouped by nurse
     */
    private function getNursePatients($wardId)
    {
        $nurses = [];
        
        // Include both admitted and prebooked patients that have a nurse assigned
        $patients = Patient::where('ward_id', $wardId)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->whereNotNull('nurse_id')
            ->with('nurse')
            ->get();
        
        foreach ($patients as $patient) {
            if ($patient->nurse_id && $patient->nurse) {
                $nurseId = $patient->nurse_id;
                $nurseName = $patient->nurse->name;
                
                if (!isset($nurses[$nurseId])) {
                    $nurses[$nurseId] = [
                        'id' => $nurseId,
                        'name' => $nurseName,
                        'patients' => []
                    ];
                }
                
                $nurses[$nurseId]['patients'][] = [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'mrn' => $patient->mrn,
                    'bed_number' => $patient->bed_number,
                ];
            }
        }
        
        return array_values($nurses);
    }
    
    /**
     * Get patients grouped by anaesthetist
     */
    private function getAnaesthetistPatients($wardId)
    {
        $anaesthetists = [];
        
        // Include both admitted and prebooked patients that have an anaesthetist assigned
        $patients = Patient::where('ward_id', $wardId)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->whereNotNull('anaesthetist_id')
            ->with('anaesthetist')
            ->get();
        
        foreach ($patients as $patient) {
            if ($patient->anaesthetist_id && $patient->anaesthetist) {
                $anaesthetistId = $patient->anaesthetist_id;
                $anaesthetistName = $patient->anaesthetist->name;
                
                if (!isset($anaesthetists[$anaesthetistId])) {
                    $anaesthetists[$anaesthetistId] = [
                        'id' => $anaesthetistId,
                        'name' => $anaesthetistName,
                        'patients' => []
                    ];
                }
                
                $anaesthetists[$anaesthetistId]['patients'][] = [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'mrn' => $patient->mrn,
                    'bed_number' => $patient->bed_number,
                ];
            }
        }
        
        return array_values($anaesthetists);
    }

    /**
     * Calculate Early Warning Score (EWS) based on vital signs
     * Using NEWS2 (National Early Warning Score 2) scoring system
     */
    private function calculateEWS(?VitalSign $vitals): array
    {
        if (!$vitals) {
            return [
                'score' => null,
                'has_vitals' => false,
            ];
        }

        $score = 0;

        // Respiratory Rate scoring (breaths per minute)
        if ($vitals->respiratory_rate !== null) {
            $rr = $vitals->respiratory_rate;
            if ($rr <= 8) $score += 3;
            elseif ($rr <= 11) $score += 1;
            elseif ($rr <= 20) $score += 0;
            elseif ($rr <= 24) $score += 2;
            else $score += 3;
        }

        // SpO2 scoring (oxygen saturation %)
        if ($vitals->spo2 !== null) {
            $spo2 = $vitals->spo2;
            if ($spo2 <= 91) $score += 3;
            elseif ($spo2 <= 93) $score += 2;
            elseif ($spo2 <= 95) $score += 1;
            else $score += 0;
        }

        // Systolic Blood Pressure scoring (mmHg)
        if ($vitals->systolic_bp !== null) {
            $sbp = $vitals->systolic_bp;
            if ($sbp <= 90) $score += 3;
            elseif ($sbp <= 100) $score += 2;
            elseif ($sbp <= 110) $score += 1;
            elseif ($sbp <= 219) $score += 0;
            else $score += 3;
        }

        // Pulse Rate scoring (beats per minute)
        if ($vitals->pulse_rate !== null) {
            $hr = $vitals->pulse_rate;
            if ($hr <= 40) $score += 3;
            elseif ($hr <= 50) $score += 1;
            elseif ($hr <= 90) $score += 0;
            elseif ($hr <= 110) $score += 1;
            elseif ($hr <= 130) $score += 2;
            else $score += 3;
        }

        // Temperature scoring (°C)
        if ($vitals->temperature !== null) {
            $temp = (float) $vitals->temperature;
            if ($temp <= 35.0) $score += 3;
            elseif ($temp <= 36.0) $score += 1;
            elseif ($temp <= 38.0) $score += 0;
            elseif ($temp <= 39.0) $score += 1;
            else $score += 2;
        }

        return [
            'score' => $score,
            'has_vitals' => true,
        ];
    }
}

