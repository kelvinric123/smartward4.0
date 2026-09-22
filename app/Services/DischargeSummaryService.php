<?php

namespace App\Services;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Infusion;
use App\Models\PatientCareProvider;
use App\Models\ShiftSetting;
use App\Models\VitalSign;
use App\Models\WardScheduleAssignment;
use App\Support\AdmissionEpisode;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Assembles the discharge summary for one admission.
 *
 * An admission is not a row anywhere - it is the span between an admit /
 * check-in log and the discharge log that closes it (see AdmissionEpisode).
 * Everything the summary prints (vitals, ECGs, infusions) is therefore pulled
 * by time window rather than by a foreign key.
 */
class DischargeSummaryService
{
    /** Log actions that OPEN an admission. */
    public const ADMISSION_ACTIONS = ['admit', 'check-in'];

    /** Log action that CLOSES one. */
    public const DISCHARGE_ACTION = 'discharge';

    /** Roster shift codes, in the order the ward schedule runs them. */
    public const SHIFTS = ['AM', 'PM', 'ON'];

    /**
     * The summary's sections, in print order. The on-screen tabs and the
     * print-section chooser are both built from this, so a section added here
     * appears in both without either page being edited.
     */
    public const SECTIONS = [
        'patient' => 'Patient',
        'admission' => 'Admission',
        'discharge' => 'Discharge',
        'care-team' => 'Doctor & care team',
        'nursing' => 'Nursing roster',
        'vitals' => 'Vital signs',
        'ecg' => 'ECG recordings',
        'infusions' => 'Infusions',
    ];

    public function __construct(private EcgArchive $ecgArchive)
    {
    }

    // ------------------------------------------------------------------ list

