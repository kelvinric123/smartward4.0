<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Consultant;
use App\Models\ConsultantNote;
use App\Models\DietType;
use App\Models\Infusion;
use App\Models\IsolationType;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMovement;
use App\Models\ShiftSetting;
use App\Models\VitalSign;
use App\Models\WardScheduleAssignment;
use App\Models\ConsultantOrder;
use App\Models\LabInvestigation;
use App\Models\PatientMedication;
use App\Services\DoctorApp\DoctorAppPatientChart;
use App\Services\LabInvestigations;
use App\Support\DoctorAppAccess;
use App\Support\FluidBalanceChart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API for the QMed Smart Ward Doctor mobile app (doctor_app).
 *
 * Auth flow: consultant logs in with the app username/password configured on
 * the Consultant edit page -> receives a bearer token -> all subsequent calls
 * send `Authorization: Bearer <token>`. Responses are scoped to the patients
 * under that consultant's care.
 */
class DoctorAppApiController extends Controller
{
    /**
     * POST /api/doctor/ping — connectivity test for the app settings screen.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'doctor-app-api',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/doctor/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $consultant = Consultant::where('is_active', true)
            ->whereNotNull('app_username')
            ->whereNotNull('app_password')
            ->where(function ($q) use ($validated) {
                $q->where('app_username', $validated['username'])
                    ->orWhere('registration_number', $validated['username']);
            })
            ->first();

        if (!$consultant || !$consultant->verifyAppPassword($validated['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        $token = $consultant->generateAppToken(72);
        $patients = $this->patientsUnderCare($consultant);

        return response()->json([
            'success' => true,
            'token' => $token,
            'doctor' => $this->doctorPayload($consultant),
            'wards' => $this->wardsPayload($patients),
        ]);
    }

    /**
     * POST /api/doctor/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $consultant = $this->authenticate($request);
        if ($consultant) {
            $consultant->invalidateAppToken();
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/doctor/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $consultant = $this->authenticate($request);
        if (!$consultant) {
            return $this->unauthorized();
        }

        $patients = $this->patientsUnderCare($consultant);
        $beds = [];
        $linked = $this->linkedData($patients);

        // Per-ward caches for shift + nurse-on-duty lookups
        $shiftByWard = [];
        $assignmentsByWard = [];
        $today = now()->toDateString();

        foreach ($patients as $patient) {
            $wardId = $patient->ward_id;

            if (!array_key_exists($wardId, $shiftByWard)) {
                $shiftByWard[$wardId] = ShiftSetting::getCurrentShift($wardId);
                $assignments = [];
                if ($shiftByWard[$wardId]) {
                    $rows = WardScheduleAssignment::where('ward_id', $wardId)
                        ->where('scheduled_date', $today)
                        ->where('shift', $shiftByWard[$wardId]->shift_code)
                        ->with('nurse')
                        ->get();
                    foreach ($rows as $row) {
                        $assignments[$row->bed_id] = $row->nurse?->name;
                    }
                }
                $assignmentsByWard[$wardId] = $assignments;
            }

            $beds[] = $this->bedPayload($patient, $consultant, $assignmentsByWard[$wardId], $linked);
        }

        // Sort by ward name then bed number (natural order)
        usort($beds, function ($a, $b) {
            $wardCmp = strcmp($a['ward_name'] ?? '', $b['ward_name'] ?? '');
            if ($wardCmp !== 0) {
                return $wardCmp;
            }
            return strnatcmp((string) ($a['number'] ?? ''), (string) ($b['number'] ?? ''));
        });

        $criticalCount = count(array_filter($beds, fn($b) => $b['ews_has_vitals'] && $b['ews'] !== null && $b['ews'] >= 5));
        $pendingDischarge = count(array_filter($beds, fn($b) => $b['is_pending_discharge']));
        $wards = $this->wardsPayload($patients);

        return response()->json([
            'doctor' => $this->doctorPayload($consultant),
            'summary' => [
                'total_beds_under_care' => count($beds),
                'wards_covered' => count($wards),
                'critical_patients' => $criticalCount,
                'pending_discharge' => $pendingDischarge,
                // Lab results waiting for review (Patient Details > Lab Investigations)
                'pending_reviews' => array_sum(array_map(fn ($b) => $b['labs']['awaiting_review'] ?? 0, $beds)),
                'pending_orders' => array_sum(array_column($beds, 'pending_orders')),
            ],
            'wards' => $wards,
            'beds' => $beds,
        ]);
    }

    /**
     * GET /api/doctor/patients/{patient}/notes
     */
    public function listNotes(Request $request, Patient $patient): JsonResponse
    {
        $consultant = $this->authenticate($request);
        if (!$consultant) {
            return $this->unauthorized();
        }
        if (!$this->isUnderCare($consultant, $patient)) {
            return $this->forbidden();
        }

        return response()->json([
            'success' => true,
            'notes' => $this->notesPayload($patient),
        ]);
    }

