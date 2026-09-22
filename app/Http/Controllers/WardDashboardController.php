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
use App\Models\BloodTransfusion;
use App\Models\Medication;
use App\Models\PatientMedication;
use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Support\ClinicalIndicatorLibrary;
use App\Support\ClinicalIndicatorMonitoring;
use App\Support\FluidBalanceChart;
use App\Models\PatientCareProvider;
use App\Models\Infusion;
use App\Models\WardNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use App\Services\EkadService;
use App\Services\PatientInfoSources;
use App\Models\WardSpecialDuty;

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

        // Get user settings early
        $userSettings = WardDashboardSetting::where('user_id', Auth::id())->first();
        $settings = $this->resolveDashboardSettings($userSettings);

        // If no ward exists, return empty view
        if (!$selectedWard || !$selectedWardId) {
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
                    'infusions' => 0,
                    'infusion_warnings' => 0,
                    'infusion_alarms' => 0,
                ],
                'selectedWard' => null,
                'consultantPatients' => [],
                'nursePatients' => [],
                'anaesthetistPatients' => [],
                'bedBoxConfig' => $settings['bedBoxConfig'],
                'patientInfoConfig' => $settings['patientInfoConfig'],
                'dashboardDisplay' => $settings['dashboardDisplay'],
                'bedBoxVitalsMode' => $userSettings ? ($userSettings->bed_box_vitals_mode ?? 'demo') : 'demo',
                'notificationCount' => 0,
                'currentShift' => null,
            ]);
        }

        // Sync bed status with patient assignments
        $this->syncBedsWithPatients($selectedWardId);

        // Get patients for the selected ward
        $wardPatients = Patient::where('ward_id', $selectedWardId)
            ->where('is_active', true)
            ->with(['consultant', 'nurse', 'anaesthetist', 'activeCareProviders.consultant', 'activeCareProviders.anaesthetist'])
            ->get();

        // Load movements
        $movementsByPatient = PatientMovement::whereIn('patient_id', $wardPatients->pluck('id'))
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->groupBy('patient_id');

        // Get active staff buckets for fallback queries
        $consultants = Consultant::where('is_active', true)->get();
        $nurses = Nurse::where('is_active', true)->get();

        // Get clinical settings for EWS system
        $defaultClinicalSettings = ['ews_system' => 'ews_ihh'];
        $clinicalSettings = $userSettings && is_array($userSettings->clinical_settings)
            ? array_merge($defaultClinicalSettings, $userSettings->clinical_settings)
            : $defaultClinicalSettings;
        $ewsSystem = $clinicalSettings['ews_system'] ?? 'ews_ihh';

        // Generate bed data
        $beds = $this->generateBedData($selectedWard, $wardPatients, $consultants, $nurses, $movementsByPatient, $ewsSystem);

        // Medication monitoring: overdue and due-soon doses per patient, for users who have it switched on
        $medicationMonitoring = PatientMedication::monitoringEnabledFor($userSettings);
        $medicationAlerts = $medicationMonitoring
            ? PatientMedication::alertsForPatients($wardPatients->pluck('id'))
            : [];

        // Clinical indicator reassessments past their suggested interval or warning level, per patient
        $assessmentAlerts = ClinicalIndicatorMonitoring::forPatients(
            $wardPatients->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
        );

        // I/O chart: today's intake against the limit and signs of overload, for users with the tab on
        $fluidBalanceEnabled = FluidBalanceChart::enabledFor($userSettings);
        $fluidBalanceAlerts = $fluidBalanceEnabled
            ? FluidBalanceChart::alertsForPatients(
                $wardPatients->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            )
            : [];

        // Calculate statistics based on admitted patients only
        $admittedPatients = Patient::where('ward_id', $selectedWardId)
            ->where('is_active', true)
            ->where('status', 'admitted')
            ->get();

        $statistics = $this->calculateWardStatistics($selectedWardId, $beds, $admittedPatients);

        // Preparations for view
        $consultantPatients = $this->getConsultantPatients($selectedWardId);
        $nursePatients = $this->getNursePatients($selectedWardId);
        $anaesthetistPatients = $this->getAnaesthetistPatients($selectedWardId);

        $bedBoxVitalsMode = $userSettings ? ($userSettings->bed_box_vitals_mode ?? 'demo') : 'demo';
        $notificationCount = WardNotification::forWard($selectedWardId)->pending()->count();

        $currentShift = ShiftSetting::getCurrentShift($selectedWardId);

        // Fetch all special duties for the day regardless of current shift
        $specialDuties = WardSpecialDuty::where('ward_id', $selectedWardId)
            ->where('date', now()->toDateString())
            ->with('nurse')
            ->orderByRaw("FIELD(shift, 'AM', 'PM', 'ON')") // Order by shift
            ->get();

        // Debug logging
        Log::info('Ward Dashboard Data', [
            'ward_id' => $selectedWardId,
            'consultant_count' => count($consultantPatients),
            'nurse_count' => count($nursePatients),
        ]);

        $bedBoxConfig = $settings['bedBoxConfig'];
        $patientInfoConfig = $settings['patientInfoConfig'];
        $dashboardDisplay = $settings['dashboardDisplay'];

        // Define duty types (keeping consistent with WardScheduleController)
        $dutyTypes = [
            ['key' => 'team_leader', 'label' => 'Team Leader', 'shifts' => ['AM', 'PM', 'ON'], 'note' => '(Daily)'],
            ['key' => 'dda_mc_book', 'label' => 'DDA + MC book', 'shifts' => ['AM', 'PM', 'ON'], 'note' => '(Daily)'],
            ['key' => 'medication_fridge', 'label' => 'Medication fridge', 'shifts' => ['AM', 'PM', 'ON'], 'note' => '(Daily)'],
            ['key' => 'e_trolley', 'label' => 'E-trolley', 'shifts' => ['ON'], 'note' => '(Night Only)'],
            ['key' => 'qc_checking', 'label' => 'QC checking', 'shifts' => ['ON'], 'note' => '(Night Only)'],
        ];

        return view('wards.dashboard', compact(
            'wards',
            'beds',
            'statistics',
            'selectedWard',
            'consultantPatients',
            'nursePatients',
            'anaesthetistPatients',
            'bedBoxConfig', // using compact with variables directly from settings array doesn't work, so we need to extract them or pass array
            'patientInfoConfig',
            'dashboardDisplay',
            'bedBoxVitalsMode',
            'notificationCount',
            'currentShift',
            'specialDuties',
            'dutyTypes',
            'medicationMonitoring',
            'medicationAlerts',
            'assessmentAlerts',
            'fluidBalanceEnabled',
            'fluidBalanceAlerts'
        ));
    }

    public function nurseDashboard(Request $request, Nurse $nurse): View
    {
        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();
        $todaysAssignments = WardScheduleAssignment::where('nurse_id', $nurse->id)
            ->where('scheduled_date', now()->toDateString())
            ->get();

        $selectedWardId = $request->integer('ward_id')
            ?: $nurse->ward_id
            ?: $todaysAssignments->pluck('ward_id')->filter()->first()
            ?: ($wards->first()->id ?? null);

        $selectedWard = $selectedWardId ? Ward::find($selectedWardId) : null;
        $userSettings = WardDashboardSetting::where('user_id', Auth::id())->first();
        $settings = $this->resolveDashboardSettings($userSettings);

        if (!$selectedWard) {
            return view('wards.nurse-dashboard', [
                'nurse' => $nurse,
                'wards' => $wards,
                'selectedWard' => null,
                'assignedBeds' => collect(),
                'currentShift' => null,
                'latestVitalsByPatient' => collect(),
                'infusionsByPatient' => collect(),
                'summary' => [
                    'assigned_beds' => 0,
                    'occupied_beds' => 0,
                    'critical_patients' => 0,
                    'active_infusions' => 0,
                    'infusion_alerts' => 0,
                    'ward_occupancy' => 0,
                ],
                'wardStatistics' => [
                    'patients' => 0,
                    'available' => 0,
                    'occupancy' => 0,
                    'infusion_alarms' => 0,
                    'infusion_warnings' => 0,
                ],
                'dashboardDisplay' => $settings['dashboardDisplay'],
            ]);
        }

        $this->syncBedsWithPatients($selectedWard->id);

        $wardPatients = Patient::where('ward_id', $selectedWard->id)
            ->where('is_active', true)
            ->with([
                'consultant',
                'nurse',
                'anaesthetist',
                'activeCareProviders.consultant',
                'activeCareProviders.anaesthetist',
                'latestSugarReading',
            ])
            ->get();

        $movementsByPatient = PatientMovement::whereIn('patient_id', $wardPatients->pluck('id'))
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->groupBy('patient_id');

        $consultants = Consultant::where('is_active', true)->get();
        $nurses = Nurse::where('is_active', true)->get();

        $clinicalSettings = $userSettings && is_array($userSettings->clinical_settings)
            ? array_merge(['ews_system' => 'ews_ihh'], $userSettings->clinical_settings)
            : ['ews_system' => 'ews_ihh'];

        $allBeds = collect($this->generateBedData(
            $selectedWard,
            $wardPatients,
            $consultants,
            $nurses,
            $movementsByPatient,
            $clinicalSettings['ews_system'] ?? 'ews_ihh'
        ));

        $currentShift = ShiftSetting::getCurrentShift($selectedWard->id);

        $assignmentQuery = WardScheduleAssignment::where('nurse_id', $nurse->id)
            ->where('ward_id', $selectedWard->id)
            ->where('scheduled_date', now()->toDateString())
            ->with('bed');

        if ($currentShift) {
            $assignmentQuery->where('shift', $currentShift->shift_code);
        }

        $assignments = $assignmentQuery->get();
        $assignedBedIds = $assignments->pluck('bed_id')->filter()->unique()->values();
        $assignedBedNumbers = $assignments->pluck('bed.bed_number')->filter()->unique()->values();

        $assignedBeds = $allBeds
            ->filter(function (array $bed) use ($assignedBedIds, $assignedBedNumbers) {
                return $assignedBedIds->contains($bed['bed_id'])
                    || $assignedBedNumbers->contains($bed['number']);
            })
            ->sortBy('number')
            ->values();

        $patientIds = $assignedBeds->pluck('patient_id')->filter()->unique()->values();

        $latestVitalsByPatient = VitalSign::whereIn('patient_id', $patientIds)
            ->orderBy('recorded_at', 'desc')
            ->get()
            ->unique('patient_id')
            ->keyBy('patient_id');

        $infusionsByPatient = Infusion::with('infusionPump')
            ->whereIn('patient_id', $patientIds)
            ->active()
            ->latest('last_updated_at')
            ->get()
            ->groupBy('patient_id');

        $admittedPatients = Patient::where('ward_id', $selectedWard->id)
            ->where('is_active', true)
            ->where('status', 'admitted')
            ->get();

        $wardStatistics = $this->calculateWardStatistics($selectedWard->id, $allBeds, $admittedPatients);
        $activeInfusionItems = $infusionsByPatient->flatten(1);

        $summary = [
            'assigned_beds' => $assignedBeds->count(),
            'occupied_beds' => $assignedBeds->whereIn('status', ['occupied', 'reserved'])->count(),
            'critical_patients' => $assignedBeds->where('ews_is_abnormal', true)->count(),
            'active_infusions' => $activeInfusionItems->count(),
            'infusion_alerts' => $activeInfusionItems->where('is_warning', true)->count()
                + $activeInfusionItems->where('status', Infusion::STATUS_ALARMING)->count(),
            'ward_occupancy' => $wardStatistics['occupancy'] ?? 0,
        ];

        return view('wards.nurse-dashboard', [
            'nurse' => $nurse,
            'wards' => $wards,
            'selectedWard' => $selectedWard,
            'assignedBeds' => $assignedBeds,
            'currentShift' => $currentShift,
            'latestVitalsByPatient' => $latestVitalsByPatient,
            'infusionsByPatient' => $infusionsByPatient,
            'summary' => $summary,
            'wardStatistics' => $wardStatistics,
            'dashboardDisplay' => $settings['dashboardDisplay'],
        ]);
    }

    private function syncBedsWithPatients($wardId)
    {
        // Sync bed records in the Bed table with patient assignments
        $beds = Bed::where('ward_id', $wardId)->get();

        foreach ($beds as $bed) {
            /** @var \App\Models\Bed $bed */
            // Check if bed has a patient assigned through the patient table
            $patient = Patient::where('ward_id', $wardId)
                ->where('bed_number', $bed->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'pending_discharge', 'prebook']) // Changed order to make it explicit, though orderByRaw handles priority
                ->orderByRaw("FIELD(status, 'admitted', 'pending_discharge', 'prebook')")
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
                // No ACTIVE patient assigned.
                // Check if there is a PENDING PREBOOK waiting for this bed
                $pendingPrebook = Patient::where('ward_id', $wardId)
                    ->where('target_bed_number', $bed->bed_number)
                    ->where('is_active', true)
                    ->where('status', 'prebook_pending')
                    ->first();

                if ($pendingPrebook) {
                    // Activate the prebook!
                    $pendingPrebook->update([
                        'status' => 'prebook',
                        'bed_number' => $bed->bed_number,
                    ]);

                    $bed->update([
                        'status' => 'reserved',
                        'patient_id' => $pendingPrebook->id,
                    ]);
                } else if ($bed->status !== 'maintenance') {
                    // No patient assigned and not maintenance, mark bed as available
                    $bed->update([
                        'status' => 'available',
                        'patient_id' => null,
                    ]);
                }
            }
        }
    }

    private function generateBedData($ward, $wardPatients, $consultants, $nurses, $movementsByPatient, $ewsSystem = 'ews_ihh')
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

                // Check if there's already a pending prebook for this bed (prebook_pending status)
                // We now check for ANY occupied bed, not just pending discharge
                $pendingPrebook = Patient::where('ward_id', $ward->id)
                    ->where('target_bed_number', $bedNumber)
                    ->where('is_active', true)
                    ->where('status', 'prebook_pending')
                    ->first();

                // Get latest vital signs and calculate EWS
                $latestVitals = VitalSign::where('patient_id', $patient->id)
                    ->orderBy('recorded_at', 'desc')
                    ->first();

                $ewsData = $this->calculateEWS($latestVitals, $ewsSystem);

                // Get nurse on duty from schedule assignment
                $nurseOnDuty = $nurseAssignments[$wardBed->id] ?? null;

                // Get attending doctor from care providers (ADT PV1-7)
                // The attending doctor is the main doctor that will visit the patient
                // Falls back to old consultant field for backward compatibility
                $attendingDoctor = $patient->activeCareProviders()
                    ->where('role', PatientCareProvider::ROLE_ATTENDING)
                    ->first();
                $attendingDoctorName = $attendingDoctor ? $attendingDoctor->display_name :
                    ($patient->consultant ? $patient->consultant->name : 'Not Assigned');

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
                    'consultant' => $attendingDoctorName,
                    'nurse' => $patient->nurse ? $patient->nurse->name : 'Not Assigned',
                    'nurse_on_duty' => $nurseOnDuty ? $nurseOnDuty['nurse_name'] : null,
                    'current_shift' => $currentShiftCode,
                    'gender' => $patient->gender,
                    'age' => $patient->age,
                    'days' => $days,
                    'hours' => $hours,
                    'ews' => $ewsData['score'],
                    'ews_has_vitals' => $ewsData['has_vitals'],
                    'ews_severity' => $ewsData['has_vitals'] && $ewsData['score'] !== null
                        ? WardNotification::getSeverityFromEws($ewsData['score'])
                        : null,
                    'ews_is_abnormal' => $ewsData['has_vitals'] && $ewsData['score'] !== null && $ewsData['score'] >= 3,
                    'ews_bg_class' => $this->getEwsBgClass($ewsData['score'], $ewsData['has_vitals']),
                    'consultant_id' => $patient->consultant_id,
                    'anaesthetist_id' => $patient->anaesthetist_id,
                    'booked_datetime' => $patient->booked_at ? $patient->booked_at->format('Y-m-d H:i') : null,
                    'booked_at_raw' => $patient->booked_at ? $patient->booked_at->format('Y-m-d\TH:i') : null,
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
                    // HGT (Blood Glucose) monitoring
                    'hgt_enabled' => $patient->hgt_enabled ?? false,
                    'hgt_frequency' => $patient->hgt_frequency ?? null,
                    'last_hgt' => $patient->latestSugarReading ? [
                        'value' => (float) $patient->latestSugarReading->value,
                        'recorded_at' => $patient->latestSugarReading->recorded_at->format('M d H:i'),
                    ] : null,
                    // Prebook notes from admission log
                    'prebook_notes' => $patient->status === 'prebook'
                        ? AdmissionLog::where('patient_id', $patient->id)
                            ->where('action', 'prebook')
                            ->orderBy('created_at', 'desc')
                            ->value('notes')
                        : null,
                    // Pending prebook info (for pending discharge beds)
                    'has_pending_prebook' => $pendingPrebook !== null,
                    'pending_prebook_patient_id' => $pendingPrebook?->id,
                    'pending_prebook_patient_name' => $pendingPrebook?->name,
                    'pending_prebook_notes' => $pendingPrebook ? AdmissionLog::where('patient_id', $pendingPrebook->id)
                        ->whereIn('action', ['prebook', 'prebook-pending'])
                        ->orderBy('created_at', 'desc')
                        ->value('notes') : null,
                ];

                // Create or update EWS notification if abnormal
                if ($ewsData['has_vitals'] && $ewsData['score'] !== null) {
                    WardNotification::createOrUpdateEwsNotification(
                        $ward->id,
                        $patient->id,
                        $bedNumber,
                        $ewsData['score'],
                        $patient->name
                    );
                }
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
                    'ews_severity' => null,
                    'ews_is_abnormal' => false,
                    'ews_bg_class' => null,
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
                    // HGT (Blood Glucose) monitoring
                    'hgt_enabled' => false,
                    'hgt_frequency' => null,
                    'last_hgt' => null,
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
                'source' => 'manual',
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

            // Explicitly push to EKad for Admission logging
            /*
            try {
                $ekadService = new EkadService();
                // Handled by BedObserver
                $ekadService->pushPatientInfo($patient, $bedRecord, [], 'Admission');
            } catch (\Exception $e) {
                Log::warning('EKad Admission push failed', ['error' => $e->getMessage()]);
            }
            */

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
            'is_pending_discharge' => 'nullable|boolean',
        ]);

        try {
            // Check if bed is already occupied or prebooked
            $existingPatient = Patient::where('ward_id', $request->ward_id)
                ->where('bed_number', $request->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
                ->first();

            // Allow prebook only for pending_discharge beds, block for admitted/prebook
            $isPrebookForPendingDischarge = false;
            if ($existingPatient) {
                if ($existingPatient->status === 'pending_discharge' || $existingPatient->status === 'admitted') {
                    // This is allowed - prebook can coexist with admitted or pending discharge patients
                    // The new prebook will have status 'prebook_pending'
                    $isPrebookForPendingDischarge = true;
                    Log::info('Prebook for occupied bed', [
                        'bed_number' => $request->bed_number,
                        'ward_id' => $request->ward_id,
                        'current_patient_id' => $existingPatient->id,
                        'current_status' => $existingPatient->status,
                        'user_id' => Auth::id(),
                    ]);
                } else {
                    // Block prebook for already prebooked beds
                    Log::warning('Prebook failed: Bed already prebooked', [
                        'bed_number' => $request->bed_number,
                        'ward_id' => $request->ward_id,
                        'attempted_patient_id' => $request->patient_id,
                        'existing_patient_id' => $existingPatient->id,
                        'user_id' => Auth::id(),
                    ]);
                    return back()->with('error', 'This bed is already prebooked!');
                }
            }

            $ward = Ward::findOrFail($request->ward_id);
            $consultant = $request->consultant_id ? Consultant::find($request->consultant_id) : null;
            $anaesthetist = $request->anaesthetist_id ? Anaesthetist::find($request->anaesthetist_id) : null;
            $bookedAt = $request->booked_at ? $request->booked_at : now();

            // For pending discharge prebooks, we don't assign bed_number yet
            // The prebook will wait for the bed to become available
            $actualBedNumber = $isPrebookForPendingDischarge ? null : $request->bed_number;
            $prebookStatus = $isPrebookForPendingDischarge ? 'prebook_pending' : 'prebook';

            // Add pending discharge info to notes if applicable
            $notes = $request->notes ?? '';
            if ($isPrebookForPendingDischarge) {
                $pendingPatientName = $existingPatient->name ?? 'Current patient';
                $notes = "[Waiting for Bed {$request->bed_number} - Current patient: {$pendingPatientName} (pending discharge)]" . ($notes ? "\n" . $notes : '');
            }

            // If patient_id is provided, update existing patient
            if ($request->filled('patient_id')) {
                $patient = Patient::findOrFail($request->patient_id);

                // Update patient with optional fields (nurse is assigned via ward schedule)
                $updateData = [
                    'ward_id' => $request->ward_id,
                    'bed_number' => $actualBedNumber,
                    'target_bed_number' => $request->bed_number, // Store the target bed
                    'consultant_id' => $request->consultant_id,
                    'anaesthetist_id' => $request->anaesthetist_id,
                    'booked_at' => $bookedAt,
                    'status' => $prebookStatus,
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
                    'bed_number' => $actualBedNumber,
                    'target_bed_number' => $request->bed_number, // Store the target bed
                    'consultant_id' => $request->consultant_id,
                    'anaesthetist_id' => $request->anaesthetist_id,
                    'gender' => $request->gender ?? 'Male',
                    'age' => $request->age ?? 0,
                    'phone' => 'N/A',
                    'booked_at' => $bookedAt,
                    'status' => $prebookStatus,
                    'is_active' => true,
                ]);
            }

            // Update bed status in Bed table ONLY if not a pending discharge prebook
            if (!$isPrebookForPendingDischarge) {
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
            }

            // Create admission log for prebook
            AdmissionLog::create([
                'patient_id' => $patient->id,
                'ward_id' => $request->ward_id,
                'user_id' => Auth::id(),
                'bed_number' => $request->bed_number, // Log the target bed
                'action' => $isPrebookForPendingDischarge ? 'prebook-pending' : 'prebook',
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'consultant_name' => $consultant ? $consultant->name : null,
                'nurse_name' => null, // Nurse is now assigned via ward schedule
                'gender' => $request->gender ?? $patient->gender,
                'age' => $request->age ?? $patient->age,
                'notes' => $notes,
                'booked_at' => $bookedAt,
                'source' => 'manual',
            ]);

            Log::info('Patient prebooked successfully', [
                'patient_id' => $patient->id,
                'patient_name' => $patient->name,
                'mrn' => $patient->mrn,
                'ward_id' => $request->ward_id,
                'ward_name' => $ward->ward_name,
                'bed_number' => $request->bed_number,
                'is_pending_discharge_prebook' => $isPrebookForPendingDischarge,
                'consultant' => $consultant ? $consultant->name : 'None',
                'booked_at' => $bookedAt,
                'notes' => $notes,
                'user_id' => Auth::id(),
            ]);

            $successMessage = $isPrebookForPendingDischarge
                ? 'Bed prebooked for next patient! The prebook will be active once the current patient is discharged.'
                : 'Bed prebooked successfully!';

            return back()->with('success', $successMessage);
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
                'source' => 'manual',
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

    public function cancelPrebook(Request $request, $patientId)
    {
        try {
            $patient = Patient::findOrFail($patientId);

            // Accept both prebook and prebook_pending statuses
            if (!in_array($patient->status, ['prebook', 'prebook_pending'])) {
                Log::warning('Cancel prebook failed: Patient not in prebook status', [
                    'patient_id' => $patientId,
                    'current_status' => $patient->status,
                    'user_id' => Auth::id(),
                ]);
                return back()->with('error', 'Patient is not in prebook status!');
            }

            $wardId = $patient->ward_id;
            $bedNumber = $patient->bed_number ?? $patient->target_bed_number; // Use target_bed_number for prebook_pending
            $patientName = $patient->name;
            $mrn = $patient->mrn;
            $isPrebookPending = $patient->status === 'prebook_pending';

            // Update bed status in Bed table ONLY if it's a regular prebook (not prebook_pending)
            if (!$isPrebookPending && $bedNumber) {
                $bed = Bed::where('ward_id', $wardId)
                    ->where('bed_number', $bedNumber)
                    ->first();
                if ($bed) {
                    $bed->update([
                        'status' => 'available',
                        'patient_id' => null,
                    ]);
                }
            }

            // Clear patient's ward and bed assignment
            $patient->update([
                'ward_id' => null,
                'bed_number' => null,
                'target_bed_number' => null,
                'status' => 'active',
                'consultant_id' => null,
                'nurse_id' => null,
                'anaesthetist_id' => null,
            ]);

            // Create admission log for cancel prebook
            AdmissionLog::create([
                'patient_id' => $patient->id,
                'ward_id' => $wardId,
                'user_id' => Auth::id(),
                'bed_number' => $bedNumber,
                'action' => 'cancel-prebook',
                'patient_name' => $patientName,
                'mrn' => $mrn,
                'source' => 'manual',
            ]);

            Log::info('Prebook cancelled successfully', [
                'patient_id' => $patient->id,
                'patient_name' => $patientName,
                'mrn' => $mrn,
                'ward_id' => $wardId,
                'bed_number' => $bedNumber,
                'user_id' => Auth::id(),
            ]);

            return back()->with('success', 'Prebook cancelled successfully!');
        } catch (\Exception $e) {
            Log::error('Cancel prebook failed', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'user_id' => Auth::id(),
            ]);

            return back()->with('error', 'Failed to cancel prebook: ' . $e->getMessage());
        }
    }

    // Admission Logs moved to AdmissionLogController (list, print dialog count, printable report)

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
        // A form just posted from a tab comes back to that tab, even when the page URL still
        // names the tab an earlier save returned to; then ?active_tab=, then ?open_tab= (the
        // tab to open on, e.g. Medications from a bed box)
        $activeTab = session()->getOldInput('active_tab')
            ?? $request->input('active_tab')
            ?? $request->input('open_tab', 'info');

        $patient = null;
        if ($patientId) {
            $patient = Patient::with([
                'ward',
                'ward.wardType.clinicalIndicators',
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

        // Helper methods to reduce complexity
        $patientDetailsTabs = $this->resolvePatientDetailsTabs($request, $activeTab);

        $initialOptions = $this->getDefaultClinicalIndicatorOptions();
        $clinicalIndicatorOptions = $this->getEnrichedClinicalIndicatorOptions($initialOptions);

        $patientVitalsMode = 'demo';
        $additionalInfoReadOnly = false;
        if (Auth::check()) {
            $settings = WardDashboardSetting::where('user_id', Auth::id())->first();
            if ($settings) {
                $patientVitalsMode = $settings->bed_box_vitals_mode ?? 'demo';
                $additionalInfoReadOnly = (bool) ($settings->additional_info_read_only ?? false);
            }
        }

        $displayNames = $this->getPatientDisplayNames($patient);
        $wardClinicalIndicators = $this->wardClinicalIndicators($patient);
        $bloodTransfusions = $patient
            ? BloodTransfusion::where('patient_id', $patient->id)
                ->with('checkedBy:id,name')
                ->orderByRaw("FIELD(status, 'in_progress', 'pending', 'completed', 'stopped')")
                ->orderByDesc('id')
                ->get()
            : collect();

        // Medication monitoring is only loaded for users who have the tab switched on
        $medicationMonitoring = (bool) ($patientDetailsTabs[PatientMedication::SETTINGS_TAB] ?? false);

        // I/O chart for the chart day asked for (?io_day=, the date it starts on), or today's
        $fluidBalance = $patient && ($patientDetailsTabs[FluidBalanceChart::SETTINGS_TAB] ?? false)
            ? FluidBalanceChart::forPatient($patient, $request->input('io_day'))
            : null;

        return view('wards.patient-details', [
            'patient' => $patient,
            'consultants' => $consultants,
            'anaesthetists' => $anaesthetists,
            'wards' => $wards,
            'activeTab' => $activeTab,
            'patientDetailsTabs' => $patientDetailsTabs,
            'clinicalIndicatorOptions' => $clinicalIndicatorOptions,
            'patientVitalsMode' => $patientVitalsMode,
            'additionalInfoReadOnly' => $additionalInfoReadOnly,
            'consultantName' => $displayNames['consultant'],
            'nurseName' => $displayNames['nurse'],
            'anaesthetistName' => $displayNames['anaesthetist'],
            'wardClinicalIndicators' => $wardClinicalIndicators,
            'bloodTransfusions' => $bloodTransfusions,
            'infoSources' => PatientInfoSources::all(),
            'dietTypeOptions' => DietType::where('is_active', true)
                ->whereNotIn('code', Patient::NBM_DIET_CODES)
                ->orderBy('name')
                ->get(['code', 'name']),
            'medicationOrders' => $medicationMonitoring && $patient
                ? PatientMedication::forPatient($patient->id)
                : collect(),
            'medicationFormulary' => $medicationMonitoring
                ? Medication::active()->orderBy('category')->orderBy('name')->get()
                : collect(),
            'fluidBalance' => $fluidBalance,
        ]);
    }

    /**
     * The assessment scales this patient's ward is set up for: the active
     * clinical indicators bound to the ward's ward type, each paired with its
     * clinical content from the library. Empty when the ward has no ward type,
     * which is the case for every ward until one is bound.
     */
    private function wardClinicalIndicators(?Patient $patient)
    {
        $wardType = $patient?->ward?->wardType;

        if (!$wardType || !$wardType->is_active) {
            return collect();
        }

        $indicators = $wardType->clinicalIndicators->where('is_active', true);

        $scores = ClinicalIndicatorScore::where('patient_id', $patient->id)
            ->whereIn('clinical_indicator_id', $indicators->pluck('id'))
            ->with('recordedBy:id,name')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('clinical_indicator_id');

        return $indicators
            ->map(function ($indicator) use ($scores, $patient) {
                $definition = $indicator->definition();
                $history = $scores->get($indicator->id, collect());

                return [
                    'id' => $indicator->id,
                    'code' => $indicator->code,
                    'name' => $indicator->name,
                    'definition' => $definition,
                    'scorable' => ClinicalIndicatorLibrary::isScorable($definition),
                    'latest' => $history->first(),
                    'history' => $history->take(10),
                    // When it is next due, for scales with a monitoring interval switched on
                    'monitoring' => $indicator->isMonitored()
                        ? ClinicalIndicatorMonitoring::status(
                            $indicator,
                            $history->first()?->recorded_at,
                            $patient->admitted_at ?? $patient->created_at
                        )
                        : null,
                ];
            })
            ->values();
    }

    /**
     * Record a score for one of the clinical indicators bound to the patient's
     * ward type. Item-by-item scales are totalled from the item values on the
     * server rather than trusting the total the browser posted.
     */
    public function storeClinicalIndicatorScore(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'clinical_indicator_id' => 'required|exists:clinical_indicators,id',
            'score' => 'nullable|integer|min:0',
            'item_scores' => 'nullable|array',
            'item_scores.*' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $indicator = ClinicalIndicator::findOrFail($validated['clinical_indicator_id']);

        // Only scales this patient's ward is actually set up for may be scored.
        $allowed = $patient->ward?->wardType?->clinicalIndicators
            ->where('is_active', true)
            ->contains('id', $indicator->id);

        if (!$allowed) {
            return back()->withInput()->with('error', 'That clinical indicator is not bound to this ward.');
        }

        $definition = $indicator->definition();
        $itemScores = null;

        if (ClinicalIndicatorLibrary::isScorable($definition)) {
            $items = $definition['items'];
            $posted = $validated['item_scores'] ?? [];

            if (count(array_filter($posted, fn ($v) => $v !== null)) !== count($items)) {
                return back()->withInput()->with('error', 'Score every item before saving.');
            }

            $itemScores = [];
            $total = 0;

            foreach ($items as $index => $item) {
                $value = (int) $posted[$index];
                $permitted = array_column($item['options'], 'value');

                if (!in_array($value, $permitted, true)) {
                    return back()->withInput()->with('error', 'That is not a valid option for ' . $item['name'] . '.');
                }

                $label = collect($item['options'])->firstWhere('value', $value)['label'] ?? null;
                $entry = ['name' => $item['name'], 'label' => $label, 'value' => $value];
                if (!empty($item['abbr'])) {
                    $entry['abbr'] = $item['abbr'];
                }
                $itemScores[] = $entry;
                $total += $value;
            }

            $score = $total;
        } else {
            if ($validated['score'] === null) {
                return back()->withInput()->with('error', 'Enter a score before saving.');
            }
            $score = (int) $validated['score'];
        }

        $record = new ClinicalIndicatorScore([
            'patient_id' => $patient->id,
            'clinical_indicator_id' => $indicator->id,
            'ward_id' => $patient->ward_id,
            'score' => $score,
            'item_scores' => $itemScores,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => Auth::id(),
            'recorded_at' => now(),
        ]);

        $record->applyBand($indicator->code)->save();
        $breakdown = $record->breakdown();

        return back()->withInput()->with('success', $indicator->name . ' scored ' . $score
            . ($breakdown ? ' (' . $breakdown . ')' : '')
            . ($record->band_label ? ' - ' . $record->band_label : '') . '.');
    }

    /**
     * Register a unit for this patient. It starts pending: nothing runs until
     * the bedside checks are recorded and the unit is explicitly started.
     */
    public function storeBloodTransfusion(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'unit_number' => 'required|string|max:64',
            'product_type' => ['required', Rule::in(BloodTransfusion::PRODUCT_TYPES)],
            'unit_blood_group' => ['nullable', Rule::in(BloodTransfusion::BLOOD_GROUPS)],
            'patient_blood_group' => ['nullable', Rule::in(BloodTransfusion::BLOOD_GROUPS)],
            'crossmatch_reference' => 'nullable|string|max:64',
            'unit_expires_at' => 'nullable|date',
            'volume_ml' => 'nullable|integer|min:' . BloodTransfusion::VOLUME_MIN . '|max:' . BloodTransfusion::VOLUME_MAX,
            'prescribed_minutes' => 'nullable|integer|min:' . BloodTransfusion::MINUTES_MIN . '|max:' . BloodTransfusion::MAX_RUNNING_MINUTES,
            'notes' => 'nullable|string|max:1000',
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);

        BloodTransfusion::create($validated + [
            'ward_id' => $patient->ward_id,
            'status' => BloodTransfusion::STATUS_PENDING,
            'created_by' => Auth::id(),
        ]);

        return back()->withInput()->with('success', 'Unit ' . $validated['unit_number'] . ' registered.');
    }

    /**
     * Confirm or undo a single pre-start check. The checks run as a sequence,
     * so a step is only accepted once everything before it is confirmed, and
     * only the most recent step can be undone.
     */
    public function updateBloodTransfusionChecklist(Request $request, BloodTransfusion $transfusion)
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in(['check_crossmatch', 'check_product', 'check_expiry', 'check_identity'])],
            'action' => ['required', Rule::in(['confirm', 'undo'])],
        ]);

        if ($transfusion->isFinished()) {
            return back()->withInput()->with('error', 'That unit is already finished.');
        }

        $step = collect($transfusion->checklistSteps())->firstWhere('key', $validated['step']);

        if ($validated['action'] === 'confirm') {
            if (!$step['unlocked']) {
                return back()->withInput()->with('error', 'Confirm the earlier checks first.');
            }
            $transfusion->{$validated['step']} = true;
        } else {
            if (!$step['canUndo']) {
                return back()->withInput()->with('error', 'Only the last confirmed check can be undone.');
            }
            $transfusion->{$validated['step']} = false;
        }

        $transfusion->checked_by = Auth::id();
        $transfusion->checked_at = now();
        $transfusion->save();

        $done = $transfusion->completedStepCount();

        return back()->withInput()->with('success', $validated['action'] === 'confirm'
            ? $step['label'] . ' confirmed (' . $done . ' of 4).'
            : $step['label'] . ' undone (' . $done . ' of 4).');
    }

    /**
     * Start the unit running. Refused while any check is outstanding or any
     * critical exception is open, so an expired or mismatched unit cannot be
     * started from this screen.
     */
    public function startBloodTransfusion(Request $request, BloodTransfusion $transfusion)
    {
        if (!$transfusion->canStart()) {
            $reason = $transfusion->checksComplete()
                ? 'Resolve the flagged problems before starting this unit.'
                : 'Complete every pre-start check before starting this unit.';

            return back()->withInput()->with('error', $reason);
        }

        $transfusion->update([
            'status' => BloodTransfusion::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        return back()->withInput()->with('success', 'Unit ' . $transfusion->unit_number . ' started.');
    }

    /**
     * Finish a running unit, either completed or stopped early.
     */
    public function finishBloodTransfusion(Request $request, BloodTransfusion $transfusion)
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['completed', 'stopped'])],
            'stop_reason' => 'nullable|string|max:255',
        ]);

        if (!$transfusion->isRunning()) {
            return back()->withInput()->with('error', 'That unit is not running.');
        }

        if ($validated['outcome'] === 'stopped' && empty($validated['stop_reason'])) {
            return back()->withInput()->with('error', 'Give a reason when stopping a unit early.');
        }

        $transfusion->update([
            'status' => $validated['outcome'] === 'completed'
                ? BloodTransfusion::STATUS_COMPLETED
                : BloodTransfusion::STATUS_STOPPED,
            'completed_at' => now(),
            'stop_reason' => $validated['stop_reason'] ?? null,
        ]);

        return back()->withInput()->with('success', 'Unit ' . $transfusion->unit_number . ' '
            . $validated['outcome'] . '.');
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
            'io' => true, // I/O chart (fluid balance)
            'medications' => false, // Medication monitoring: off until a user switches it on
            'movement' => true,
            'careprovider' => true, // shown as "Consultant"
            'anaesthetist' => true,
            'nurses' => true, // from the ward roster
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
            ['key' => 'prebook_button', 'visible' => true, 'order' => 7],
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
            $clinicalIndicatorOptions['diet_type'] = $dbDietTypes->map(function ($dt) {
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
            // Prepend "None" option to ensure proper default value
            $noneOption = [['value' => 'none', 'label' => 'No Isolation Precaution', 'color' => 'bg-gray-100 text-gray-600']];
            $dbOptions = $dbIsolationTypes->map(function ($it) {
                return [
                    'value' => $it->code,
                    'label' => $it->name,
                    'color' => in_array(strtoupper($it->code), ['COVID', 'TB', 'AIR', 'AIRBORNE'])
                        ? 'bg-red-100 text-red-700'
                        : 'bg-yellow-100 text-yellow-700',
                ];
            })->toArray();
            $clinicalIndicatorOptions['isolation_type'] = array_merge($noneOption, $dbOptions);
        }

        // Vitals data mode settings (demo/real/off)
        $patientVitalsMode = $settings->patient_vitals_mode ?? 'demo';
        $bedBoxVitalsMode = $settings->bed_box_vitals_mode ?? 'demo';

        // Additional info read-only setting
        $additionalInfoReadOnly = (bool) ($settings->additional_info_read_only ?? false);

        return view('wards.settings', [
            'tabs' => $tabs,
            'bedBoxDisplay' => $bedBoxDisplay,
            'patientInfoDisplay' => $patientInfoDisplay,
            'clinicalIndicatorOptions' => $clinicalIndicatorOptions,
            'dashboardDisplay' => $dashboardDisplay,
            'clinicalSettings' => $clinicalSettings,
            'patientVitalsMode' => $patientVitalsMode,
            'bedBoxVitalsMode' => $bedBoxVitalsMode,
            'additionalInfoReadOnly' => $additionalInfoReadOnly,
            'infoSources' => PatientInfoSources::all(),
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

        if ($settingType === 'additional_info_read_only') {
            // Handle additional info read-only mode toggle
            $settings->additional_info_read_only = $request->has('additional_info_read_only');
            $settings->save();

            return back()->with('success', 'Additional Info read-only setting updated successfully.');
        }

        if ($settingType === 'patient_info_sources') {
            // System-wide: decides whether ADT or the ward maintains each field
            $request->validate([
                'sources' => 'required|array',
                'sources.*' => ['required', Rule::in([PatientInfoSources::ADT, PatientInfoSources::MANUAL])],
            ]);

            PatientInfoSources::save($request->input('sources'));

            Log::info('Patient additional info sources updated', [
                'sources' => PatientInfoSources::all(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->with('success', 'Patient additional info sources updated. This applies to all users.')
                ->with('settings_tab', 'patient-additional-info');
        }

        // Handle patient details tabs settings (default)
        $defaultTabs = [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'io' => true,
            'medications' => false,
            'movement' => true,
            'careprovider' => true, // shown as "Consultant"
            'anaesthetist' => true,
            'nurses' => true, // from the ward roster
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

        return back()
            ->with('success', 'Settings updated successfully.')
            ->with('settings_tab', 'patient-details');
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

        // EKad: Push update to reflect new anaesthetist/consultant
        try {
            if ($patient->isAdmitted() && $patient->bed) {
                $ekadService = new EkadService();
                $ekadService->pushPatientInfo($patient, $patient->bed, [], 'Update Information');
            }
        } catch (\Exception $e) {
            Log::warning('EKad push on referral failed', ['error' => $e->getMessage()]);
        }

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
            'source' => 'manual',
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
    /**
     * Manually add a consultant to the patient's care team.
     *
     * ADT (PV1-7/8/9) normally drives care providers, but wards need to add a
     * consultant who was brought in verbally or outside the ADT feed. Manual
     * entries sit alongside ADT ones and are the only ones that can be removed.
     */
    public function storeCareProvider(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'consultant_id' => 'required|exists:consultants,id',
            'role' => 'required|in:attending,referring,consulting',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($validated['patient_id']);
        $consultant = Consultant::findOrFail($validated['consultant_id']);

        $redirect = redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'careprovider',
        ]);

        $alreadyAssigned = PatientCareProvider::where('patient_id', $patient->id)
            ->where('consultant_id', $consultant->id)
            ->where('role', $validated['role'])
            ->where('is_active', true)
            ->exists();

        if ($alreadyAssigned) {
            return $redirect->with('error', "{$consultant->name} is already assigned to this patient in that role.");
        }

        PatientCareProvider::create([
            'patient_id' => $patient->id,
            'role' => $validated['role'],
            'doctor_code' => $consultant->personnel_code,
            'doctor_name' => $consultant->name,
            'consultant_id' => $consultant->id,
            'source' => PatientCareProvider::SOURCE_MANUAL,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        Log::info('Care provider added manually', [
            'patient_id' => $patient->id,
            'consultant_id' => $consultant->id,
            'role' => $validated['role'],
            'added_by' => Auth::id(),
        ]);

        return $redirect->with('success', "{$consultant->name} added to the care team.");
    }

    /**
     * Manually add an anaesthetist to the patient's care team (Anaesthetist tab).
     *
     * Stored the way ADT stores a PV1 doctor that matches an anaesthetist: a
     * consulting care provider linked to the anaesthetist. The bedside patient
     * app and the E-Ink display therefore pick it up like an ADT one, and ADT
     * messages leave it in place because they only replace ADT entries.
     */
    public function storeAnaesthetistCareProvider(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'anaesthetist_id' => 'required|exists:anaesthetists,id',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($validated['patient_id']);
        $anaesthetist = Anaesthetist::findOrFail($validated['anaesthetist_id']);

        $redirect = redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'anaesthetist',
        ]);

        $alreadyAssigned = (int) $patient->anaesthetist_id === $anaesthetist->id
            || PatientCareProvider::where('patient_id', $patient->id)
                ->where('anaesthetist_id', $anaesthetist->id)
                ->where('is_active', true)
                ->exists();

        if ($alreadyAssigned) {
            return $redirect->with('error', "{$anaesthetist->name} is already on this patient's care team.");
        }

        PatientCareProvider::create([
            'patient_id' => $patient->id,
            'role' => PatientCareProvider::ROLE_CONSULTING,
            'doctor_code' => $anaesthetist->personnel_code ?: 'ANAES-' . $anaesthetist->id,
            'doctor_name' => $anaesthetist->name,
            'anaesthetist_id' => $anaesthetist->id,
            'source' => PatientCareProvider::SOURCE_MANUAL,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        Log::info('Anaesthetist added to care team manually', [
            'patient_id' => $patient->id,
            'anaesthetist_id' => $anaesthetist->id,
            'added_by' => Auth::id(),
        ]);

        return $redirect->with('success', "{$anaesthetist->name} added to the care team.");
    }

    /**
     * Remove a manually added care provider. ADT-sourced providers are left
     * alone so the next ADT message stays the source of truth.
     */
    public function destroyCareProvider(PatientCareProvider $careProvider)
    {
        $patientId = $careProvider->patient_id;

        // Back to the tab the provider is listed on
        $redirect = redirect()->route('ward.patient-details', [
            'patient_id' => $patientId,
            'active_tab' => $careProvider->anaesthetist_id ? 'anaesthetist' : 'careprovider',
        ]);

        if ($careProvider->source !== PatientCareProvider::SOURCE_MANUAL) {
            return $redirect->with('error', 'Only manually added care providers can be removed.');
        }

        $name = $careProvider->display_name;
        $careProvider->update(['is_active' => false]);

        Log::info('Care provider removed', [
            'care_provider_id' => $careProvider->id,
            'patient_id' => $patientId,
            'removed_by' => Auth::id(),
        ]);

        return $redirect->with('success', "{$name} removed from the care team.");
    }

    /**
     * Schedule a discharge for a future date/time instead of discharging now.
     *
     * The patient stays in the bed with status "pending_discharge" so the ward
     * can plan ahead, and the bedside patient app shows the expected date.
     */
    public function scheduleDischarge(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'expected_discharge_at' => 'required|date',
            'discharge_notes' => 'nullable|string',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($validated['patient_id']);

        $redirect = redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'discharge',
        ]);

        if (!in_array($patient->status, [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])) {
            return $redirect->with('error', 'Only admitted patients can have a discharge scheduled.');
        }

        $expectedAt = now()->parse($validated['expected_discharge_at']);

        $patient->update([
            'status' => Patient::STATUS_PENDING_DISCHARGE,
            'pending_discharge_at' => now(),
            'expected_discharge_at' => $expectedAt,
        ]);

        // Notes live on the admission log, matching how an actual discharge records them.
        AdmissionLog::create([
            'patient_id' => $patient->id,
            'ward_id' => $patient->ward_id,
            'user_id' => Auth::id(),
            'bed_number' => $patient->bed_number,
            'action' => 'discharge_scheduled',
            'patient_name' => $patient->name,
            'mrn' => $patient->mrn,
            'consultant_name' => $patient->consultant?->name,
            'nurse_name' => $patient->nurse?->name,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'notes' => trim('Scheduled for ' . $expectedAt->format('j M Y, H:i')
                . ($validated['discharge_notes'] ? ' | ' . $validated['discharge_notes'] : '')),
            'source' => 'manual',
        ]);

        Log::info('Discharge scheduled', [
            'patient_id' => $patient->id,
            'expected_discharge_at' => $expectedAt->toDateTimeString(),
            'scheduled_by' => Auth::id(),
        ]);

        return $redirect->with(
            'success',
            'Discharge scheduled for ' . $expectedAt->format('j M Y, H:i') . '.'
        );
    }

    /**
     * Cancel a scheduled discharge and put the patient back to admitted.
     */
    public function cancelScheduledDischarge(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($validated['patient_id']);

        $redirect = redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'discharge',
        ]);

        if ($patient->status !== Patient::STATUS_PENDING_DISCHARGE) {
            return $redirect->with('error', 'This patient does not have a scheduled discharge.');
        }

        $patient->update([
            'status' => Patient::STATUS_ADMITTED,
            'pending_discharge_at' => null,
            'expected_discharge_at' => null,
        ]);

        Log::info('Scheduled discharge cancelled', [
            'patient_id' => $patient->id,
            'cancelled_by' => Auth::id(),
        ]);

        return $redirect->with('success', 'Scheduled discharge cancelled. Patient is admitted again.');
    }

    public function dischargePatient(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'discharge_reason' => 'nullable|string',
            'discharge_notes' => 'nullable|string',
            'discharged_at' => 'nullable|date',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);

        // Accept both admitted and pending_discharge status
        if (!in_array($patient->status, ['admitted', 'pending_discharge'])) {
            return redirect()->route('ward.patient-details', [
                'patient_id' => $patient->id,
                'active_tab' => 'discharge',
            ])
                ->with('error', 'Only admitted or pending discharge patients can be discharged from the ward.');
        }

        $dischargedAt = $request->discharged_at ? now()->parse($request->discharged_at) : now();

        $wardId = $patient->ward_id;
        $bedNumber = $patient->bed_number;
        $consultant = $patient->consultant;
        $nurse = $patient->nurse;
        $ward = $patient->ward;

        // EKad: Push discharge screen with vacant/discharged values
        // EKad: Explicit Push DISABLED (Handled by BedObserver -> pushVacant)
        /*
        try {
            // Get bed before it is unassigned
            $bed = $patient->bed;
            if ($bed) {
                $ekadService = new EkadService();
                // Push discharge discharge payload: vacant patient, discharged MRN, clear other fields
                $ekadService->pushPatientInfo($patient, $bed, [
                    'patient_name' => 'vacant',
                    'mrn' => 'discharged',
                    'doctor' => '-',
                    'nurse' => '-',
                    'anaesthetist' => '-',
                    'diet_type' => '-',
                    // Keep actual bed number
                ], 'Discharge');
                Log::info('EKad: Pushed discharge screen', ['patient_id' => $patient->id, 'bed_id' => $bed->id]);
            }
        } catch (\Exception $e) {
            Log::warning('EKad discharge push failed', ['error' => $e->getMessage()]);
        }
        */

        // Update patient record
        $patient->update([
            'status' => 'discharged',
            'ward_id' => null,
            'bed_number' => null,
        ]);

        // Check for pending prebook (prebook_pending) waiting for this bed
        $pendingPrebook = Patient::where('ward_id', $wardId)
            ->where('target_bed_number', $bedNumber)
            ->where('is_active', true)
            ->where('status', 'prebook_pending')
            ->first();

        if ($pendingPrebook) {
            // Activate the pending prebook - assign the bed and change status to prebook
            $pendingPrebook->update([
                'bed_number' => $bedNumber,
                'target_bed_number' => null,
                'status' => 'prebook',
            ]);

            // Update bed to reserved status with the prebook patient
            if ($wardId && $bedNumber) {
                $bed = Bed::where('ward_id', $wardId)
                    ->where('bed_number', $bedNumber)
                    ->first();

                if ($bed) {
                    $bed->update([
                        'status' => 'reserved',
                        'patient_id' => $pendingPrebook->id,
                    ]);
                }
            }

            // Log the prebook activation
            AdmissionLog::create([
                'patient_id' => $pendingPrebook->id,
                'ward_id' => $wardId,
                'user_id' => Auth::id(),
                'bed_number' => $bedNumber,
                'action' => 'prebook-activated',
                'patient_name' => $pendingPrebook->name,
                'mrn' => $pendingPrebook->mrn,
                'notes' => 'Prebook activated after discharge of ' . $patient->name,
                'source' => 'manual',
            ]);

            Log::info('Pending prebook activated after discharge', [
                'discharged_patient_id' => $patient->id,
                'prebook_patient_id' => $pendingPrebook->id,
                'ward_id' => $wardId,
                'bed_number' => $bedNumber,
            ]);
        } else {
            // No pending prebook - free the bed
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
            'source' => 'manual',
        ]);

        Log::info('Patient discharged from ward', [
            'patient_id' => $patient->id,
            'ward_id' => $wardId,
            'ward_name' => $ward ? $ward->ward_name : null,
            'bed_number' => $bedNumber,
            'discharged_at' => $dischargedAt,
            'pending_prebook_activated' => $pendingPrebook !== null,
            'user_id' => Auth::id(),
        ]);

        $successMessage = 'Patient discharged from ward ' . ($ward ? $ward->ward_name : '') . '.';
        if ($pendingPrebook) {
            $successMessage .= ' Pending prebook for ' . $pendingPrebook->name . ' has been activated.';
        }

        return redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'discharge',
        ])
            ->with('success', $successMessage);
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
            'fall_risk' => 'nullable|string',
            'isolation_type' => 'nullable|string',
            'nbm' => 'nullable|boolean',
            'diet_types' => 'nullable|array',
            'diet_types.*' => 'nullable|string|max:20',
            'feeding_routes' => 'nullable|array',
            'feeding_routes.*' => ['string', Rule::in(array_keys(Patient::FEEDING_ROUTES))],
            'diet_orders' => 'nullable|string|max:1000',
            'allergies_kept' => 'nullable|array',
            'allergies_kept.*' => 'nullable|string',
            'new_allergy' => 'nullable|string|max:100',
            'hgt_enabled' => 'nullable|boolean',
            'hgt_frequency' => 'nullable|string|in:bd,tds,qid,pid',
        ]);

        $patient = Patient::where('is_active', true)->findOrFail($request->patient_id);
        $manual = fn (string $field) => PatientInfoSources::isManual($field);

        // Only fields the ward manages are written. ADT-managed ones are left exactly as the
        // feed set them (previously saving this form cleared diet, fall risk and allergies).
        $changes = [
            'hgt_enabled' => $request->boolean('hgt_enabled'),
            'hgt_frequency' => $request->hgt_frequency,
        ];

        if ($manual('nursing_level') && $request->has('nursing_level')) {
            $changes['nursing_level'] = $request->nursing_level;
        }

        if ($manual('isolation') && $request->has('isolation_type')) {
            $changes['isolation_type'] = $request->isolation_type;
        }

        if ($manual('fall_risk') && $request->has('fall_risk')) {
            $changes['fall_risk'] = $request->fall_risk;
        }

        // The *_managed markers confirm the editable section was on the page; an empty
        // selection would otherwise be indistinguishable from a section that was not shown
        if ($manual('diet') && $request->has('diet_managed')) {
            $dietTypes = collect($request->input('diet_types', []))
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->reject(fn ($code) => $code === '' || in_array($code, Patient::NBM_DIET_CODES, true));

            // NBM is kept as a diet code so the bed box, EKad and patient screens pick it up
            if ($request->boolean('nbm')) {
                $dietTypes->prepend(Patient::NBM_DIET_CODES[0]);
            }

            $changes['diet_types'] = $dietTypes->unique()->values()->all() ?: null;
            $changes['feeding_routes'] = array_values(array_unique($request->input('feeding_routes', []))) ?: null;
            $changes['diet_orders'] = $request->diet_orders;
        }

        if ($manual('allergies') && $request->has('allergies_managed')) {
            // Kept entries come back exactly as stored (ADT entries keep their status)
            $allergies = collect($request->input('allergies_kept', []))
                ->map(fn ($entry) => json_decode((string) $entry, true))
                ->filter(fn ($entry) => $entry !== null && $entry !== '');

            $newAllergy = trim((string) $request->input('new_allergy', ''));
            if ($newAllergy !== '') {
                $allergies->push($newAllergy);
            }

            $changes['allergies'] = $allergies->values()->all() ?: null;
        }

        $patient->update($changes);

        Log::info('Patient clinical indicators updated', [
            'patient_id' => $patient->id,
            'fields' => array_keys($changes),
            'nursing_level' => $patient->nursing_level,
            'diet_types' => $patient->diet_types,
            'feeding_routes' => $patient->feeding_routes,
            'fall_risk' => $patient->fall_risk,
            'isolation_type' => $patient->isolation_type,
            'allergies' => $patient->allergies,
            'hgt_enabled' => $patient->hgt_enabled,
            'hgt_frequency' => $patient->hgt_frequency,
            'user_id' => Auth::id(),
        ]);

        // EKad: Explicitly push update for clinical changes (Observer might race or fail)
        try {
            if ($patient->isAdmitted() && $patient->bed) {
                // Import class or use full path
                $ekadService = new EkadService();
                $ekadService->pushPatientInfo($patient, $patient->bed, [], 'Update Information');
                Log::info('EKad: Manual push trigger for clinical update', ['patient_id' => $patient->id]);
            }
        } catch (\Exception $e) {
            Log::warning('EKad clinical update push failed', ['error' => $e->getMessage()]);
        }

        return redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'additional',
        ])
            ->with('success', 'Clinical indicators updated successfully.');
    }

    /**
     * Save a new sugar (HGT) reading for a patient
     */
    public function saveSugarReading(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'value' => 'required|numeric|min:0|max:50',
            'notes' => 'nullable|string|max:255',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        $reading = \App\Models\SugarReading::create([
            'patient_id' => $patient->id,
            'value' => $request->value,
            'frequency' => $patient->hgt_frequency,
            'notes' => $request->notes,
            'recorded_by' => Auth::id(),
            'recorded_at' => now(),
        ]);

        Log::info('HGT reading recorded', [
            'patient_id' => $patient->id,
            'value' => $reading->value,
            'frequency' => $reading->frequency,
            'user_id' => Auth::id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'HGT reading recorded successfully.',
                'reading' => [
                    'id' => $reading->id,
                    'value' => $reading->value,
                    'recorded_at' => $reading->recorded_at->format('M d H:i'),
                    'status' => $reading->isLow() ? 'Low' : ($reading->isHigh() ? 'High' : 'Normal'),
                    'status_color' => $reading->getStatusColor(),
                ],
            ]);
        }

        return redirect()->route('ward.patient-details', [
            'patient_id' => $patient->id,
            'active_tab' => 'additional',
        ])->with('success', 'HGT reading recorded successfully.');
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
     * Get ward notifications for the notification panel
     */
    public function getNotifications(Request $request)
    {
        $wardId = $request->query('ward_id');

        if (!$wardId) {
            return response()->json(['error' => 'Ward ID required'], 400);
        }

        $pendingNotifications = WardNotification::forWard($wardId)
            ->pending()
            ->with('patient:id,name,mrn')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'type_label' => $this->notificationTypeLabel($notification->type),
                    'category' => $notification->category,
                    'severity' => $notification->severity,
                    'severity_label' => $notification->severity_label,
                    'severity_bg_class' => $notification->severity_bg_class,
                    'severity_border_class' => $notification->severity_border_class,
                    'message' => $notification->message,
                    'ews_score' => $notification->ews_score,
                    'bed_number' => $notification->bed_number,
                    'patient_name' => $notification->patient->name ?? 'Unknown',
                    'patient_mrn' => $notification->patient->mrn ?? 'N/A',
                    'created_at' => $notification->created_at->diffForHumans(),
                    'created_at_full' => $notification->created_at->format('Y-m-d H:i:s'),
                ];
            });

        $respondedNotifications = WardNotification::forWard($wardId)
            ->responded()
            ->with(['patient:id,name,mrn', 'responder:id,name'])
            ->orderBy('responded_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'type_label' => $this->notificationTypeLabel($notification->type),
                    'category' => $notification->category,
                    'severity' => $notification->severity,
                    'message' => $notification->message,
                    'ews_score' => $notification->ews_score,
                    'bed_number' => $notification->bed_number,
                    'patient_name' => $notification->patient->name ?? 'Unknown',
                    'responded_at' => $notification->responded_at->diffForHumans(),
                    'responded_by' => $notification->responder->name ?? 'Unknown',
                ];
            });

        return response()->json([
            'pending' => $pendingNotifications,
            'pending_count' => $pendingNotifications->count(),
            'responded' => $respondedNotifications,
        ]);
    }

    /**
     * Mark a notification as responded
     */
    /**
     * Human-friendly badge text for a notification type.
     */
    private function notificationTypeLabel(?string $type): string
    {
        return match ($type) {
            WardNotification::TYPE_EWS => 'EWS',
            WardNotification::TYPE_PATIENT_REQUEST => 'PATIENT CALL',
            default => strtoupper(str_replace('_', ' ', (string) $type)),
        };
    }

    public function respondNotification(Request $request, WardNotification $notification)
    {
        if ($notification->status === WardNotification::STATUS_RESPONDED) {
            return response()->json(['error' => 'Notification already responded'], 400);
        }

        $notification->update([
            'status' => WardNotification::STATUS_RESPONDED,
            'responded_at' => now(),
            'responded_by' => Auth::id(),
        ]);

        Log::info('Notification responded', [
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'patient_id' => $notification->patient_id,
            'responded_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as responded',
        ]);
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
     * Get the background class for bed box based on EWS score
     * Returns null for normal scores (<=2) to keep default gender-based coloring
     */
    private function getEwsBgClass(?int $score, bool $hasVitals): ?string
    {
        if (!$hasVitals || $score === null || $score <= 2) {
            return null; // Normal - keep default pink/blue based on gender
        }

        if ($score >= 5) {
            return 'ews-urgent'; // Red theme
        } elseif ($score >= 3) {
            return 'ews-warning'; // Yellow theme
        }

        return null;
    }

    /**
     * Get patients grouped by consultant
     */
    /**
     * Get consultants and their patients for the ward dashboard bottom bar
     * Includes all doctors from care providers with their roles:
     * - Attending (PV1-7): Primary physician responsible for patient care
     * - Referring (PV1-8): Physician who referred the patient
     * - Consulting (PV1-9): Additional specialists consulted
     */
    private function getConsultantPatients($wardId)
    {
        $consultants = [];

        // Consider both currently admitted and prebooked patients in this ward
        $patients = Patient::where('ward_id', $wardId)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->with(['consultant', 'activeCareProviders.consultant'])
            ->get();

        Log::info('getConsultantPatients Debug', [
            'ward_id' => $wardId,
            'patient_count' => $patients->count(),
        ]);

        // Helper function to add patient to consultant with role
        $addPatientToConsultant = function ($consultantId, $consultantName, $patient, $role) use (&$consultants) {
            if (!$consultantId || !$consultantName) {
                return;
            }

            if (!isset($consultants[$consultantId])) {
                $consultants[$consultantId] = [
                    'id' => $consultantId,
                    'name' => $consultantName,
                    'patients' => []
                ];
            }

            // Check if patient already added with this role
            $patientKey = $patient->id . '_' . $role;
            $patientExists = false;
            foreach ($consultants[$consultantId]['patients'] as $p) {
                if (($p['id'] . '_' . $p['role']) === $patientKey) {
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
                    'role' => $role,
                ];
            }
        };

        foreach ($patients as $patient) {
            /** @var \App\Models\Patient $patient */
            // Add Attending Doctors (PV1-7) - Primary physician
            $attendingDoctors = $patient->activeCareProviders()
                ->where('role', PatientCareProvider::ROLE_ATTENDING)
                ->whereNull('anaesthetist_id')
                ->get();

            foreach ($attendingDoctors as $provider) {
                if ($provider->consultant_id && $provider->consultant) {
                    $addPatientToConsultant(
                        $provider->consultant_id,
                        $provider->consultant->name,
                        $patient,
                        'attending'
                    );
                } elseif ($provider->doctor_name || $provider->doctor_code) {
                    // Use doctor code as ID for unlinked doctors
                    $doctorKey = 'unlinked_' . ($provider->doctor_code ?: md5($provider->doctor_name));
                    $addPatientToConsultant(
                        $doctorKey,
                        $provider->doctor_name ?: $provider->doctor_code,
                        $patient,
                        'attending'
                    );
                }
            }

            // Add Referring Doctors (PV1-8)
            $referringDoctors = $patient->activeCareProviders()
                ->where('role', PatientCareProvider::ROLE_REFERRING)
                ->whereNull('anaesthetist_id')
                ->get();

            foreach ($referringDoctors as $provider) {
                if ($provider->consultant_id && $provider->consultant) {
                    $addPatientToConsultant(
                        $provider->consultant_id,
                        $provider->consultant->name,
                        $patient,
                        'referring'
                    );
                } elseif ($provider->doctor_name || $provider->doctor_code) {
                    $doctorKey = 'unlinked_' . ($provider->doctor_code ?: md5($provider->doctor_name));
                    $addPatientToConsultant(
                        $doctorKey,
                        $provider->doctor_name ?: $provider->doctor_code,
                        $patient,
                        'referring'
                    );
                }
            }

            // Add Consulting Doctors (PV1-9)
            $consultingDoctors = $patient->activeCareProviders()
                ->where('role', PatientCareProvider::ROLE_CONSULTING)
                ->whereNull('anaesthetist_id')
                ->get();

            foreach ($consultingDoctors as $provider) {
                if ($provider->consultant_id && $provider->consultant) {
                    $addPatientToConsultant(
                        $provider->consultant_id,
                        $provider->consultant->name,
                        $patient,
                        'consulting'
                    );
                } elseif ($provider->doctor_name || $provider->doctor_code) {
                    $doctorKey = 'unlinked_' . ($provider->doctor_code ?: md5($provider->doctor_name));
                    $addPatientToConsultant(
                        $doctorKey,
                        $provider->doctor_name ?: $provider->doctor_code,
                        $patient,
                        'consulting'
                    );
                }
            }

            // Add primary consultant (for backward compatibility) - treat as attending
            if ($patient->consultant_id && $patient->consultant) {
                // Check if this patient already has this consultant as attending
                $alreadyHasAttending = false;
                if (isset($consultants[$patient->consultant_id])) {
                    foreach ($consultants[$patient->consultant_id]['patients'] as $p) {
                        if ($p['id'] === $patient->id && $p['role'] === 'attending') {
                            $alreadyHasAttending = true;
                            break;
                        }
                    }
                }

                if (!$alreadyHasAttending) {
                    $addPatientToConsultant(
                        $patient->consultant_id,
                        $patient->consultant->name,
                        $patient,
                        'attending'
                    );
                }
            }

            // Add consultants from bed_consultant pivot (treat as consulting)
            $bed = Bed::where('ward_id', $wardId)
                ->where('patient_id', $patient->id)
                ->with('consultants')
                ->first();

            if ($bed && $bed->consultants) {
                foreach ($bed->consultants as $consultant) {
                    $addPatientToConsultant(
                        $consultant->id,
                        $consultant->name,
                        $patient,
                        'consulting'
                    );
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

        // Get current shift to determine which schedule to look at
        $currentShift = ShiftSetting::getCurrentShift($wardId);

        // If for some reason no shift is active (unlikely with defaults), return empty
        if (!$currentShift) {
            return [];
        }

        // Fetch assignments for this ward, today, and current shift
        // We need the nurse and the bed (and patient in that bed)
        $assignments = WardScheduleAssignment::where('ward_id', $wardId)
            ->where('scheduled_date', now()->toDateString())
            ->where('shift', $currentShift->shift_code)
            ->with(['nurse', 'bed.patient'])
            ->get();

        foreach ($assignments as $assignment) {
            // Skip if nurse is missing (shouldn't happen with valid FKs)
            if (!$assignment->nurse) {
                continue;
            }

            $nurseId = $assignment->nurse_id;
            $nurseName = $assignment->nurse->name;

            if (!isset($nurses[$nurseId])) {
                $nurses[$nurseId] = [
                    'id' => $nurseId,
                    'name' => $nurseName,
                    'patients' => []
                ];
            }

            $bed = $assignment->bed;
            if ($bed) {
                // Determine patient details
                $patientName = 'Empty Bed';
                $mrn = '-';
                $patientId = null;

                // Check if bed has a patient assigned (via syncBedsWithPatients logic)
                // Note: bed->patient relationship relies on patient_id FK in beds table
                if ($bed->patient && $bed->patient->is_active) {
                    $patientName = $bed->patient->name;
                    $mrn = $bed->patient->mrn;
                    $patientId = $bed->patient->id;
                }

                $nurses[$nurseId]['patients'][] = [
                    'id' => $patientId,
                    'name' => $patientName,
                    'mrn' => $mrn,
                    'bed_number' => $bed->bed_number,
                ];
            }
        }

        // Sort nurses by name for consistency
        usort($nurses, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return array_values($nurses);
    }

    /**
     * Get patients grouped by anaesthetist
     */
    /**
     * Get anaesthetists and their patients for the ward dashboard bottom bar
     * Includes anaesthetists from care providers (any role with anaesthetist link)
     * and old anaesthetist field for backward compatibility
     */
    private function getAnaesthetistPatients($wardId)
    {
        $anaesthetists = [];

        // Include both admitted and prebooked patients that have an anaesthetist assigned
        $patients = Patient::where('ward_id', $wardId)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
            ->with(['anaesthetist', 'activeCareProviders.anaesthetist'])
            ->get();

        foreach ($patients as $patient) {
            /** @var \App\Models\Patient $patient */
            // Get anaesthetists from care providers (consulting or attending role with anaesthetist link)
            $anaesthetistProviders = $patient->activeCareProviders()
                ->whereNotNull('anaesthetist_id')
                ->get();

            foreach ($anaesthetistProviders as $provider) {
                if ($provider->anaesthetist) {
                    $anaesthetistId = $provider->anaesthetist_id;
                    $anaesthetistName = $provider->anaesthetist->name;

                    if (!isset($anaesthetists[$anaesthetistId])) {
                        $anaesthetists[$anaesthetistId] = [
                            'id' => $anaesthetistId,
                            'name' => $anaesthetistName,
                            'patients' => []
                        ];
                    }

                    // Check if patient already added to avoid duplicates
                    $patientExists = false;
                    foreach ($anaesthetists[$anaesthetistId]['patients'] as $p) {
                        if ($p['id'] === $patient->id) {
                            $patientExists = true;
                            break;
                        }
                    }

                    if (!$patientExists) {
                        $anaesthetists[$anaesthetistId]['patients'][] = [
                            'id' => $patient->id,
                            'name' => $patient->name,
                            'mrn' => $patient->mrn,
                            'bed_number' => $patient->bed_number,
                        ];
                    }
                }
            }

            // Also include from old anaesthetist_id field for backward compatibility
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

                // Check if patient already added to avoid duplicates
                $patientExists = false;
                foreach ($anaesthetists[$anaesthetistId]['patients'] as $p) {
                    if ($p['id'] === $patient->id) {
                        $patientExists = true;
                        break;
                    }
                }

                if (!$patientExists) {
                    $anaesthetists[$anaesthetistId]['patients'][] = [
                        'id' => $patient->id,
                        'name' => $patient->name,
                        'mrn' => $patient->mrn,
                        'bed_number' => $patient->bed_number,
                    ];
                }
            }
        }

        return array_values($anaesthetists);
    }

    /**
     * Calculate Early Warning Score (EWS) based on vital signs
     * Supports multiple scoring systems: EWS IHH, NEWS2, NEWS, MEWS, PEWS
     */
    public function calculateEWS(?VitalSign $vitals, string $ewsSystem = 'ews_ihh'): array
    {
        if (!$vitals) {
            return [
                'score' => null,
                'has_vitals' => false,
            ];
        }

        // Route to appropriate scoring system
        switch ($ewsSystem) {
            case 'ews_ihh':
                return $this->calculateEWS_IHH($vitals);
            case 'news2':
            case 'news':
                return $this->calculateEWS_NEWS2($vitals);
            case 'mews':
                return $this->calculateEWS_MEWS($vitals);
            case 'pews':
                return $this->calculateEWS_PEWS($vitals);
            default:
                return $this->calculateEWS_IHH($vitals);
        }
    }

    /**
     * EWS IHH (IJN Hospital Hijau) scoring system
     * Yellow Zone (Score 1): Warning - Attention required
     * Pink Zone (Score 2): Activate Trigger Protocol
     */
    private function calculateEWS_IHH(VitalSign $vitals): array
    {
        $score = 0;

        // Pulse Rate scoring (bpm)
        // Yellow Zone (1): 100-120 (high), 41-59 (low)
        // Pink Zone (2): >120, ≤40
        if ($vitals->pulse_rate !== null) {
            $pr = $vitals->pulse_rate;
            if ($pr > 120 || $pr <= 40) {
                $score += 2;
            } elseif (($pr >= 100 && $pr <= 120) || ($pr >= 41 && $pr <= 59)) {
                $score += 1;
            }
        }

        // Respiratory Rate scoring (breaths per minute)
        // Yellow Zone (1): 21-24 (high), 9-11 (low)
        // Pink Zone (2): >25, ≤8
        if ($vitals->respiratory_rate !== null) {
            $rr = $vitals->respiratory_rate;
            if ($rr > 25 || $rr <= 8) {
                $score += 2;
            } elseif (($rr >= 21 && $rr <= 24) || ($rr >= 9 && $rr <= 11)) {
                $score += 1;
            }
        }

        // Systolic Blood Pressure scoring (mmHg)
        // Yellow Zone (1): 160-199 (high), 91-100 (low)
        // Pink Zone (2): >200, ≤90
        if ($vitals->systolic_bp !== null) {
            $sbp = $vitals->systolic_bp;
            if ($sbp > 200 || $sbp <= 90) {
                $score += 2;
            } elseif (($sbp >= 160 && $sbp <= 199) || ($sbp >= 91 && $sbp <= 100)) {
                $score += 1;
            }
        }

        // SpO2 scoring (oxygen saturation %)
        // Yellow Zone (1): 92-95 (low only)
        // Pink Zone (2): ≤91
        if ($vitals->spo2 !== null) {
            $spo2 = $vitals->spo2;
            if ($spo2 <= 91) {
                $score += 2;
            } elseif ($spo2 >= 92 && $spo2 <= 95) {
                $score += 1;
            }
        }

        // Temperature scoring (°C)
        // Yellow Zone (1): 38-38.9 (high), 35.1-35.9 (low)
        // Pink Zone (2): ≥39, ≤35
        if ($vitals->temperature !== null) {
            $temp = (float) $vitals->temperature;
            if ($temp >= 39 || $temp <= 35) {
                $score += 2;
            } elseif (($temp >= 38 && $temp <= 38.9) || ($temp >= 35.1 && $temp <= 35.9)) {
                $score += 1;
            }
        }

        return [
            'score' => $score,
            'has_vitals' => true,
        ];
    }

    /**
     * NEWS2 (National Early Warning Score 2) scoring system
     */
    private function calculateEWS_NEWS2(VitalSign $vitals): array
    {
        $score = 0;

        // Respiratory Rate scoring (breaths per minute)
        if ($vitals->respiratory_rate !== null) {
            $rr = $vitals->respiratory_rate;
            if ($rr <= 8)
                $score += 3;
            elseif ($rr <= 11)
                $score += 1;
            elseif ($rr <= 20)
                $score += 0;
            elseif ($rr <= 24)
                $score += 2;
            else
                $score += 3;
        }

        // SpO2 scoring (oxygen saturation %)
        if ($vitals->spo2 !== null) {
            $spo2 = $vitals->spo2;
            if ($spo2 <= 91)
                $score += 3;
            elseif ($spo2 <= 93)
                $score += 2;
            elseif ($spo2 <= 95)
                $score += 1;
            else
                $score += 0;
        }

        // Systolic Blood Pressure scoring (mmHg)
        if ($vitals->systolic_bp !== null) {
            $sbp = $vitals->systolic_bp;
            if ($sbp <= 90)
                $score += 3;
            elseif ($sbp <= 100)
                $score += 2;
            elseif ($sbp <= 110)
                $score += 1;
            elseif ($sbp <= 219)
                $score += 0;
            else
                $score += 3;
        }

        // Pulse Rate scoring (beats per minute)
        if ($vitals->pulse_rate !== null) {
            $hr = $vitals->pulse_rate;
            if ($hr <= 40)
                $score += 3;
            elseif ($hr <= 50)
                $score += 1;
            elseif ($hr <= 90)
                $score += 0;
            elseif ($hr <= 110)
                $score += 1;
            elseif ($hr <= 130)
                $score += 2;
            else
                $score += 3;
        }

        // Temperature scoring (°C)
        if ($vitals->temperature !== null) {
            $temp = (float) $vitals->temperature;
            if ($temp <= 35.0)
                $score += 3;
            elseif ($temp <= 36.0)
                $score += 1;
            elseif ($temp <= 38.0)
                $score += 0;
            elseif ($temp <= 39.0)
                $score += 1;
            else
                $score += 2;
        }

        return [
            'score' => $score,
            'has_vitals' => true,
        ];
    }

    /**
     * MEWS (Modified Early Warning Score) scoring system
     */
    private function calculateEWS_MEWS(VitalSign $vitals): array
    {
        $score = 0;

        // Systolic Blood Pressure
        if ($vitals->systolic_bp !== null) {
            $sbp = $vitals->systolic_bp;
            if ($sbp <= 70)
                $score += 3;
            elseif ($sbp <= 80)
                $score += 2;
            elseif ($sbp <= 100)
                $score += 1;
            elseif ($sbp <= 199)
                $score += 0;
            else
                $score += 2;
        }

        // Heart Rate
        if ($vitals->pulse_rate !== null) {
            $hr = $vitals->pulse_rate;
            if ($hr <= 40)
                $score += 2;
            elseif ($hr <= 50)
                $score += 1;
            elseif ($hr <= 100)
                $score += 0;
            elseif ($hr <= 110)
                $score += 1;
            elseif ($hr <= 129)
                $score += 2;
            else
                $score += 3;
        }

        // Respiratory Rate
        if ($vitals->respiratory_rate !== null) {
            $rr = $vitals->respiratory_rate;
            if ($rr < 9)
                $score += 2;
            elseif ($rr <= 14)
                $score += 0;
            elseif ($rr <= 20)
                $score += 1;
            elseif ($rr <= 29)
                $score += 2;
            else
                $score += 3;
        }

        // Temperature
        if ($vitals->temperature !== null) {
            $temp = (float) $vitals->temperature;
            if ($temp < 35)
                $score += 2;
            elseif ($temp <= 38.4)
                $score += 0;
            else
                $score += 2;
        }

        return [
            'score' => $score,
            'has_vitals' => true,
        ];
    }

    /**
     * PEWS (Pediatric Early Warning Score) scoring system
     */
    private function calculateEWS_PEWS(VitalSign $vitals): array
    {
        $score = 0;

        // Heart Rate (pediatric ranges)
        if ($vitals->pulse_rate !== null) {
            $hr = $vitals->pulse_rate;
            if ($hr < 60 || $hr > 150)
                $score += 2;
            elseif ($hr < 70 || $hr > 130)
                $score += 1;
        }

        // Respiratory Rate (pediatric ranges)
        if ($vitals->respiratory_rate !== null) {
            $rr = $vitals->respiratory_rate;
            if ($rr < 10 || $rr > 40)
                $score += 2;
            elseif ($rr < 15 || $rr > 30)
                $score += 1;
        }

        // SpO2
        if ($vitals->spo2 !== null) {
            $spo2 = $vitals->spo2;
            if ($spo2 < 90)
                $score += 2;
            elseif ($spo2 < 95)
                $score += 1;
        }

        // Temperature
        if ($vitals->temperature !== null) {
            $temp = (float) $vitals->temperature;
            if ($temp < 36 || $temp > 39)
                $score += 2;
            elseif ($temp < 36.5 || $temp > 38)
                $score += 1;
        }

        return [
            'score' => $score,
            'has_vitals' => true,
        ];
    }

    private function resolvePatientDetailsTabs(Request $request, &$activeTab)
    {
        $defaultTabs = [
            'info' => true,
            'additional' => true,
            'vitals' => true,
            'io' => true,
            'medications' => false,
            'movement' => true,
            'careprovider' => true, // shown as "Consultant"
            'anaesthetist' => true,
            'nurses' => true, // from the ward roster
            'infusion' => true,
            'transfer' => true,
            'discharge' => true,
        ];

        $patientDetailsTabs = $defaultTabs;

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

        if (Auth::check()) {
            $settings = WardDashboardSetting::where('user_id', Auth::id())->first();
            if ($settings && is_array($settings->patient_details_tabs)) {
                $patientDetailsTabs = array_merge($patientDetailsTabs, $settings->patient_details_tabs);
            }
        }

        return $patientDetailsTabs;
    }

    private function getEnrichedClinicalIndicatorOptions($options)
    {
        if (Auth::check()) {
            $settings = WardDashboardSetting::where('user_id', Auth::id())->first();
            if ($settings && is_array($settings->clinical_indicator_options)) {
                $options = array_merge($options, $settings->clinical_indicator_options);
            }
        }

        // Load diet types and isolation types from database and merge into options
        $dbDietTypes = DietType::where('is_active', true)->orderBy('name')->get();
        $dbIsolationTypes = IsolationType::where('is_active', true)->orderBy('name')->get();

        if ($dbDietTypes->isNotEmpty()) {
            $options['diet_type'] = $dbDietTypes->map(function ($dt) {
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
            // Prepend "None" option to ensure proper default value
            $noneOption = [['value' => 'none', 'label' => 'No Isolation Precaution', 'color' => 'bg-gray-100 text-gray-600']];
            $dbOptions = $dbIsolationTypes->map(function ($it) {
                return [
                    'value' => $it->code,
                    'label' => $it->name,
                    'color' => in_array(strtoupper($it->code), ['COVID', 'TB', 'AIR', 'AIRBORNE'])
                        ? 'bg-red-100 text-red-700'
                        : 'bg-yellow-100 text-yellow-700',
                ];
            })->toArray();
            $options['isolation_type'] = array_merge($noneOption, $dbOptions);
        }

        return $options;
    }

    private function getPatientDisplayNames($patient)
    {
        $names = [
            'consultant' => 'Not Assigned',
            'nurse' => 'Not Assigned',
            'anaesthetist' => 'Not Assigned',
        ];

        if (!$patient) {
            return $names;
        }

        // Consultant
        $attendingDoctor = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();
        $names['consultant'] = $attendingDoctor ? $attendingDoctor->display_name :
            ($patient->consultant ? $patient->consultant->name : 'Not Assigned');

        // Anaesthetist
        if ($patient->anaesthetist) {
            $names['anaesthetist'] = $patient->anaesthetist->name;
        } else {
            $anaesthetistProvider = $patient->activeCareProviders()
                ->whereNotNull('anaesthetist_id')
                ->first();

            if ($anaesthetistProvider && $anaesthetistProvider->anaesthetist) {
                $names['anaesthetist'] = $anaesthetistProvider->anaesthetist->name;
            }
        }

        // Nurse
        $currentShift = null;
        $currentShiftCode = null;

        if ($patient->ward_id) {
            $currentShift = ShiftSetting::getCurrentShift($patient->ward_id);
            $currentShiftCode = $currentShift ? $currentShift->shift_code : null;
        }

        if ($currentShiftCode && $patient->bed_number && $patient->ward_id) {
            $bed = Bed::where('ward_id', $patient->ward_id)
                ->where('bed_number', $patient->bed_number)
                ->first();

            if ($bed) {
                $assignment = WardScheduleAssignment::where('bed_id', $bed->id)
                    ->where('scheduled_date', now()->toDateString())
                    ->where('shift', $currentShiftCode)
                    ->with('nurse')
                    ->first();

                if ($assignment && $assignment->nurse) {
                    $names['nurse'] = $assignment->nurse->name;
                }
            }
        }

        return $names;
    }
    private function calculateWardStatistics($wardId, $beds, $admittedPatients)
    {
        if ($beds instanceof \Illuminate\Support\Collection) {
            $beds = $beds->values()->all();
        }

        // Count unique staff assigned to admitted patients
        $uniqueConsultantIds = collect();
        $uniqueNurseIds = collect();
        $uniqueAnaesthetistIds = collect();

        foreach ($admittedPatients as $patient) {
            // Count consultants from care providers (consulting role)
            // Exclude those that are actually anaesthetists (have anaesthetist_id)
            $consultingDoctors = $patient->activeCareProviders()
                ->where('role', PatientCareProvider::ROLE_CONSULTING)
                ->whereNull('anaesthetist_id')
                ->get();

            foreach ($consultingDoctors as $provider) {
                if ($provider->consultant_id) {
                    $uniqueConsultantIds->push($provider->consultant_id);
                }
            }

            // Also count from old consultant_id field for backward compatibility
            if ($patient->consultant_id) {
                $uniqueConsultantIds->push($patient->consultant_id);
            }

            // Get consultants from bed_consultant pivot table
            $bed = Bed::where('ward_id', $wardId)
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

            // Count anaesthetists from care providers (consulting role with anaesthetist link)
            $anaesthetistProviders = $patient->activeCareProviders()
                ->whereNotNull('anaesthetist_id')
                ->get();

            foreach ($anaesthetistProviders as $provider) {
                $uniqueAnaesthetistIds->push($provider->anaesthetist_id);
            }

            // Also count from old anaesthetist_id field for backward compatibility
            if ($patient->anaesthetist_id) {
                $uniqueAnaesthetistIds->push($patient->anaesthetist_id);
            }
        }

        $consultantCount = $uniqueConsultantIds->unique()->count();
        $anaesthetistCount = $uniqueAnaesthetistIds->unique()->count();
        $patientCount = $admittedPatients->count();

        // Calculate nurse count based on schedule for current shift
        $nurseCount = 0;
        if ($wardId) {
            $currentShift = ShiftSetting::getCurrentShift($wardId);
            if ($currentShift) {
                $nurseCount = WardScheduleAssignment::where('ward_id', $wardId)
                    ->where('scheduled_date', now()->toDateString())
                    ->where('shift', $currentShift->shift_code)
                    ->distinct('nurse_id')
                    ->count('nurse_id');
            }
        }

        // Calculate nurse:patient ratio
        $ratio = '0:0';
        if ($patientCount > 0) {
            if ($nurseCount > 0) {
                $gcd = $this->gcd($nurseCount, $patientCount);
                $ratioNurse = $nurseCount / $gcd;
                $ratioPatient = $patientCount / $gcd;
                $ratio = "$ratioNurse:$ratioPatient";
            } else {
                $ratio = "0:$patientCount";
            }
        }

        // Get infusion statistics
        $activeInfusions = Infusion::inWard($wardId)->active()->count();
        $infusionWarnings = Infusion::inWard($wardId)->running()->withWarnings()->count();
        $infusionAlarms = Infusion::inWard($wardId)->alarming()->count();

        return [
            'available' => count(array_filter($beds, fn($bed) => $bed['status'] === 'available')),
            'cleaning' => count(array_filter($beds, fn($bed) => $bed['status'] === 'cleaning')),
            'patients' => $patientCount,
            'consultants' => $consultantCount,
            'anaesthetists' => $anaesthetistCount,
            'nurses' => $nurseCount,
            'ratio' => $ratio,
            'occupancy' => round((count(array_filter($beds, fn($bed) => $bed['status'] === 'occupied')) / max(count($beds), 1)) * 100),
            'infusions' => $activeInfusions,
            'infusion_warnings' => $infusionWarnings,
            'infusion_alarms' => $infusionAlarms,
        ];
    }

    private function resolveDashboardSettings($userSettings)
    {
        $defaultBedBoxDisplay = [
            ['key' => 'patient_name', 'visible' => true, 'order' => 0],
            ['key' => 'consultant', 'visible' => true, 'order' => 1],
            ['key' => 'nurse', 'visible' => true, 'order' => 2],
            ['key' => 'admitted_duration', 'visible' => true, 'order' => 3],
            ['key' => 'ews', 'visible' => true, 'order' => 4],
            ['key' => 'mrn', 'visible' => true, 'order' => 5],
            ['key' => 'admit_button', 'visible' => true, 'order' => 6],
            ['key' => 'prebook_button', 'visible' => true, 'order' => 7],
        ];

        $defaultPatientInfoDisplay = [
            ['key' => 'nursing_level', 'visible' => true],
            ['key' => 'diet_type', 'visible' => true],
            ['key' => 'fall_risk', 'visible' => true],
            ['key' => 'isolation_type', 'visible' => true],
            ['key' => 'allergies', 'visible' => true],
        ];

        $defaultDashboardDisplay = [
            'patient_name_mask' => 'full',
            'fullscreen_mode' => 'medium',
            'fullscreen_text_size' => 'medium',
        ];

        $bedBoxDisplay = $userSettings && is_array($userSettings->bed_box_display)
            ? $userSettings->bed_box_display
            : $defaultBedBoxDisplay;

        $patientInfoDisplay = $userSettings && is_array($userSettings->patient_info_display)
            ? $userSettings->patient_info_display
            : $defaultPatientInfoDisplay;

        $dashboardDisplay = $userSettings && is_array($userSettings->dashboard_display)
            ? array_merge($defaultDashboardDisplay, $userSettings->dashboard_display)
            : $defaultDashboardDisplay;

        return [
            'bedBoxConfig' => collect($bedBoxDisplay)->keyBy('key')->toArray(),
            'patientInfoConfig' => collect($patientInfoDisplay)->keyBy('key')->toArray(),
            'dashboardDisplay' => $dashboardDisplay
        ];
    }

    public function slideshowViewer(Request $request)
    {
        $wardId = $request->query('ward_id');
        $ward = Ward::find($wardId);
        
        if (!$ward) {
            // fallback if no ward provided
            return response('No ward selected for slideshow.', 404);
        }

        $hospitalId = $ward->hospital_id;

        $slideshows = \App\Models\Slideshow::where('hospital_id', $hospitalId)
            ->where(function ($query) use ($wardId) {
                $query->whereNull('ward_id')
                      ->orWhere('ward_id', $wardId);
            })
            ->where('is_active', true)
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Build a clean slides array for JSON encoding
        $allSlides = [];

        // Database-managed slides
        foreach ($slideshows as $slide) {
            $allSlides[] = [
                'url' => \Illuminate\Support\Facades\Storage::url($slide->file_path),
                'name' => pathinfo($slide->file_path, PATHINFO_FILENAME),
                'type' => str_contains($slide->file_type, 'pdf') ? 'pdf' : 'image',
                'scale250' => false,
            ];
        }

        // Static files from base_path('picture') as hospital-wide slides
        $pictureDir = base_path('picture');
        $scale250Files = ['Hosp Org Chart.jpeg', 'Clinical Governance Chart.jpeg'];

        if (\Illuminate\Support\Facades\File::exists($pictureDir)) {
            $files = \Illuminate\Support\Facades\File::files($pictureDir);
            foreach ($files as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                    continue;
                }
                
                $filename = $file->getFilename();
                $allSlides[] = [
                    'url' => route('picture.serve', ['filename' => $filename]),
                    'name' => pathinfo($filename, PATHINFO_FILENAME),
                    'type' => $ext === 'pdf' ? 'pdf' : 'image',
                    'scale250' => in_array($filename, $scale250Files),
                ];
            }
        }

        return view('wards.slideshow-viewer', compact('allSlides', 'ward'));
    }
}
