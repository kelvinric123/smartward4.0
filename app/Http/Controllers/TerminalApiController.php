<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\DietType;
use App\Models\IsolationType;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMovement;
use App\Models\SugarReading;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Models\WardScheduleAssignment;
use App\Services\NurseScheduling\RosterSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API for the bedside Patient Information Terminal (patient information terminal/).
 *
 * The terminal is a kiosk bound to one bed. Staff open the terminal's admin
 * settings (passcode protected), point it at this server and pick a ward +
 * bed; the terminal then polls the bed snapshot to show the live patient
 * profile, care team and vitals.
 */
class TerminalApiController extends Controller
{
    /**
     * How long after a discharge the bedside app still shows the farewell
     * screen instead of a plain "no patient in this bed" message.
     */
    private const DISCHARGED_NOTICE_HOURS = 12;

    /**
     * GET/POST /api/terminal/ping — connectivity test for the settings screen.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'terminal-api',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/terminal/wards — for the ward picker in admin settings.
     */
    public function wards(): JsonResponse
    {
        $wards = Ward::where('is_active', true)
            ->orderBy('ward_name')
            ->get()
            ->map(fn(Ward $w) => [
                'id' => $w->id,
                'ward_name' => $w->ward_name,
                'ward_code' => $w->ward_code,
            ])
            ->values();

        return response()->json(['success' => true, 'wards' => $wards]);
    }

