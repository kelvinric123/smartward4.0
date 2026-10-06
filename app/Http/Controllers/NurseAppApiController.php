<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\BloodTransfusion;
use App\Models\ConsultantOrder;
use App\Models\DietType;
use App\Models\Infusion;
use App\Models\IsolationType;
use App\Models\LabInvestigation;
use App\Models\NurseRosterRequest;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMedication;
use App\Models\PatientMovement;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Services\LabInvestigations;
use App\Services\NurseApp\NurseAppSchedule;
use App\Services\NurseScheduling\NurseRoster;
use App\Services\NurseScheduling\RosterSlot;
use App\Support\ClinicalIndicatorMonitoring;
use App\Support\FluidBalanceChart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API for the QMed Smart Ward Nurse mobile app (nurse_app).
 *
 * Auth flow: nurse logs in with the app username/password configured on the
 * Nurse edit page -> receives a bearer token -> all subsequent calls send
 * `Authorization: Bearer <token>`. Responses are scoped to the beds assigned
 * to that nurse (current-shift schedule assignments, plus patients where the
 * nurse is set as the primary nurse).
 */
class NurseAppApiController extends Controller
{
    /**
     * POST /api/nurse/ping — connectivity test for the app settings screen.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'nurse-app-api',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/nurse/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $nurse = Nurse::where('is_active', true)
            ->whereNotNull('app_username')
            ->whereNotNull('app_password')
            ->where(function ($q) use ($validated) {
                $q->where('app_username', $validated['username'])
                    ->orWhere('registration_number', $validated['username']);
            })
            ->first();

        if (!$nurse || !$nurse->verifyAppPassword($validated['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        $token = $nurse->generateAppToken(72);

        return response()->json([
            'success' => true,
            'token' => $token,
            'nurse' => $this->nursePayload($nurse),
        ]);
    }

    /**
     * POST /api/nurse/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $nurse = $this->authenticate($request);
        if ($nurse) {
            $nurse->invalidateAppToken();
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/nurse/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $nurse = $this->authenticate($request);
        if (!$nurse) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please log in again.',
            ], 401);
        }

        // This nurse's schedule assignments in the roster slot on duty on each
        // ward: after midnight in the night shift, last night's ON (mirrors the
        // web nurse dashboard).
        $currentAssignments = RosterSlot::assignmentsOnDuty($nurse->id);

        // Beds assigned via schedule
        $assignedBedIds = $currentAssignments->pluck('bed_id')->filter()->unique();
        $bedsById = Bed::whereIn('id', $assignedBedIds)->with('ward')->get()->keyBy('id');

        // Patients occupying those beds
        $patients = collect();
        foreach ($bedsById as $bed) {
            $patient = Patient::where('ward_id', $bed->ward_id)
                ->where('bed_number', $bed->bed_number)
                ->where('is_active', true)
                ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
                ->with(['ward', 'consultant', 'nurse', 'latestSugarReading'])
                ->first();
            $patients->push([$bed, $patient]);
        }

        // Fallback: patients where this nurse is set as primary nurse and
        // that are not already covered by a schedule assignment.
        $coveredPatientIds = $patients->map(fn($pair) => $pair[1]?->id)->filter();
        $primaryPatients = Patient::where('nurse_id', $nurse->id)
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->whereNotIn('id', $coveredPatientIds)
            ->with(['ward', 'consultant', 'nurse', 'latestSugarReading'])
            ->get();

        foreach ($primaryPatients as $patient) {
            $bed = Bed::where('ward_id', $patient->ward_id)
                ->where('bed_number', $patient->bed_number)
                ->where('is_active', true)
                ->with('ward')
                ->first();
            $patients->push([$bed, $patient]);
        }

        // What needs doing per patient, for the badges on each bed
        $badges = $this->badgesFor($patients->map(fn($pair) => $pair[1])->filter()->values());

        // Build bed payloads (skip empty schedule beds with no patient only
        // if we have at least something better to show; keep them so the
        // nurse sees their full assignment).
        $beds = [];
        foreach ($patients as [$bed, $patient]) {
            $beds[] = $this->bedPayload($nurse, $bed, $patient, $patient ? ($badges[$patient->id] ?? []) : []);
        }

        usort($beds, fn($a, $b) => strnatcmp((string) ($a['number'] ?? ''), (string) ($b['number'] ?? '')));

        // Resolve the nurse's ward for the header + occupancy stats
        $wardId = $nurse->ward_id
            ?: $currentAssignments->pluck('ward_id')->filter()->first()
            ?: collect($beds)->pluck('ward_id')->filter()->first();
        $ward = $wardId ? Ward::find($wardId) : null;
        $currentSlot = $wardId ? RosterSlot::current($wardId) : null;

        // Ward occupancy
        $occupancy = 0;
        $wardPatientCount = 0;
        if ($ward) {
            $totalBeds = Bed::where('ward_id', $ward->id)->where('is_active', true)->count();
            $wardPatientCount = Patient::where('ward_id', $ward->id)
                ->where('is_active', true)
                ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
                ->count();
            $occupancy = $totalBeds > 0 ? (int) round($wardPatientCount / $totalBeds * 100) : 0;
        }

        $occupied = collect($beds)->whereIn('status', ['occupied', 'reserved'])->count();
        $critical = collect($beds)
            ->filter(fn($b) => $b['ews_has_vitals'] && $b['ews'] !== null && $b['ews'] >= 5)
            ->count();
        $allInfusions = collect($beds)->flatMap(fn($b) => $b['infusions']);
        $alerts = $allInfusions->where('is_warning', true)->count()
            + $allInfusions->where('status', Infusion::STATUS_ALARMING)->count();

        // Workload per bed, scored as the AI Nurse Schedule scores it, and my share of the shift
        $workload = NurseAppSchedule::workload(collect($beds)->pluck('ward_id')->push($ward?->id));
        foreach ($beds as $i => $row) {
            $beds[$i]['workload'] = $workload['beds'][$row['id']] ?? null;
        }
        $myLoad = $workload['loads'][$nurse->id] ?? null;

        return response()->json([
            'nurse' => $this->nursePayload($nurse),
            'current_shift' => $currentSlot ? [
                'shift_code' => $currentSlot['code'],
                'shift_name' => $currentSlot['name'],
            ] : null,
            'ward' => $ward ? [
                'id' => $ward->id,
                'ward_name' => $ward->ward_name,
            ] : null,
            'summary' => [
                'assigned_beds' => count($beds),
                'occupied_beds' => $wardPatientCount,
                'critical_patients' => $critical,
                'active_infusions' => $allInfusions->count(),
                'infusion_alerts' => $alerts,
                'ward_occupancy' => $occupancy,
                'open_orders' => collect($beds)->sum(fn($b) => $b['badges']['orders_open']),
                'doses_overdue' => collect($beds)->sum(fn($b) => $b['badges']['meds_overdue']),
                'pending_alerts' => collect($beds)->sum(fn($b) => $b['badges']['alerts_pending']),
                'labs_to_review' => collect($beds)->sum(fn($b) => $b['badges']['labs_review'] ?? 0),
                // My beds' workload against the shift average on the ward (heavy / even / light)
                'my_load' => $myLoad ? [
                    'score' => $myLoad['score'],
                    'beds' => $myLoad['beds'],
                    'patients' => $myLoad['patients'],
                    'level' => $myLoad['level'],
                    'team_average' => $myLoad['team_average'],
                    'team_size' => $myLoad['team_size'],
                ] : null,
            ],
            'schedule' => $this->schedulePayload($nurse),
            'beds' => $beds,
        ]);
    }

    /**
     * The roster at a glance for the dashboard: today and tomorrow, swaps colleagues
     * asked of me, my requests decided lately, and whether a week of my roster is
     * waiting to be acknowledged (new, or changed since I saw it).
     */
    private function schedulePayload(Nurse $nurse): array
    {
        $days = NurseRoster::days($nurse, now()->startOfDay(), 2);
        $weeks = [NurseRoster::week($nurse, now()), NurseRoster::week($nurse, now()->addWeek())];
        $toAcknowledge = collect($weeks)
            ->filter(fn (array $week) => $week['totals']['shifts'] > 0 && $week['acknowledgement']['state'] !== 'seen')
            ->map(fn (array $week) => ['start' => $week['start'], 'label' => $week['label'], 'state' => $week['acknowledgement']['state']])
            ->values();

        return [
            'today' => $days[0],
            'tomorrow' => $days[1],
            'swaps_to_answer' => NurseRosterRequest::where('colleague_id', $nurse->id)
                ->where('status', NurseRosterRequest::STATUS_AWAITING_COLLEAGUE)
                ->count(),
            'requests_open' => NurseRosterRequest::where('nurse_id', $nurse->id)->open()->count(),
            'requests_decided' => NurseRosterRequest::where('nurse_id', $nurse->id)
                ->whereIn('status', [NurseRosterRequest::STATUS_APPROVED, NurseRosterRequest::STATUS_DECLINED])
                ->where('decided_at', '>=', now()->subDay())
                ->count(),
            'to_acknowledge' => $toAcknowledge->all(),
        ];
    }