    /**
     * Admissions for the list page, newest first, each with its episode
     * resolved so the row can show discharge state without an N+1.
     *
     * Filters: ward_id, search (name / MRN / bed), status
     * ('discharged'|'admitted'), from_date, to_date (against the admission
     * date).
     */
    public function admissions(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = AdmissionLog::with(['patient.consultant', 'ward', 'user'])
            ->whereIn('action', self::ADMISSION_ACTIONS)
            ->orderByRaw('COALESCE(admitted_at, created_at) DESC');

        if (!empty($filters['ward_id'])) {
            $query->where('ward_id', $filters['ward_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('mrn', 'like', '%' . $search . '%')
                    ->orWhere('patient_name', 'like', '%' . $search . '%')
                    ->orWhere('bed_number', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['from_date'])) {
            $query->whereRaw('DATE(COALESCE(admitted_at, created_at)) >= ?', [$filters['from_date']]);
        }

        if (!empty($filters['to_date'])) {
            $query->whereRaw('DATE(COALESCE(admitted_at, created_at)) <= ?', [$filters['to_date']]);
        }

        if (($filters['status'] ?? '') === 'discharged') {
            $query->whereRaw($this->hasClosingDischargeSql());
        } elseif (($filters['status'] ?? '') === 'admitted') {
            $query->whereRaw('NOT ' . $this->hasClosingDischargeSql());
        }

        $admissions = $query->paginate($perPage)->withQueryString();

        $this->attachEpisodes(collect($admissions->items()));

        return $admissions;
    }

    /**
     * Whether an admission row was closed by a discharge - the SQL twin of
     * resolveEpisode(), so the list's "Discharged" badge can never disagree
     * with the summary that row opens.
     *
     * The inner COALESCE is the "bounded by the next admission" rule: a
     * discharge only closes this admission if it was filed before the patient
     * was admitted again. With no later admission the bound collapses to the
     * discharge's own timestamp and the comparison is trivially true.
     */
    protected function hasClosingDischargeSql(): string
    {
        return <<<'SQL'
        EXISTS (
            SELECT 1
            FROM admission_logs d
            WHERE d.patient_id = admission_logs.patient_id
              AND d.action = 'discharge'
              AND d.created_at >= admission_logs.created_at
              AND d.created_at <= COALESCE((
                    SELECT MIN(n.created_at)
                    FROM admission_logs n
                    WHERE n.patient_id = admission_logs.patient_id
                      AND n.action IN ('admit', 'check-in')
                      AND n.created_at > admission_logs.created_at
              ), d.created_at)
        )
        SQL;
    }

    // -------------------------------------------------------------- episodes

    /**
     * Resolve and attach the episode for each admission row, loading every
     * relevant log for those patients in one query.
     */
    public function attachEpisodes(Collection $admissions): void
    {
        if ($admissions->isEmpty()) {
            return;
        }

        $logsByPatient = AdmissionLog::with('user')
            ->whereIn('patient_id', $admissions->pluck('patient_id')->unique()->all())
            ->whereIn('action', array_merge(self::ADMISSION_ACTIONS, [self::DISCHARGE_ACTION]))
            ->orderBy('created_at')
            ->get()
            ->groupBy('patient_id');

        foreach ($admissions as $admission) {
            $admission->setAttribute('episode', $this->resolveEpisode(
                $admission,
                $logsByPatient->get($admission->patient_id) ?? collect()
            ));
        }
    }

    /**
     * The episode for a single admission row.
     */
    public function episodeFor(AdmissionLog $admission): AdmissionEpisode
    {
        $logs = AdmissionLog::with('user')
            ->where('patient_id', $admission->patient_id)
            ->whereIn('action', array_merge(self::ADMISSION_ACTIONS, [self::DISCHARGE_ACTION]))
            ->orderBy('created_at')
            ->get();

        return $this->resolveEpisode($admission, $logs);
    }

    /**
     * Pair an admission with the discharge that closed it.
     *
     * Sequencing uses created_at (when the event was filed) rather than
     * admitted_at / discharged_at, because staff may back-date those and a
     * back-dated stay would otherwise reorder itself into the wrong episode.
     * The recorded times are still what gets displayed.
     */
    protected function resolveEpisode(AdmissionLog $admission, Collection $patientLogs): AdmissionEpisode
    {
        $admittedAt = $admission->admitted_at ?? $admission->created_at;

        $nextAdmission = $patientLogs
            ->first(fn(AdmissionLog $log) => in_array($log->action, self::ADMISSION_ACTIONS, true)
                && $log->created_at->gt($admission->created_at));

        $dischargeLog = $patientLogs
            ->first(fn(AdmissionLog $log) => $log->action === self::DISCHARGE_ACTION
                && $log->created_at->gte($admission->created_at)
                && (!$nextAdmission || $log->created_at->lte($nextAdmission->created_at)));

        $dischargedAt = null;
        if ($dischargeLog) {
            // discharged_at is only stamped on logs written since the column
            // was added; older rows fall back to when the log was filed.
            $dischargedAt = $dischargeLog->discharged_at ?? $dischargeLog->created_at;
        } elseif (!$nextAdmission && $admission->patient?->status === 'discharged') {
            // Discharged without a closing log (legacy data / direct edit).
            $dischargedAt = $admission->patient->discharged_at;
        }

        // Displayed times can be back-dated into nonsense; the window used to
        // collect readings widens to the filing times instead, so nothing
        // recorded during the stay is silently dropped from the summary.
        $windowStart = $admittedAt->lt($admission->created_at) ? $admittedAt : $admission->created_at;
        $windowEnd = now();

        if ($dischargeLog) {
            $recorded = $dischargedAt ?? $dischargeLog->created_at;
            $windowEnd = $recorded->gt($dischargeLog->created_at) ? $recorded : $dischargeLog->created_at;
        } elseif ($dischargedAt) {
            $windowEnd = $dischargedAt->gt($windowStart) ? $dischargedAt : $windowStart;
        }

        return new AdmissionEpisode(
            admissionLog: $admission,
            dischargeLog: $dischargeLog,
            admittedAt: $admittedAt,
            dischargedAt: $dischargedAt,
            windowStart: $windowStart,
            windowEnd: $windowEnd,
        );
    }

    // --------------------------------------------------------------- summary

    /**
     * Everything the discharge summary prints for one admission.
     */
    public function summary(AdmissionLog $admission): array
    {
        $episode = $this->episodeFor($admission);
        $vitalSigns = $this->vitalSigns($episode);

        return [
            'episode' => $episode,
            'admission' => $admission,
            'patient' => $admission->patient,
            'sections' => self::SECTIONS,
            'careTeam' => $this->careTeam($episode),
            'nursingRoster' => $this->nursingRoster($episode),
            'vitalSigns' => $vitalSigns,
            'vitalRanges' => $this->vitalRanges($vitalSigns),
            'ecgFiles' => $this->ecgFiles($episode),
            'infusions' => $this->infusions($episode),
        ];
    }

    /**
     * The nursing team as the ward roster actually staffed it: every nurse
     * rostered to a bed this patient occupied, on any shift falling inside the
     * admission.
     *
     * The patient's nurse_id is only ever the one nurse currently attached to
     * the record, which for a stay of any length is not the team that looked
     * after them - the ward schedule is.
     */
    protected function nursingRoster(AdmissionEpisode $episode): array
    {
        $beds = $this->bedsOccupied($episode);

        $empty = [
            'beds' => $beds,
            'nurses' => collect(),
            'assignmentCount' => 0,
            'shiftLabels' => $this->shiftLabels($episode->admissionLog->ward_id),
        ];

        if ($beds->isEmpty()) {
            return $empty;
        }

        $assignments = WardScheduleAssignment::with(['nurse.taggingNurses', 'bed'])
            ->whereIn('bed_id', $beds->pluck('id')->all())
            ->whereBetween('scheduled_date', [
                $episode->windowStart->toDateString(),
                $episode->windowEnd->toDateString(),
            ])
            ->orderBy('scheduled_date')
            ->get()
            ->filter(fn(WardScheduleAssignment $a) => $a->nurse !== null);

        if ($assignments->isEmpty()) {
            return $empty;
        }

        $nurses = $assignments
            ->groupBy('nurse_id')
            ->map(function (Collection $rows) {
                $shifts = [];
                foreach (self::SHIFTS as $shift) {
                    $shifts[$shift] = $rows->where('shift', $shift)->count();
                }

                return [
                    'nurse' => $rows->first()->nurse,
                    'shifts' => $shifts,
                    'total' => $rows->count(),
                    'firstDate' => $rows->min('scheduled_date'),
                    'lastDate' => $rows->max('scheduled_date'),
                    'beds' => $rows->pluck('bed')->filter()
                        ->map(fn(Bed $bed) => $bed->bed_display_name ?: $bed->bed_number)
                        ->unique()->sort()->values(),
                ];
            })
            ->sortBy([
                fn($a, $b) => $b['total'] <=> $a['total'],
                fn($a, $b) => strcmp((string) $a['nurse']->name, (string) $b['nurse']->name),
            ])
            ->values();

        return [
            'beds' => $beds,
            'nurses' => $nurses,
            'assignmentCount' => $assignments->count(),
            'shiftLabels' => $this->shiftLabels($episode->admissionLog->ward_id),
        ];
    }

    /**
     * Every bed the patient occupied during the stay: the one they were
     * admitted to, plus each bed a transfer moved them to.
     */
    protected function bedsOccupied(AdmissionEpisode $episode): Collection
    {
        $admission = $episode->admissionLog;

        $places = collect([[$admission->ward_id, $admission->bed_number]]);

        AdmissionLog::where('patient_id', $admission->patient_id)
            ->where('action', 'transfer')
            ->whereBetween('created_at', [$episode->windowStart, $episode->windowEnd])
            ->get(['ward_id', 'bed_number'])
            ->each(fn(AdmissionLog $transfer) => $places->push([$transfer->ward_id, $transfer->bed_number]));

        $places = $places
            ->filter(fn(array $place) => $place[0] && $place[1])
            ->unique(fn(array $place) => $place[0] . '|' . $place[1]);

        if ($places->isEmpty()) {
            return collect();
        }

        return Bed::with('ward')
            ->where(function (Builder $query) use ($places) {
                foreach ($places as [$wardId, $bedNumber]) {
                    $query->orWhere(fn(Builder $q) => $q
                        ->where('ward_id', $wardId)
                        ->where('bed_number', $bedNumber));
                }
            })
            ->get();
    }

    /**
     * Shift code -> the ward's own name for it ("AM" => "Morning"), falling
     * back to the bare code when the ward has no shift settings.
     */
    protected function shiftLabels(?int $wardId): array
    {
        $labels = array_combine(self::SHIFTS, self::SHIFTS);

        if (!$wardId) {
            return $labels;
        }

        foreach (ShiftSetting::where('ward_id', $wardId)->get() as $setting) {
            if (isset($labels[$setting->shift_code]) && $setting->shift_name) {
                $labels[$setting->shift_code] = $setting->shift_name;
            }
        }

        return $labels;
    }

    /**
     * Doctors, anaesthetists and nurses attached to this stay.
     *
     * The admission log keeps the consultant / nurse names as they were at
     * admission; the patient record holds who is on the case now. Both are
     * reported, because on an older admission they are frequently not the
     * same people.
     */
    protected function careTeam(AdmissionEpisode $episode): array
    {
        $patient = $episode->admissionLog->patient;

        $roleOrder = [
            PatientCareProvider::ROLE_ATTENDING => 0,
            PatientCareProvider::ROLE_REFERRING => 1,
            PatientCareProvider::ROLE_CONSULTING => 2,
        ];

        $providers = $patient
            ? PatientCareProvider::with(['consultant.specialty', 'anaesthetist'])
                ->where('patient_id', $patient->id)
                ->where(fn(Builder $q) => $q->where('is_active', true)
                    ->orWhereBetween('assigned_at', [$episode->windowStart, $episode->windowEnd]))
                ->get()
                ->sortBy(fn(PatientCareProvider $p) => [$roleOrder[$p->role] ?? 9, (string) $p->doctor_name])
                ->values()
            : collect();

        return [
            'consultant' => $patient?->consultant?->loadMissing('specialty'),
            'consultantAtAdmission' => $episode->admissionLog->consultant_name,
            'anaesthetist' => $patient?->anaesthetist,
            'nurse' => $patient?->nurse,
            'nurseAtAdmission' => $episode->admissionLog->nurse_name,
            'careProviders' => $providers,
            'anaesthetistProviders' => $providers->filter(fn($p) => $p->anaesthetist_id !== null)->values(),
            'admittedBy' => $episode->admissionLog->user,
            'dischargedBy' => $episode->dischargeLog?->user,
        ];
    }

    protected function vitalSigns(AdmissionEpisode $episode): Collection
    {
        return VitalSign::with(['operator', 'recordedBy'])
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->whereBetween('recorded_at', [$episode->windowStart, $episode->windowEnd])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Min / max / average per parameter over the stay - the part of a vitals
     * chart a discharge summary is actually read for.
     */
    protected function vitalRanges(Collection $vitalSigns): array
    {
        $parameters = [
            'systolic_bp' => ['label' => 'Systolic BP', 'unit' => 'mmHg', 'decimals' => 0],
            'diastolic_bp' => ['label' => 'Diastolic BP', 'unit' => 'mmHg', 'decimals' => 0],
            'pulse_rate' => ['label' => 'Pulse', 'unit' => 'bpm', 'decimals' => 0],
            'temperature' => ['label' => 'Temperature', 'unit' => '&deg;C', 'decimals' => 1],
            'spo2' => ['label' => 'SpO2', 'unit' => '%', 'decimals' => 0],
            'respiratory_rate' => ['label' => 'Resp. rate', 'unit' => '/min', 'decimals' => 0],
        ];

        $ranges = [];

        foreach ($parameters as $field => $meta) {
            $values = $vitalSigns
                ->pluck($field)
                ->filter(fn($value) => $value !== null && $value !== '')
                ->map(fn($value) => (float) $value);

            if ($values->isEmpty()) {
                continue;
            }

            $ranges[] = $meta + [
                'field' => $field,
                'min' => $values->min(),
                'max' => $values->max(),
                'avg' => $values->avg(),
                'count' => $values->count(),
            ];
        }

        return $ranges;
    }

    /**
     * ECGs recorded during the stay. The store is matched on MRN / RN, so a
     * patient whose MRN changed mid-stay can only be matched on the current
     * one - the same limitation the ECG viewer has.
     */
    protected function ecgFiles(AdmissionEpisode $episode): Collection
    {
        $patient = $episode->admissionLog->patient;

        if (!$patient) {
            return collect();
        }

        try {
            $files = $this->ecgArchive->filesForPatient($patient->mrn, $patient->rn, $patient->id);
        } catch (\Throwable $e) {
            Log::warning('Discharge summary: ECG store unreadable: ' . $e->getMessage());

            return collect();
        }

        return collect($files)
            ->filter(function (array $file) use ($episode) {
                $recordedAt = $this->parseTimestamp($file['recorded_at'] ?? null);

                return $recordedAt && $recordedAt->betweenIncluded($episode->windowStart, $episode->windowEnd);
            })
            ->sortBy('recorded_at')
            ->values();
    }

    /**
     * Infusions that ran during the stay.
     *
     * Completed infusions are snapshotted into the infusions table when a pump
     * is unbound, so history lives there. Live state does not - in engine mode
     * the table's open rows are stale - so for a patient who is still admitted
     * the running infusions are read from the engine and the stale local rows
     * for those same pumps are dropped.
     */
    protected function infusions(AdmissionEpisode $episode): Collection
    {
        $stored = Infusion::with('infusionPump')
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->get()
            ->filter(fn(Infusion $infusion) => $this->infusionOverlapsWindow($infusion, $episode));

        $live = collect();

        if (!$episode->isDischarged() && InfusionEngineClient::engineModeActive()) {
            try {
                $live = EngineInfusionService::make()
                    ->infusionsForPatient((int) $episode->admissionLog->patient_id);
            } catch (\Throwable $e) {
                Log::warning('Discharge summary: infusion engine unreachable: ' . $e->getMessage());
            }
        }

        if ($live->isNotEmpty()) {
            $livePumpIds = $live->pluck('infusion_pump_id')->filter()->all();
            $stored = $stored->reject(fn(Infusion $infusion) => $infusion->status !== Infusion::STATUS_COMPLETED
                && in_array($infusion->infusion_pump_id, $livePumpIds));
        }

        return $stored->concat($live)
            ->sortBy(fn(Infusion $infusion) => $this->infusionStartedAt($infusion)?->timestamp ?? 0)
            ->values();
    }

    protected function infusionOverlapsWindow(Infusion $infusion, AdmissionEpisode $episode): bool
    {
        $from = $this->infusionStartedAt($infusion);
        $to = $infusion->completed_at ?? $infusion->last_updated_at ?? $from;

        if (!$from || !$to) {
            return false;
        }

        return $from->lte($episode->windowEnd) && $to->gte($episode->windowStart);
    }

    protected function infusionStartedAt(Infusion $infusion): ?Carbon
    {
        $started = $infusion->started_at ?? $infusion->last_updated_at ?? $infusion->created_at;

        return $started ? Carbon::parse($started) : null;
    }

    protected function parseTimestamp(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
