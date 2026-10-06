<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\DietType;
use App\Models\Infusion;
use App\Models\IsolationType;
use App\Models\Nurse;
use App\Models\NurseHandover;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMovement;
use App\Models\ShiftSetting;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * API for the QMed Smart Ward Nurse mobile app (nurse_app).
 *
 * Auth flow: nurse logs in with the app username/password configured on the
 * Nurse edit page -> receives a bearer token -> all subsequent calls send
 * `Authorization: Bearer <token>`. Responses are scoped to the beds assigned
 * to that nurse (current-shift schedule assignments, plus patients where the
 * nurse is set as the primary nurse).
 *
 * Shift handover: the outgoing nurse passes each patient's condition and
 * nursing plan to the next-shift nurse, who receives (acknowledges) it.
 */
class NurseAppApiController extends Controller
{
    /**
     * How far back handovers are shown (and pending ones can be edited or
     * received), in hours.
     */
    private const HANDOVER_WINDOW_HOURS = 24;

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
            return $this->unauthorized();
        }

        [
            'assignments' => $currentAssignments,
            'pairs' => $patients,
            'shiftByWard' => $shiftByWard,
        ] = $this->resolveAssignments($nurse);

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

    /**
     * GET /api/nurse/handovers
     *
     * Shift handover state for this nurse: what they handed over this shift
     * (outgoing), what is waiting for them to receive (incoming), the next
     * shift and the colleagues they can hand over to.
     */
    public function handovers(Request $request): JsonResponse
    {
        $nurse = $this->authenticate($request);
        if (!$nurse) {
            return $this->unauthorized();
        }

        $context = $this->handoverContext($nurse);

        return response()->json(['success' => true] + $this->handoverState($nurse, $context));
    }

    /**
     * POST /api/nurse/handovers
     * body: { to_nurse_id?, items: [{ patient_id, condition_status?, patient_condition?, nursing_plan? }] }
     *
     * Without to_nurse_id each patient goes to the nurse scheduled on that
     * bed for the next shift, or stays open for whoever takes the patient over.
     */
    public function submitHandovers(Request $request): JsonResponse
    {
        $nurse = $this->authenticate($request);
        if (!$nurse) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'to_nurse_id' => ['nullable', 'integer', Rule::exists('nurses', 'id')->where('is_active', true)],
            'items' => 'required|array|min:1|max:50',
            'items.*.patient_id' => 'required|integer',
            'items.*.condition_status' => 'nullable|string|in:' . implode(',', NurseHandover::CONDITION_STATUSES),
            'items.*.patient_condition' => 'nullable|string|max:5000',
            'items.*.nursing_plan' => 'nullable|string|max:5000',
        ]);

        $toNurseId = isset($validated['to_nurse_id']) ? (int) $validated['to_nurse_id'] : null;
        if ($toNurseId === $nurse->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot hand over to yourself.',
            ], 422);
        }

        $context = $this->handoverContext($nurse);

        // Validate every item before saving anything
        $items = [];
        foreach ($validated['items'] as $item) {
            $patientId = (int) $item['patient_id'];
            $pair = $context['pairsByPatient'][$patientId] ?? null;
            if (!$pair) {
                return response()->json([
                    'success' => false,
                    'message' => 'One of the patients is no longer assigned to you. Refresh and try again.',
                ], 403);
            }

            $condition = trim((string) ($item['patient_condition'] ?? ''));
            $plan = trim((string) ($item['nursing_plan'] ?? ''));
            if ($condition === '' && $plan === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Add the patient condition or nursing plan for every patient you hand over.',
                ], 422);
            }

            $items[] = [$pair, $item['condition_status'] ?? null, $condition, $plan];
        }

        $dashboard = app(WardDashboardController::class);

        foreach ($items as [[$bed, $patient], $conditionStatus, $condition, $plan]) {
            $currentShift = $context['shiftByWard'][$patient->ward_id] ?? null;
            $nextShift = $context['nextShiftByWard'][$patient->ward_id] ?? null;

            $receiverId = $toNurseId ?? ($context['suggestedByPatient'][$patient->id]['id'] ?? null);
            if ($receiverId === $nurse->id) {
                $receiverId = null;
            }

            $latestVitals = VitalSign::where('patient_id', $patient->id)
                ->orderBy('recorded_at', 'desc')
                ->first();

            $attributes = [
                'ward_id' => $patient->ward_id,
                'bed_id' => $bed?->id,
                'to_nurse_id' => $receiverId,
                'from_shift' => $currentShift?->shift_code,
                'to_shift' => $nextShift ? $nextShift['shift']->shift_code : null,
                'condition_status' => $conditionStatus,
                'patient_condition' => $condition !== '' ? $condition : null,
                'nursing_plan' => $plan !== '' ? $plan : null,
                'ews' => $dashboard->calculateEWS($latestVitals)['score'],
            ];

            // Re-submitting before the counterpart has received it edits the
            // pending handover instead of stacking a second one.
            $pending = NurseHandover::where('from_nurse_id', $nurse->id)
                ->where('patient_id', $patient->id)
                ->where('status', NurseHandover::STATUS_PENDING)
                ->where('created_at', '>=', now()->subHours(self::HANDOVER_WINDOW_HOURS))
                ->latest()
                ->first();

            if ($pending) {
                $pending->update($attributes);
            } else {
                NurseHandover::create($attributes + [
                    'patient_id' => $patient->id,
                    'from_nurse_id' => $nurse->id,
                    'status' => NurseHandover::STATUS_PENDING,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'handed_over' => count($items),
        ] + $this->handoverState($nurse, $context));
    }

    /**
     * POST /api/nurse/handovers/receive  body: { ids: [...] }
     */
    public function receiveHandovers(Request $request): JsonResponse
    {
        $nurse = $this->authenticate($request);
        if (!$nurse) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
        ]);

        $context = $this->handoverContext($nurse);

        $received = $this->incomingHandoversQuery($nurse, $context)
            ->whereIn('id', $validated['ids'])
            ->where('status', NurseHandover::STATUS_PENDING)
            ->get();

        foreach ($received as $handover) {
            $handover->update([
                'status' => NurseHandover::STATUS_RECEIVED,
                'received_by_nurse_id' => $nurse->id,
                'received_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'received' => $received->count(),
        ] + $this->handoverState($nurse, $context));
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

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated. Please log in again.',
        ], 401);
    }

    /**
     * Beds/patients assigned to this nurse right now: today's schedule
     * assignments for each ward's current shift, plus patients where the
     * nurse is set as primary nurse. `pairs` is a collection of [?Bed, ?Patient].
     */
    private function resolveAssignments(Nurse $nurse): array
    {
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
            $patients->push([$bed, $this->patientInBed($bed)]);
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

        return [
            'assignments' => $currentAssignments,
            'pairs' => $patients,
            'shiftByWard' => $shiftByWard,
        ];
    }

    private function patientInBed(Bed $bed): ?Patient
    {
        return Patient::where('ward_id', $bed->ward_id)
            ->where('bed_number', $bed->bed_number)
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->with(['ward', 'consultant', 'nurse', 'latestSugarReading'])
            ->first();
    }

    /**
     * Everything the handover endpoints need to know about this nurse:
     * current patients, the wards involved, each ward's next shift, the
     * nurses scheduled on it, and the patients this nurse is taking over.
     */
    private function handoverContext(Nurse $nurse): array
    {
        [
            'assignments' => $currentAssignments,
            'pairs' => $pairs,
            'shiftByWard' => $shiftByWard,
        ] = $this->resolveAssignments($nurse);

        $pairsByPatient = [];
        foreach ($pairs as [$bed, $patient]) {
            if ($patient) {
                $pairsByPatient[$patient->id] = [$bed, $patient];
            }
        }

        $nextShiftByWard = [];
        $nextShiftFor = function (?int $wardId) use (&$nextShiftByWard) {
            if (!$wardId) {
                return null;
            }
            if (!array_key_exists($wardId, $nextShiftByWard)) {
                $nextShiftByWard[$wardId] = ShiftSetting::getNextShift($wardId);
            }
            return $nextShiftByWard[$wardId];
        };

        // Beds this nurse is scheduled on for the upcoming shift: the
        // incoming nurse usually opens the app before their shift starts.
        $upcomingAssignments = WardScheduleAssignment::where('nurse_id', $nurse->id)
            ->whereIn('scheduled_date', [now()->toDateString(), now()->addDay()->toDateString()])
            ->with('bed')
            ->get()
            ->filter(function ($assignment) use ($nextShiftFor) {
                $next = $nextShiftFor($assignment->ward_id);
                return $next
                    && $assignment->shift === $next['shift']->shift_code
                    && $assignment->scheduled_date->toDateString() === $next['starts_at']->toDateString();
            });

        $incomingPatientIds = collect(array_keys($pairsByPatient));
        foreach ($upcomingAssignments as $assignment) {
            if ($assignment->bed && ($patient = $this->patientInBed($assignment->bed))) {
                $incomingPatientIds->push($patient->id);
            }
        }

        $homeWardIds = collect([$nurse->ward_id])->filter()->values();
        $wardIds = $homeWardIds
            ->merge(collect($pairsByPatient)->map(fn($pair) => $pair[1]->ward_id))
            ->merge($upcomingAssignments->pluck('ward_id'))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        // Who is scheduled on the next shift, per ward -> bed
        $suggestedByPatient = [];
        $nextShiftNurseIds = collect();
        foreach ($wardIds as $wardId) {
            $next = $nextShiftFor($wardId);
            if (!$next) {
                continue;
            }
            $rows = WardScheduleAssignment::where('ward_id', $wardId)
                ->where('scheduled_date', $next['starts_at']->toDateString())
                ->where('shift', $next['shift']->shift_code)
                ->with('nurse')
                ->get();
            $nextShiftNurseIds = $nextShiftNurseIds->merge($rows->pluck('nurse_id'));

            $nurseByBed = $rows->filter(fn($row) => $row->nurse && (int) $row->nurse_id !== $nurse->id)
                ->keyBy('bed_id');
            foreach ($pairsByPatient as $patientId => [$bed, $patient]) {
                if ($bed && (int) $patient->ward_id === $wardId && $nurseByBed->has($bed->id)) {
                    $receiver = $nurseByBed->get($bed->id)->nurse;
                    $suggestedByPatient[$patientId] = ['id' => $receiver->id, 'name' => $receiver->name];
                }
            }
        }

        $primaryWardId = $nurse->ward_id
            ?: $currentAssignments->pluck('ward_id')->filter()->first()
            ?: collect($pairsByPatient)->map(fn($pair) => $pair[1]->ward_id)->filter()->first()
            ?: $wardIds->first();

        return [
            'pairsByPatient' => $pairsByPatient,
            'shiftByWard' => $shiftByWard,
            'nextShiftByWard' => $nextShiftByWard,
            'primaryWardId' => $primaryWardId,
            'currentShift' => $primaryWardId
                ? ($shiftByWard[$primaryWardId] ?? ShiftSetting::getCurrentShift($primaryWardId))
                : null,
            'nextShift' => $nextShiftFor($primaryWardId),
            'homeWardIds' => $homeWardIds->all(),
            'wardIds' => $wardIds->all(),
            'incomingPatientIds' => $incomingPatientIds->unique()->values()->all(),
            'suggestedByPatient' => $suggestedByPatient,
            'nextShiftNurseIds' => $nextShiftNurseIds->filter()->map(fn($id) => (int) $id)->unique()->values()->all(),
        ];
    }

    /**
     * Handovers this nurse can see on the receiving side: pending ones
     * addressed to them (or open ones for their patients/ward), plus the
     * ones they already received, within the handover window.
     */
    private function incomingHandoversQuery(Nurse $nurse, array $context)
    {
        return NurseHandover::where('from_nurse_id', '!=', $nurse->id)
            ->where('created_at', '>=', now()->subHours(self::HANDOVER_WINDOW_HOURS))
            ->where(function ($q) use ($nurse, $context) {
                $q->where('received_by_nurse_id', $nurse->id)
                    ->orWhere(function ($q) use ($nurse, $context) {
                        $q->where('status', NurseHandover::STATUS_PENDING)
                            ->where(function ($q) use ($nurse, $context) {
                                $q->where('to_nurse_id', $nurse->id)
                                    ->orWhere(function ($q) use ($context) {
                                        $q->whereNull('to_nurse_id')
                                            ->where(function ($q) use ($context) {
                                                $q->whereIn('patient_id', $context['incomingPatientIds'])
                                                    ->orWhereIn('ward_id', $context['homeWardIds']);
                                            });
                                    });
                            });
                    });
            });
    }

    private function handoverState(Nurse $nurse, array $context): array
    {
        $with = ['patient', 'ward', 'bed', 'fromNurse', 'toNurse', 'receivedBy'];

        $outgoing = NurseHandover::where('from_nurse_id', $nurse->id)
            ->where('created_at', '>=', now()->subHours(self::HANDOVER_WINDOW_HOURS))
            ->with($with)
            ->orderBy('created_at', 'desc')
            ->get();

        $incoming = $this->incomingHandoversQuery($nurse, $context)
            ->with($with)
            ->orderBy('created_at', 'desc')
            ->get();

        $nurses = Nurse::where('is_active', true)
            ->where('id', '!=', $nurse->id)
            ->where(function ($q) use ($context) {
                $q->whereIn('ward_id', $context['wardIds'])
                    ->orWhereIn('id', $context['nextShiftNurseIds']);
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        $currentShift = $context['currentShift'];
        $nextShift = $context['nextShift'];

        return [
            'current_shift' => $currentShift ? [
                'shift_code' => $currentShift->shift_code,
                'shift_name' => $currentShift->shift_name,
            ] : null,
            'next_shift' => $nextShift ? [
                'shift_code' => $nextShift['shift']->shift_code,
                'shift_name' => $nextShift['shift']->shift_name,
                'starts_at' => $nextShift['starts_at']->toIso8601String(),
                'starts_at_label' => $nextShift['starts_at']->format('d M H:i'),
            ] : null,
            'nurses' => $nurses->map(fn(Nurse $n) => [
                'id' => $n->id,
                'name' => $n->name,
                'designation' => $n->designation,
                'on_next_shift' => in_array($n->id, $context['nextShiftNurseIds'], true),
            ])->values()->all(),
            'suggested_receivers' => collect($context['suggestedByPatient'])
                ->map(fn($receiver, $patientId) => ['patient_id' => $patientId, 'nurse' => $receiver])
                ->values()
                ->all(),
            'outgoing' => $outgoing->map(fn($h) => $this->handoverPayload($h))->values()->all(),
            'incoming' => $incoming->map(fn($h) => $this->handoverPayload($h))->values()->all(),
        ];
    }

    private function handoverPayload(NurseHandover $handover): array
    {
        $person = fn(?Nurse $n) => $n ? ['id' => $n->id, 'name' => $n->name] : null;

        return [
            'id' => $handover->id,
            'patient_id' => $handover->patient_id,
            'patient_name' => $handover->patient?->name,
            'mrn' => $handover->patient?->mrn,
            'bed_number' => $handover->bed?->bed_number ?? $handover->patient?->bed_number,
            'ward_name' => $handover->ward?->ward_name,
            'from_nurse' => $person($handover->fromNurse),
            'to_nurse' => $person($handover->toNurse),
            'from_shift' => $handover->from_shift,
            'to_shift' => $handover->to_shift,
            'condition_status' => $handover->condition_status,
            'patient_condition' => $handover->patient_condition,
            'nursing_plan' => $handover->nursing_plan,
            'ews' => $handover->ews,
            'status' => $handover->status,
            'received_by' => $person($handover->receivedBy),
            'received_at' => $handover->received_at?->toIso8601String(),
            'created_at' => $handover->created_at?->toIso8601String(),
            'updated_at' => $handover->updated_at?->toIso8601String(),
        ];
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
                'vitals_history' => [],
                'infusions' => [],
            ];
        }

        $dashboard = app(WardDashboardController::class);

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
            'vitals_history' => $vitalsHistory,
            'infusions' => $infusions,
        ];
    }
}