    /**
     * Per patient: open consultant orders (and how many STAT), overdue and
     * due-soon doses, today's I/O flag, and unanswered alerts. Four queries
     * for the whole list, whatever its length.
     *
     * @return array<int, array>
     */
    private function badgesFor(\Illuminate\Support\Collection $patients): array
    {
        if ($patients->isEmpty()) {
            return [];
        }

        $ids = $patients->pluck('id')->all();

        $orders = ConsultantOrder::whereIn('patient_id', $ids)
            ->open()
            ->selectRaw("patient_id, COUNT(*) AS open_count, SUM(urgency = 'stat') AS stat_count")
            ->groupBy('patient_id')
            ->get()
            ->keyBy('patient_id');
        $medications = PatientMedication::alertsForPatients($ids);
        $io = FluidBalanceChart::alertsForPatients($patients);
        $alerts = WardNotification::whereIn('patient_id', $ids)
            ->pending()
            ->selectRaw('patient_id, COUNT(*) AS pending_count')
            ->groupBy('patient_id')
            ->pluck('pending_count', 'patient_id');
        $transfusions = BloodTransfusion::whereIn('patient_id', $ids)
            ->whereIn('status', [BloodTransfusion::STATUS_PENDING, BloodTransfusion::STATUS_IN_PROGRESS])
            ->selectRaw("patient_id, SUM(status = 'in_progress') AS running_count, SUM(status = 'pending') AS pending_count")
            ->groupBy('patient_id')
            ->get()
            ->keyBy('patient_id');
        $labs = $this->labBadges($patients);
        $assessments = ClinicalIndicatorMonitoring::forPatients($patients);

        $badges = [];
        foreach ($ids as $id) {
            $badges[$id] = [
                'orders_open' => (int) ($orders[$id]->open_count ?? 0),
                'orders_stat' => (int) ($orders[$id]->stat_count ?? 0),
                'meds_overdue' => (int) ($medications[$id]['overdue'] ?? 0),
                'meds_due_soon' => (int) ($medications[$id]['due_soon'] ?? 0),
                'io_level' => $io[$id]['level'] ?? null,
                'alerts_pending' => (int) ($alerts[$id] ?? 0),
                'transfusions_running' => (int) ($transfusions[$id]->running_count ?? 0),
                'transfusions_pending' => (int) ($transfusions[$id]->pending_count ?? 0),
                'labs_review' => $labs[$id]['review'] ?? 0,
                'labs_overdue' => $labs[$id]['overdue'] ?? 0,
                'labs_critical' => $labs[$id]['critical'] ?? 0,
                'assess_overdue' => (int) ($assessments[$id]['overdue'] ?? 0),
                'assess_due' => (int) ($assessments[$id]['due'] ?? 0),
            ];
        }

        return $badges;
    }