    /**
     * POST /api/doctor/patients/{patient}/notes  body: { text }
     */
    public function addNote(Request $request, Patient $patient): JsonResponse
    {
        $consultant = $this->authenticate($request);
        if (!$consultant) {
            return $this->unauthorized();
        }
        if (!$this->isUnderCare($consultant, $patient)) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'text' => 'required|string|max:5000',
        ]);

        $bed = $this->findBedForPatient($patient);

        ConsultantNote::create([
            'consultant_id' => $consultant->id,
            'patient_id' => $patient->id,
            'bed_id' => $bed?->id,
            'note' => trim($validated['text']),
        ]);

        return response()->json([
            'success' => true,
            'notes' => $this->notesPayload($patient),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authenticate(Request $request): ?Consultant
    {
        return DoctorAppAccess::consultant($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please log in again.',
        ], 401);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'This patient is not under your care.',
        ], 403);
    }

    private function doctorPayload(Consultant $consultant): array
    {
        $consultant->loadMissing('specialty');

        return [
            'id' => $consultant->id,
            'name' => $consultant->name,
            'title' => 'Consultant',
            'specialty' => $consultant->specialty?->name,
            'mmc' => $consultant->registration_number,
        ];
    }

    /**
     * Active admitted patients under this consultant's care:
     * - primary consultant on the patient record, or
     * - linked via ADT care providers (attending/referring/consulting), or
     * - assigned via the bed_consultant pivot.
     */
    private function patientsUnderCare(Consultant $consultant)
    {
        $bedPatientIds = DB::table('bed_consultant')
            ->join('beds', 'beds.id', '=', 'bed_consultant.bed_id')
            ->where('bed_consultant.consultant_id', $consultant->id)
            ->whereNotNull('beds.patient_id')
            ->pluck('beds.patient_id');

        $careProviderPatientIds = PatientCareProvider::where('consultant_id', $consultant->id)
            ->where('is_active', true)
            ->pluck('patient_id');

        return Patient::where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->whereNotNull('ward_id')
            ->where(function ($q) use ($consultant, $bedPatientIds, $careProviderPatientIds) {
                $q->where('consultant_id', $consultant->id)
                    ->orWhereIn('id', $bedPatientIds)
                    ->orWhereIn('id', $careProviderPatientIds);
            })
            ->with(['ward', 'nurse', 'consultant', 'latestSugarReading'])
            ->get();
    }

    private function isUnderCare(Consultant $consultant, Patient $patient): bool
    {
        return DoctorAppAccess::isUnderCare($consultant, $patient);
    }

    private function wardsPayload($patients): array
    {
        return $patients->groupBy('ward_id')
            ->map(function ($group) {
                $ward = $group->first()->ward;
                return [
                    'id' => $ward?->id ?? $group->first()->ward_id,
                    'ward_name' => $ward?->ward_name ?? 'Unknown Ward',
                    'bed_count' => $group->count(),
                ];
            })
            ->sortBy('ward_name')
            ->values()
            ->all();
    }

    private function findBedForPatient(Patient $patient): ?Bed
    {
        if ($patient->bed_number === null || $patient->ward_id === null) {
            return null;
        }

        return Bed::where('ward_id', $patient->ward_id)
            ->where('bed_number', $patient->bed_number)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Today's I/O, the active medication orders, the open consultant orders
     * and the lab results waiting for review of every patient on the
     * dashboard, fetched for all of them at once rather than bed by bed.
     */
    private function linkedData($patients): array
    {
        $ids = $patients->pluck('id');

        return [
            'io' => FluidBalanceChart::alertsForPatients($patients),
            'medications' => PatientMedication::alertsForPatients($ids),
            'orders' => ConsultantOrder::whereIn('patient_id', $ids)
                ->where('status', ConsultantOrder::STATUS_OPEN)
                ->selectRaw('patient_id, COUNT(*) as open_count')
                ->groupBy('patient_id')
                ->pluck('open_count', 'patient_id')
                ->all(),
            'labs' => $this->labsAwaitingReview($ids),
        ];
    }

    /**
     * Per patient: the lab results waiting for review, how many are overdue and how many
     * carry a critical flag. Only what Patient Details would show (no sample results
     * unless sample data is on), and nothing when Lab Investigations is switched off.
     */
    private function labsAwaitingReview($ids): array
    {
        $settings = LabInvestigations::settings();
        if (!$settings['enabled']) {
            return [];
        }

        $now = now();

        return LabInvestigation::whereIn('patient_id', $ids)
            ->where('status', 'resulted')
            ->whereNull('reviewed_at')
            ->when(!$settings['sample'], fn ($query) => $query->where('source', LabInvestigation::SOURCE_HIS))
            ->get()
            ->groupBy('patient_id')
            ->map(fn ($labs) => [
                'awaiting_review' => $labs->count(),
                'overdue' => $labs->filter(fn (LabInvestigation $lab) => $lab->reviewState($now) === 'overdue')->count(),
                'critical' => $labs->filter(fn (LabInvestigation $lab) => $lab->resultFlag() === 'critical')->count(),
            ])
            ->all();
    }

    private function bedPayload(Patient $patient, Consultant $consultant, array $nurseAssignments, array $linked = []): array
    {
        $dashboard = app(WardDashboardController::class);
        $io = $linked['io'][$patient->id] ?? null;
        $medications = $linked['medications'][$patient->id] ?? null;
        $labs = $linked['labs'][$patient->id] ?? null;
        $bed = $this->findBedForPatient($patient);

        // Vitals: latest 20 readings, oldest -> newest for the trend chart
        $vitalRows = VitalSign::where('patient_id', $patient->id)
            ->orderBy('recorded_at', 'desc')
            ->limit(20)
            ->get()
            ->reverse()
            ->values();

        $latestVitals = $vitalRows->last();
        $ewsData = $dashboard->calculateEWS($latestVitals);

        $vitalsHistory = $vitalRows->map(function (VitalSign $v) use ($dashboard) {
            $rowEws = $dashboard->calculateEWS($v);
            return [
                'id' => 'vh-' . $v->id,
                'recorded_at' => $v->recorded_at?->toIso8601String(),
                'recorded_at_label' => $v->recorded_at?->format('d M H:i'),
                'pulse_rate' => $v->pulse_rate,
                'systolic_bp' => $v->systolic_bp,
                'diastolic_bp' => $v->diastolic_bp,
                'spo2' => $v->spo2,
                'respiratory_rate' => $v->respiratory_rate,
                'temperature' => $v->temperature !== null ? (float) $v->temperature : null,
                'ews' => $rowEws['score'],
            ];
        })->values()->all();

        // Movements (currently off-ward?)
        $currentMovement = PatientMovement::where('patient_id', $patient->id)
            ->where('status', 'sent')
            ->whereNull('returned_at')
            ->orderBy('sent_at', 'desc')
            ->first();

        // Active infusions
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

        // Length of stay
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
            : ($patient->consultant?->name ?? $consultant->name);

        return [
            'id' => $bed?->id ?? (1000000 + $patient->id),
            'ward_id' => $patient->ward_id,
            'ward_name' => $patient->ward?->ward_name ?? 'Unknown Ward',
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
            'nurse_on_duty' => ($bed ? ($nurseAssignments[$bed->id] ?? null) : null)
                ?? $patient->nurse?->name,
            'consultant' => $consultantName,
            'primary_diagnosis' => null,
            'diet_types_display' => $patient->diet_types
                ? collect($patient->diet_types)->map(fn($dt) => DietType::getDisplayName($dt))->implode(', ')
                : 'Regular diet',
            'isolation_type_name' => ($patient->isolation_type && $patient->isolation_type !== 'none')
                ? IsolationType::getDisplayName($patient->isolation_type)
                : 'None',
            'fall_risk' => $patient->fall_risk ?? 'none',
            'allergies' => $patient->allergies ?? [],
            // Name, optional severity and resolved, active and most severe first
            'allergy_list' => DoctorAppPatientChart::allergies($patient),
            'vip_status' => $patient->vipStatusLabel(),
            // The oxygen now (Oxygen Therapy tab or vital signs); null when none is recorded
            'oxygen' => DoctorAppPatientChart::oxygenBrief($patient),
            // Lab results waiting for review; null when there are none
            'labs' => $labs,
            'is_outside' => $currentMovement !== null,
            'current_movement_location' => $currentMovement?->location,
            'is_pending_discharge' => $isPendingDischarge,
            'pending_review' => ($labs['awaiting_review'] ?? 0) > 0,
            'pending_orders' => (int) ($linked['orders'][$patient->id] ?? 0),
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
            'vitals_history' => $vitalsHistory,
            // Most urgent first: "Ceftriaxone 1 g IV TDS · Due in 2h"
            'active_medications' => collect($medications['items'] ?? [])
                ->map(fn (array $item) => $item['name'] . ' ' . $item['summary'] . ' · ' . $item['label'])
                ->values()
                ->all(),
            'medication_counts' => [
                'active' => $medications['active'] ?? 0,
                'overdue' => $medications['overdue'] ?? 0,
                'due_soon' => $medications['due_soon'] ?? 0,
            ],
            // Today's I/O against the fluid plan; null when nothing is charted or planned
            'io' => $io ? [
                'intake' => $io['intake'],
                'output' => $io['output'],
                'balance' => $io['balance'],
                'limit' => $io['limit'],
                'level' => $io['level'],
                'alerts' => array_column($io['alerts'], 'title'),
            ] : null,
            'infusions' => $infusions,
        ];
    }

    private function notesPayload(Patient $patient): array
    {
        return ConsultantNote::where('patient_id', $patient->id)
            ->with('consultant')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(fn(ConsultantNote $n) => [
                'id' => $n->id,
                'text' => $n->note,
                'author' => $n->consultant?->name ?? 'Consultant',
                'created_at' => $n->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
