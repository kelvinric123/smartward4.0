<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\DietType;
use App\Models\Infusion;
use App\Models\IsolationType;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMovement;
use App\Models\ShiftSetting;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
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

        $today = now()->toDateString();

        // Today's schedule assignments for this nurse, filtered to each
        // ward's current shift (mirrors the web nurse dashboard).
        $assignments = WardScheduleAssignment::where('nurse_id', $nurse->id)
            ->where('scheduled_date', $today)
            ->with('bed')
            ->get();

        $shiftByWard = [];
        $currentAssignments = $assignments->filter(function ($assignment) use (&$shiftByWard) {
            $wardId = $assignment->ward_id;
            if (!array_key_exists($wardId, $shiftByWard)) {
                $shiftByWard[$wardId] = ShiftSetting::getCurrentShift($wardId);
            }
            $shift = $shiftByWard[$wardId];
            return $shift ? $assignment->shift === $shift->shift_code : true;
        });

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

        // Build bed payloads (skip empty schedule beds with no patient only
        // if we have at least something better to show; keep them so the
        // nurse sees their full assignment).
        $beds = [];
        foreach ($patients as [$bed, $patient]) {
            $beds[] = $this->bedPayload($nurse, $bed, $patient);
        }

        usort($beds, fn($a, $b) => strnatcmp((string) ($a['number'] ?? ''), (string) ($b['number'] ?? '')));

        // Resolve the nurse's ward for the header + occupancy stats
        $wardId = $nurse->ward_id
            ?: $currentAssignments->pluck('ward_id')->filter()->first()
            ?: collect($beds)->pluck('ward_id')->filter()->first();
        $ward = $wardId ? Ward::find($wardId) : null;
        $currentShift = $wardId
            ? ($shiftByWard[$wardId] ?? ShiftSetting::getCurrentShift($wardId))
            : null;

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

        return response()->json([
            'nurse' => $this->nursePayload($nurse),
            'current_shift' => $currentShift ? [
                'shift_code' => $currentShift->shift_code,
                'shift_name' => $currentShift->shift_name,
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
            ],
            'beds' => $beds,
        ]);
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

    private function bedPayload(Nurse $nurse, ?Bed $bed, ?Patient $patient): array
    {
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
        ];
    }
}