    /**
     * Lab results waiting for review per patient: how many, how many overdue,
     * and how many critical. None while the ward has Lab Investigations off;
     * sample results count while sample data is on (and are made here, so a
     * bed shows them before its chart is first opened).
     *
     * @return array<int, array{review: int, overdue: int, critical: int}>
     */
    private function labBadges(\Illuminate\Support\Collection $patients): array
    {
        $settings = LabInvestigations::settings();
        if (!$settings['enabled']) {
            return [];
        }

        if ($settings['sample']) {
            $patients->each(fn (Patient $patient) => LabInvestigations::ensureSamples($patient));
        }

        $now = now();

        return LabInvestigation::whereIn('patient_id', $patients->pluck('id'))
            ->when(!$settings['sample'], fn ($query) => $query->where('source', LabInvestigation::SOURCE_HIS))
            ->where('status', 'resulted')
            ->whereNull('reviewed_at')
            ->get()
            ->groupBy('patient_id')
            ->map(fn ($labs) => [
                'review' => $labs->count(),
                'overdue' => $labs->filter(fn (LabInvestigation $lab) => $lab->reviewState($now) === 'overdue')->count(),
                'critical' => $labs->filter(fn (LabInvestigation $lab) => $lab->resultFlag() === 'critical')->count(),
            ])
            ->all();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authenticate(Request $request): ?Nurse
    {
        $token = $request->bearerToken() ?: $request->input('token');
        if (!$token) {
            return null;
        }

        return Nurse::findByAppToken($token);
    }

    private function nursePayload(Nurse $nurse): array
    {
        return [
            'id' => $nurse->id,
            'name' => $nurse->name,
            'designation' => $nurse->designation,
            'ward_id' => $nurse->ward_id,
        ];
    }

    private function bedPayload(Nurse $nurse, ?Bed $bed, ?Patient $patient, array $badges = []): array
    {
        $badges += [
            'orders_open' => 0,
            'orders_stat' => 0,
            'meds_overdue' => 0,
            'meds_due_soon' => 0,
            'io_level' => null,
            'alerts_pending' => 0,
            'transfusions_running' => 0,
            'transfusions_pending' => 0,
        ];

        if (!$patient) {
            return [
                'id' => $bed?->id,
                'ward_id' => $bed?->ward_id,
                'ward_name' => $bed?->ward?->ward_name,
                'number' => $bed?->bed_number,
                'status' => $bed?->status ?? 'available',
                'patient_id' => null,
                'patient_name' => null,
                'mrn' => null,
                'gender' => null,
                'age' => null,
                'ews' => null,
                'ews_has_vitals' => false,
                'days' => null,
                'hours' => null,
                'nurse_on_duty' => $nurse->name,
                'consultant' => null,
                'diet_types_display' => null,
                'isolation_type_name' => null,
                'fall_risk' => null,
                'is_outside' => false,
                'current_movement_location' => null,
                'is_pending_discharge' => false,
                'last_hgt' => null,
                'vitals' => null,
                'infusions' => [],
                'badges' => $badges,
            ];
        }

        $dashboard = app(WardDashboardController::class);

        $latestVitals = VitalSign::where('patient_id', $patient->id)
            ->orderBy('recorded_at', 'desc')
            ->first();
        $ewsData = $dashboard->calculateEWS($latestVitals);

        $currentMovement = PatientMovement::where('patient_id', $patient->id)
            ->where('status', 'sent')
            ->whereNull('returned_at')
            ->orderBy('sent_at', 'desc')
            ->first();

        $infusions = Infusion::where('patient_id', $patient->id)
            ->active()
            ->with('infusionPump')
            ->orderBy('last_updated_at', 'desc')
            ->get()
            ->map(function (Infusion $inf) {
                return [
                    'id' => $inf->id,
                    'medication_name' => $inf->medication_name ?: 'Unknown medication',
                    'device_id' => $inf->infusionPump?->device_name
                        ?: $inf->infusionPump?->device_id,
                    'flow_rate' => $inf->flow_rate !== null ? (float) $inf->flow_rate : null,
                    'status' => $inf->status,
                    'is_warning' => (bool) $inf->is_warning,
                    'formatted_remaining_time' => $inf->formatted_remaining_time,
                    'remaining_volume' => $inf->remaining_volume !== null ? (float) $inf->remaining_volume : null,
                    'last_updated_label' => $inf->last_updated_at?->format('H:i'),
                ];
            })->values()->all();

        $admittedAt = $patient->admitted_at ?? $patient->booked_at ?? now();
        $diff = now()->diff($admittedAt);

        $isPendingDischarge = $patient->status === Patient::STATUS_PENDING_DISCHARGE
            || $patient->pending_discharge_at !== null;

        // Attending doctor display (ADT PV1-7, falls back to primary consultant)
        $attendingDoctor = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();
        $consultantName = $attendingDoctor
            ? $attendingDoctor->display_name
            : $patient->consultant?->name;

        return [
            'id' => $bed?->id ?? (1000000 + $patient->id),
            'ward_id' => $patient->ward_id,
            'ward_name' => $patient->ward?->ward_name,
            'number' => $patient->bed_number,
            'status' => 'occupied',
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'mrn' => $patient->mrn,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'ews' => $ewsData['score'],
            'ews_has_vitals' => $ewsData['has_vitals'],
            'days' => $diff->days,
            'hours' => $diff->h,
            'nurse_on_duty' => $nurse->name,
            'consultant' => $consultantName,
            'diet_types_display' => $patient->diet_types
                ? collect($patient->diet_types)->map(fn($dt) => DietType::getDisplayName($dt))->implode(', ')
                : 'Regular diet',
            'isolation_type_name' => ($patient->isolation_type && $patient->isolation_type !== 'none')
                ? IsolationType::getDisplayName($patient->isolation_type)
                : 'None',
            'fall_risk' => $patient->fall_risk ?? 'none',
            'is_outside' => $currentMovement !== null,
            'current_movement_location' => $currentMovement?->location,
            'is_pending_discharge' => $isPendingDischarge,
            'last_hgt' => $patient->latestSugarReading ? [
                'value' => number_format((float) $patient->latestSugarReading->value, 1) . ' mmol/L',
                'recorded_at' => $patient->latestSugarReading->recorded_at?->format('d M H:i'),
            ] : null,
            'vitals' => $latestVitals ? [
                'recorded_at_label' => $latestVitals->recorded_at?->format('d M H:i'),
                'pulse_rate' => $latestVitals->pulse_rate,
                'systolic_bp' => $latestVitals->systolic_bp,
                'diastolic_bp' => $latestVitals->diastolic_bp,
                'spo2' => $latestVitals->spo2,
                'respiratory_rate' => $latestVitals->respiratory_rate,
                'temperature' => $latestVitals->temperature !== null ? (float) $latestVitals->temperature : null,
            ] : null,
            'infusions' => $infusions,
            'badges' => $badges,
        ];
    }
}