    /**
     * GET /api/terminal/beds?ward_id= — for the bed picker in admin settings.
     */
    public function beds(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
        ]);

        $beds = Bed::where('ward_id', $validated['ward_id'])
            ->where('is_active', true)
            ->orderBy('bed_number')
            ->get()
            ->map(function (Bed $bed) {
                $patient = $this->patientForBed($bed);
                return [
                    'id' => $bed->id,
                    'bed_number' => $bed->bed_number,
                    'display_name' => $bed->bed_display_name ?: $bed->bed_number,
                    'status' => $bed->status,
                    'patient_name' => $patient?->name,
                ];
            })
            ->values();

        return response()->json(['success' => true, 'beds' => $beds]);
    }

    /**
     * GET /api/terminal/beds/{bed}/snapshot — live data for the bound bed.
     */
    public function snapshot(Bed $bed): JsonResponse
    {
        $bed->loadMissing('ward');
        $patient = $this->patientForBed($bed);

        if (!$patient) {
            // Distinguish "your stay has ended" from "this bed was never yours" —
            // the app shows a farewell rather than a bare empty-bed screen.
            $recentlyDischarged = Patient::where('ward_id', $bed->ward_id)
                ->where('bed_number', $bed->bed_number)
                ->where('status', Patient::STATUS_DISCHARGED)
                ->whereNotNull('discharged_at')
                ->where('discharged_at', '>=', now()->subHours(self::DISCHARGED_NOTICE_HOURS))
                ->orderByDesc('discharged_at')
                ->first();

            return response()->json([
                'success' => true,
                'occupied' => false,
                'vacancy_reason' => $recentlyDischarged ? 'discharged' : 'vacant',
                'bed' => [
                    'id' => $bed->id,
                    'bed_number' => $bed->bed_number,
                    'ward_name' => $bed->ward?->ward_name,
                    'ward_code' => $bed->ward?->ward_code,
                ],
                'discharged_patient' => $recentlyDischarged ? [
                    'name' => $recentlyDischarged->name,
                    'first_name' => explode(' ', trim($recentlyDischarged->name))[0] ?? null,
                    'discharged_at' => $recentlyDischarged->discharged_at?->toIso8601String(),
                    'discharged_at_label' => $recentlyDischarged->discharged_at?->format('j M Y, H:i'),
                ] : null,
                'patient' => null,
                'care_team' => [],
                'vitals' => null,
                'schedule' => [],
                'discharge' => null,
            ]);
        }

        $patient->loadMissing(['consultant.specialty', 'nurse', 'ward']);

        // Vitals: last 12 readings, oldest -> newest
        $vitalRows = VitalSign::where('patient_id', $patient->id)
            ->orderBy('recorded_at', 'desc')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn(VitalSign $v) => [
                'recorded_at' => $v->recorded_at?->toIso8601String(),
                'time_label' => $v->recorded_at?->format('H:i'),
                'day_label' => $v->recorded_at?->format('D'),
                'pulse_rate' => $v->pulse_rate,
                'systolic_bp' => $v->systolic_bp,
                'diastolic_bp' => $v->diastolic_bp,
                'spo2' => $v->spo2,
                'respiratory_rate' => $v->respiratory_rate,
                'temperature' => $v->temperature !== null ? (float) $v->temperature : null,
            ]);

        $glucoseRows = SugarReading::where('patient_id', $patient->id)
            ->orderBy('recorded_at', 'desc')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn(SugarReading $r) => [
                'recorded_at' => $r->recorded_at?->toIso8601String(),
                'time_label' => $r->recorded_at?->format('H:i'),
                'day_label' => $r->recorded_at?->format('D'),
                'value' => (float) $r->value,
            ]);

        $admittedAt = $patient->admitted_at ?? $patient->booked_at;
        $los = $admittedAt ? (int) $admittedAt->diffInDays(now()) : null;

        return response()->json([
            'success' => true,
            'occupied' => true,
            'bed' => [
                'id' => $bed->id,
                'bed_number' => $bed->bed_number,
                'ward_name' => $bed->ward?->ward_name,
                'ward_code' => $bed->ward?->ward_code,
            ],
            'patient' => [
                'name' => $patient->name,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'mrn' => $patient->mrn,
                'ward_name' => $patient->ward?->ward_name,
                'ward_code' => $patient->ward?->ward_code,
                'bed_number' => $patient->bed_number,
                'admitted_at_label' => $admittedAt?->format('j M Y'),
                'length_of_stay_days' => $los,
                'attending' => $this->attendingName($patient),
                'primary_nurse' => $patient->nurse?->name,
                'allergies' => $patient->allergies ?? [],
                'diet_display' => $patient->diet_types
                    ? collect($patient->diet_types)->map(fn($dt) => DietType::getDisplayName($dt))->implode(' · ')
                    : 'Regular diet',
                'isolation_type_name' => ($patient->isolation_type && $patient->isolation_type !== 'none')
                    ? IsolationType::getDisplayName($patient->isolation_type)
                    : null,
                'fall_risk' => $patient->fall_risk ?? 'none',
                'expected_discharge_label' => $patient->expected_discharge_at?->format('j M Y'),
            ],
            'care_team' => $this->careTeam($bed, $patient),
            'vitals' => [
                'readings' => $vitalRows,
                'glucose' => $glucoseRows,
                'latest_label' => $vitalRows->last()['time_label'] ?? null,
            ],
            'schedule' => $this->schedule($patient),
            'discharge' => $this->discharge($patient),
        ]);
    }

    /**
     * Today's planned movements (scans, theatre, physio, ...) for the patient's
     * timeline. Ward staff create these in the dashboard's Movement tab.
     */
    private function schedule(Patient $patient): array
    {
        return PatientMovement::where('patient_id', $patient->id)
            ->whereDate('scheduled_at', now()->toDateString())
            ->orderBy('scheduled_at')
            ->get()
            ->map(function (PatientMovement $m) {
                // 'returned' means the patient is back on the ward; 'sent' means
                // they are out of the ward right now.
                $status = match ($m->status) {
                    'returned' => 'done',
                    'sent' => 'active',
                    default => 'upcoming',
                };

                $detail = $m->location_type ?: 'Ward movement';
                if ($m->notes) {
                    $detail .= ' · ' . $m->notes;
                }

                return [
                    'id' => 'movement-' . $m->id,
                    'time' => $m->scheduled_at?->format('H:i'),
                    'title' => $m->location,
                    'detail' => $detail,
                    'type' => $this->movementType($m->location_type, $m->location),
                    'status' => $status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Best-effort mapping of a movement to one of the patient app's timeline
     * icons. Unknown destinations fall back to a generic check.
     */
    private function movementType(?string $locationType, ?string $location): string
    {
        $haystack = strtolower(trim(($locationType ?? '') . ' ' . ($location ?? '')));

        return match (true) {
            str_contains($haystack, 'physio'), str_contains($haystack, 'therapy'),
            str_contains($haystack, 'rehab') => 'therapy',
            str_contains($haystack, 'theatre'), str_contains($haystack, 'ot '),
            str_contains($haystack, 'surgery'), str_contains($haystack, 'operation') => 'doctor',
            str_contains($haystack, 'x-ray'), str_contains($haystack, 'xray'),
            str_contains($haystack, 'scan'), str_contains($haystack, 'ct'),
            str_contains($haystack, 'mri'), str_contains($haystack, 'radiology'),
            str_contains($haystack, 'imaging'), str_contains($haystack, 'lab') => 'check',
            str_contains($haystack, 'dialysis'), str_contains($haystack, 'endoscopy') => 'med',
            default => 'check',
        };
    }

    /**
     * Discharge plan the patient app shows on its Discharge tab.
     */
    private function discharge(Patient $patient): array
    {
        $isScheduled = $patient->status === Patient::STATUS_PENDING_DISCHARGE
            && $patient->expected_discharge_at !== null;

        return [
            'status' => $patient->status,
            'is_scheduled' => $isScheduled,
            'expected_at' => $patient->expected_discharge_at?->toIso8601String(),
            'expected_date_label' => $patient->expected_discharge_at?->format('l, j M Y'),
            'expected_time_label' => $patient->expected_discharge_at?->format('H:i'),
        ];
    }

    /**
     * POST /api/terminal/beds/{bed}/requests — patient raises a smart call.
     *
     * Lands in the ward dashboard's Ward Notifications panel, where a nurse
     * marks it responded; the patient app then sees the response.
     */
    public function storeRequest(Request $request, Bed $bed): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string|max:64',
            'label' => 'required|string|max:120',
            'urgent' => 'sometimes|boolean',
        ]);

        $patient = $this->patientForBed($bed);

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'No patient is currently admitted to this bed.',
            ], 409);
        }

        $notification = WardNotification::createPatientRequest(
            $bed->ward_id,
            $patient->id,
            $bed->bed_number,
            $validated['category'],
            $validated['label'],
            $patient->name,
            (bool) ($validated['urgent'] ?? false)
        );

        return response()->json([
            'success' => true,
            'request' => $this->formatRequest($notification),
        ], 201);
    }

    /**
     * GET /api/terminal/beds/{bed}/requests — this bed's recent smart calls
     * and whether a nurse has responded yet.
     */
    public function listRequests(Bed $bed): JsonResponse
    {
        $patient = $this->patientForBed($bed);

        if (!$patient) {
            return response()->json(['success' => true, 'requests' => []]);
        }

        $requests = WardNotification::where('patient_id', $patient->id)
            ->where('type', WardNotification::TYPE_PATIENT_REQUEST)
            ->with('responder:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn(WardNotification $n) => $this->formatRequest($n))
            ->values();

        return response()->json(['success' => true, 'requests' => $requests]);
    }

    private function formatRequest(WardNotification $notification): array
    {
        $notification->loadMissing('responder:id,name');

        return [
            'id' => $notification->id,
            'category' => $notification->category,
            'status' => $notification->status,
            'severity' => $notification->severity,
            'message' => $notification->message,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_label' => $notification->created_at?->format('H:i'),
            'responded' => $notification->status === WardNotification::STATUS_RESPONDED,
            'responded_at' => $notification->responded_at?->toIso8601String(),
            'responded_at_label' => $notification->responded_at?->format('H:i'),
            'responded_by' => $notification->responder?->name,
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function patientForBed(Bed $bed): ?Patient
    {
        return Patient::where('ward_id', $bed->ward_id)
            ->where('bed_number', $bed->bed_number)
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->first();
    }

    private function attendingName(Patient $patient): ?string
    {
        $attending = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();

        return $attending ? $attending->display_name : $patient->consultant?->name;
    }

    private function careTeam(Bed $bed, Patient $patient): array
    {
        $team = [];

        $attending = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();
        $attendingName = $attending ? $attending->display_name : $patient->consultant?->name;
        if ($attendingName) {
            $specialty = $patient->consultant?->specialty?->name;
            $team[] = [
                'id' => 'attending',
                'name' => $attendingName,
                'role' => $specialty ? "Consultant · {$specialty}" : 'Attending Consultant',
                'group' => 'doctor',
                'detail' => 'Attending physician · leads your care and reviews you on rounds.',
                'status' => null,
            ];
        }

        // Other doctors linked via ADT (referring / consulting)
        $others = $patient->activeCareProviders()
            ->whereIn('role', [PatientCareProvider::ROLE_REFERRING, PatientCareProvider::ROLE_CONSULTING])
            ->get();
        foreach ($others as $i => $provider) {
            $name = $provider->display_name;
            if (!$name || $name === $attendingName) {
                continue;
            }
            $team[] = [
                'id' => 'doctor-' . $i,
                'name' => $name,
                'role' => $provider->role === PatientCareProvider::ROLE_REFERRING
                    ? 'Referring Doctor'
                    : 'Consulting Specialist',
                'group' => 'doctor',
                'detail' => 'Part of the medical team looking after you.',
                'status' => null,
            ];
        }

        if ($patient->nurse) {
            $team[] = [
                'id' => 'primary-nurse',
                'name' => $patient->nurse->name,
                'role' => 'Primary Nurse',
                'group' => 'nurse',
                'detail' => 'Coordinates your day-to-day nursing care.',
                'status' => null,
            ];
        }

        // Nurse on duty for this bed in the roster slot on duty (after
        // midnight in the night shift, last night's ON)
        $currentSlot = $bed->ward_id ? RosterSlot::current($bed->ward_id) : null;
        if ($currentSlot) {
            $assignment = WardScheduleAssignment::where('ward_id', $bed->ward_id)
                ->where('scheduled_date', $currentSlot['date'])
                ->where('shift', $currentSlot['code'])
                ->where('bed_id', $bed->id)
                ->with('nurse')
                ->first();
            if ($assignment?->nurse && $assignment->nurse->id !== $patient->nurse_id) {
                $team[] = [
                    'id' => 'duty-nurse',
                    'name' => $assignment->nurse->name,
                    'role' => 'Nurse on Duty',
                    'group' => 'nurse',
                    'detail' => 'Looking after your bed this shift.',
                    'status' => 'On shift · ' . ($currentSlot['name'] ?? $currentSlot['code']),
                ];
            }
        }

        return $team;
    }
}
