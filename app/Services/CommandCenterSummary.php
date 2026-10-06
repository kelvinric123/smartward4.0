<?php

namespace App\Services;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\NurseRosterEntry;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Models\WardScheduleAssignment;
use App\Services\NurseScheduling\RosterRules;
use App\Services\NurseScheduling\RosterSlot;
use App\Services\NurseScheduling\WardShifts;
use App\Support\ClinicalIndicatorReadings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Command Center V2: every ward summed up for hospital management, on the
 * command centre's screen. Aggregate figures only, never a patient's name.
 * Wards of an Emergency ward type are left out: the ED has its own board
 * (CommandCenterEd).
 *
 * Four areas, hospital-wide and per ward:
 *  - capacity: beds by status, and occupancy against OCCUPANCY_TARGET;
 *  - patient flow: today's admissions and discharges from the admission log,
 *    the share discharged by noon and the average length of stay;
 *  - quality and safety: the flags CommandCenterLive raises from the ward
 *    dashboards' own sources, vital signs in the last day and ward alerts;
 *  - workforce: the nurses on each ward's current shift in the AI Nurse
 *    Schedule against the ward's minimum staffing.
 * From them comes the list management should look at first, most serious first.
 */
final class CommandCenterSummary
{
    /** How often the screen asks for fresh figures. */
    public const REFRESH_SECONDS = 30;

    /** Bed occupancy (%) to stay at or under; at OCCUPANCY_CRITICAL a ward or the hospital is all but full. */
    public const OCCUPANCY_TARGET = 85;
    public const OCCUPANCY_CRITICAL = 95;

    /** The daily trends run this many days, today included. */
    public const TREND_DAYS = 14;

    /** Average length of stay covers the patients discharged in this many days, against the same span before. */
    public const LOS_DAYS = 30;

    /** A discharge "by noon" is one before this hour. */
    public const NOON_HOUR = 12;

    /** An alert left unanswered longer than this is overdue for a response. */
    public const ALERT_WAIT_MINUTES = 15;

    /** A patient admitted this recently is not yet due their first vital signs. */
    public const OBS_GRACE_MINUTES = 60;

    /** How much of the attention list the screen shows; the rest are counted. */
    public const ATTENTION_LIMIT = 12;

    /** The status scale, worst first. "good" means nothing to report. */
    public const SEVERITIES = ['critical', 'serious', 'warning', 'good'];

    /** Within a severity, the attention list runs in this order of topic. */
    public const TOPICS = ['capacity', 'alerts', 'escalation', 'ews', 'staffing', 'overdue', 'observations'];

    private const ACTIVE = [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE];

    /** The acuity and risk measures, in the order shown: key => [label, short label] */
    private const ACUITY = [
        'high_acuity' => ['High acuity (nursing level 3–4)', 'High acuity'],
        'ventilated' => ['Ventilated', 'Ventilated'],
        'high_fall' => ['High fall risk', 'High fall risk'],
        'isolation' => ['Isolation precautions', 'Isolation'],
        'transfusing' => ['Blood transfusion running', 'Transfusing'],
    ];

    /**
     * @param  string  $ewsSystem  the EWS the viewer's ward dashboard scores with
     */
    public static function build(string $ewsSystem = 'ews_ihh'): array
    {
        $now = now();
        // The ED's zones (Emergency ward types) have their own board, Command Center V2 (ED)
        $wards = Ward::where('is_active', true)->notEmergency()->orderBy('ward_name')->get();
        // Also loads each ward's type and active beds, which the rest reuses
        $live = CommandCenterLive::build($wards, $ewsSystem);
        $wardIds = $wards->modelKeys();

        $inpatients = Patient::whereIn('ward_id', $wardIds)
            ->where('is_active', true)
            ->whereIn('status', self::ACTIVE)
            ->get(['id', 'ward_id', 'admitted_at', 'fall_risk', 'isolation_type', 'nursing_level']);

        $flow = self::flow($wardIds, $inpatients->count(), $now);
        $observed = self::observations($inpatients, $now);
        $alerts = self::alerts($wardIds, $now);
        $staffing = self::staffing($wards, $inpatients->countBy('ward_id'), $now);

        $wardsById = $wards->keyBy('id');
        $risks = $inpatients->groupBy('ward_id')->map(fn (Collection $patients) => self::riskCounts($patients));
        $rows = collect($live['wards'])->map(fn (array $row) => self::wardRow(
            $row, $wardsById[$row['id']], $flow, $observed, $alerts['wards'], $staffing, $risks->get($row['id'], self::riskCounts(collect()))
        ));

        $capacity = self::capacity($rows);
        $attention = self::attention($rows, $capacity);
        $rows = $rows->map(fn (array $row) => $row + [
            'status' => self::worst($attention->where('ward_id', $row['id'])->pluck('severity')),
        ]);

        return [
            'generated_at' => $now->toIso8601String(),
            'generated_time' => $now->format('H:i:s'),
            'refresh_seconds' => self::REFRESH_SECONDS,
            'targets' => [
                'occupancy' => self::OCCUPANCY_TARGET,
                'occupancy_critical' => self::OCCUPANCY_CRITICAL,
                'alert_wait_minutes' => self::ALERT_WAIT_MINUTES,
                'noon_hour' => self::NOON_HOUR,
                'los_days' => self::LOS_DAYS,
                'obs_hours' => CommandCenterLive::EWS_CURRENT_HOURS,
            ],
            'capacity' => $capacity,
            'flow' => $flow['summary'],
            'quality' => self::quality($rows, $live, $alerts, $attention),
            'workforce' => self::workforce($rows),
            'acuity' => self::acuity($inpatients, $rows),
            'wards' => $rows->values()->all(),
            'attention' => $attention->take(self::ATTENTION_LIMIT)->map(fn (array $item) => array_diff_key($item, ['weight' => 0]))->values()->all(),
            'attention_total' => $attention->count(),
            'trend' => $flow['trend'],
        ];
    }

    /** One ward's line: beds, flow, clinical flags, acuity, alerts and staffing. */
    private static function wardRow(array $live, Ward $ward, array $flow, array $observed, array $alerts, array $staffing, array $risks): array
    {
        $beds = $ward->beds;
        $occupancy = self::percent($live['occupied'], $beds->count());
        $obs = $observed[$ward->id] ?? ['due' => 0, 'done' => 0];
        $wardAlerts = $alerts[$ward->id] ?? ['pending' => 0, 'urgent' => 0, 'waiting' => 0, 'waiting_urgent' => 0, 'oldest' => null];

        return [
            'id' => $ward->id,
            'name' => $ward->ward_name,
            'code' => $ward->ward_code,
            'type' => $live['type'],
            'critical_care' => $live['critical_care'],
            'url' => $live['dashboard_url'],
            'beds' => $beds->count(),
            'occupied' => $live['occupied'],
            'free' => $beds->where('status', 'available')->count(),
            'reserved' => $beds->where('status', 'reserved')->count(),
            'maintenance' => $beds->where('status', Bed::STATUS_MAINTENANCE)->count(),
            'occupancy' => $occupancy,
            'occupancy_status' => self::occupancyStatus($occupancy),
            'inpatients' => $live['inpatients'],
            'incoming' => $live['incoming'],
            'pending_discharge' => $live['pending_discharge'],
            'admissions_today' => $flow['admissions_today'][$ward->id] ?? 0,
            'discharges_today' => $flow['discharges_today'][$ward->id] ?? 0,
            'ews_urgent' => $live['ews_urgent'],
            'ews_warning' => $live['ews_warning'],
            'escalations' => $live['escalations'],
            'ventilated' => $live['ventilated'],
            'transfusing' => $live['transfusing'],
            'high_acuity' => $risks['high_acuity'],
            'high_fall' => $risks['high_fall'],
            'isolation' => $risks['isolation'],
            'overdue_care' => $live['overdue_care'],
            'obs_due' => $obs['due'],
            'obs_done' => $obs['done'],
            'obs_pct' => self::percent($obs['done'], $obs['due']),
            'alerts' => $wardAlerts['pending'],
            'alerts_urgent' => $wardAlerts['urgent'],
            'alerts_waiting' => $wardAlerts['waiting'],
            'alerts_waiting_urgent' => $wardAlerts['waiting_urgent'],
            'alerts_oldest' => $wardAlerts['oldest'],
            'staffing' => $staffing[$ward->id] ?? null,
        ];
    }

    private static function capacity(Collection $rows): array
    {
        $beds = $rows->sum('beds');
        $occupied = $rows->sum('occupied');
        $occupancy = self::percent($occupied, $beds);
        $criticalCare = $rows->where('critical_care', true);

        return [
            'wards' => $rows->count(),
            'beds' => $beds,
            'occupied' => $occupied,
            'free' => $rows->sum('free'),
            'reserved' => $rows->sum('reserved'),
            'maintenance' => $rows->sum('maintenance'),
            'occupancy' => $occupancy,
            'status' => self::occupancyStatus($occupancy),
            'incoming' => $rows->sum('incoming'),
            'pending_discharge' => $rows->sum('pending_discharge'),
            'critical_care' => $criticalCare->isEmpty() ? null : [
                'wards' => $criticalCare->count(),
                'beds' => $criticalCare->sum('beds'),
                'occupied' => $criticalCare->sum('occupied'),
                'free' => $criticalCare->sum('free'),
                'ventilated' => $criticalCare->sum('ventilated'),
            ],
        ];
    }

    /**
     * Admissions and discharges from the admission log, which keeps every
     * stay: a readmission reuses the patient record and clears its discharge
     * time, but never the log. A busy hospital logs thousands in the sixty
     * days read here, so they are kept as plain timestamps.
     *
     * The midnight census is worked back from the inpatients now: each
     * midnight had the next day's count, less that day's admissions, plus its
     * discharges. So it always agrees with the admissions and discharges beside it.
     */
    private static function flow(array $wardIds, int $inpatients, CarbonInterface $now): array
    {
        $today = $now->copy()->startOfDay();
        $trendStart = $today->copy()->subDays(self::TREND_DAYS - 1);
        $losStart = $now->copy()->subDays(self::LOS_DAYS * 2);
        $midnight = $today->getTimestamp();
        $yesterday = $today->copy()->subDay()->getTimestamp();
        $sameTimeYesterday = $now->copy()->subDay()->getTimestamp();
        $losSplit = $now->copy()->subDays(self::LOS_DAYS)->getTimestamp();

        $admits = AdmissionLog::where('action', 'admit')
            ->whereIn('ward_id', $wardIds)
            ->whereBetween('admitted_at', [$trendStart, $now])
            ->toBase()
            ->get(['ward_id', 'admitted_at'])
            ->map(fn (object $log) => ['ward_id' => (int) $log->ward_id, 'at' => strtotime($log->admitted_at)]);

        $discharges = AdmissionLog::where('action', 'discharge')
            ->whereIn('ward_id', $wardIds)
            ->whereBetween('discharged_at', [$trendStart->min($losStart), $now])
            ->toBase()
            ->get(['patient_id', 'ward_id', 'discharged_at'])
            ->map(fn (object $log) => ['patient_id' => $log->patient_id, 'ward_id' => (int) $log->ward_id, 'at' => strtotime($log->discharged_at)]);

        $between = fn (Collection $logs, int $from, int $to) => $logs->filter(fn (array $log) => $log['at'] >= $from && $log['at'] <= $to);
        $byNoon = fn (Collection $logs) => $logs->filter(fn (array $log) => (int) date('G', $log['at']) < self::NOON_HOUR)->count();

        $admittedToday = $between($admits, $midnight, PHP_INT_MAX);
        $dischargedToday = $between($discharges, $midnight, PHP_INT_MAX);
        $recent = $discharges->filter(fn (array $log) => $log['at'] > $losSplit);
        $stays = self::stayLengths($between($discharges, $losStart->getTimestamp(), PHP_INT_MAX));
        $recentStays = $stays->filter(fn (array $stay) => $stay['at'] > $losSplit)->pluck('days');
        $earlierStays = $stays->filter(fn (array $stay) => $stay['at'] <= $losSplit)->pluck('days');

        $trend = ['dates' => [], 'labels' => [], 'days' => [], 'census' => [], 'admissions' => [], 'discharges' => []];
        $admitsPerDay = $admits->countBy(fn (array $log) => date('Y-m-d', $log['at']));
        $dischargesPerDay = $discharges->countBy(fn (array $log) => date('Y-m-d', $log['at']));
        for ($offset = 0; $offset < self::TREND_DAYS; $offset++) {
            $day = $trendStart->copy()->addDays($offset);
            $trend['dates'][] = $day->toDateString();
            $trend['labels'][] = $day->format('j M');
            $trend['days'][] = $day->format('D j M');
            $trend['admissions'][] = $admitsPerDay[$day->toDateString()] ?? 0;
            $trend['discharges'][] = $dischargesPerDay[$day->toDateString()] ?? 0;
        }
        $census = [self::TREND_DAYS - 1 => $inpatients];
        for ($i = self::TREND_DAYS - 2; $i >= 0; $i--) {
            $census[$i] = $census[$i + 1] - $trend['admissions'][$i + 1] + $trend['discharges'][$i + 1];
        }
        ksort($census);
        $trend['census'] = array_map(fn (int $count) => max(0, $count), array_values($census));

        return [
            'admissions_today' => $admittedToday->countBy('ward_id')->all(),
            'discharges_today' => $dischargedToday->countBy('ward_id')->all(),
            'summary' => [
                'admissions_today' => $admittedToday->count(),
                'admissions_same_time_yesterday' => $between($admits, $yesterday, $sameTimeYesterday)->count(),
                'discharges_today' => $dischargedToday->count(),
                'discharges_same_time_yesterday' => $between($discharges, $yesterday, $sameTimeYesterday)->count(),
                'discharged_by_noon' => $byNoon($dischargedToday),
                'discharged_by_noon_pct' => self::percent($byNoon($dischargedToday), $dischargedToday->count()),
                'discharged_by_noon_pct_period' => self::percent($byNoon($recent), $recent->count()),
                'discharges_period' => $recent->count(),
                'alos' => $recentStays->isEmpty() ? null : round($recentStays->avg(), 1),
                'alos_previous' => $earlierStays->isEmpty() ? null : round($earlierStays->avg(), 1),
                'alos_stays' => $recentStays->count(),
            ],
            'trend' => $trend,
        ];
    }

    /**
     * How long each discharged patient stayed: from their latest admission
     * before the discharge, or failing a logged one the admission time on
     * their record, while that is still the stay's.
     *
     * @param  Collection<int, array{patient_id: ?int, at: int}>  $discharges
     * @return Collection<int, array{at: int, days: float}>
     */
    private static function stayLengths(Collection $discharges): Collection
    {
        $patientIds = $discharges->pluck('patient_id')->filter()->unique()->values();
        if ($patientIds->isEmpty()) {
            return collect();
        }

        $admitted = AdmissionLog::where('action', 'admit')
            ->whereIn('patient_id', $patientIds)
            ->whereNotNull('admitted_at')
            ->toBase()
            ->get(['patient_id', 'admitted_at'])
            ->groupBy('patient_id')
            ->map(fn (Collection $logs) => $logs->map(fn (object $log) => strtotime($log->admitted_at))->all());
        $onRecord = Patient::whereIn('id', $patientIds)
            ->whereNotNull('admitted_at')
            ->toBase()
            ->pluck('admitted_at', 'id')
            ->map(fn (string $admittedAt) => strtotime($admittedAt));

        return $discharges->map(function (array $discharge) use ($admitted, $onRecord) {
            $before = array_filter($admitted[$discharge['patient_id']] ?? [], fn (int $at) => $at <= $discharge['at']);
            $recorded = $onRecord[$discharge['patient_id']] ?? null;
            $start = $before ? max($before) : ($recorded !== null && $recorded <= $discharge['at'] ? $recorded : null);

            return $start === null ? null : ['at' => $discharge['at'], 'days' => ($discharge['at'] - $start) / 86400];
        })->filter()->values();
    }

    /**
     * Per ward, the inpatients due vital signs within the last day and how
     * many had them. Someone admitted within OBS_GRACE_MINUTES is not due yet,
     * unless they have been observed already.
     *
     * @return array<int, array{due: int, done: int}>
     */
    private static function observations(EloquentCollection $inpatients, CarbonInterface $now): array
    {
        $observed = $inpatients->isEmpty() ? collect() : VitalSign::whereIn('patient_id', $inpatients->modelKeys())
            ->whereBetween('recorded_at', [$now->copy()->subHours(CommandCenterLive::EWS_CURRENT_HOURS), $now])
            ->distinct()
            ->pluck('patient_id')
            ->flip();
        $settled = $now->copy()->subMinutes(self::OBS_GRACE_MINUTES);

        $wards = [];
        foreach ($inpatients as $patient) {
            $done = $observed->has($patient->id);
            if (!$done && $patient->admitted_at && $patient->admitted_at->gt($settled)) {
                continue;
            }
            $wards[$patient->ward_id]['due'] = ($wards[$patient->ward_id]['due'] ?? 0) + 1;
            $wards[$patient->ward_id]['done'] = ($wards[$patient->ward_id]['done'] ?? 0) + ($done ? 1 : 0);
        }

        return $wards;
    }

    /**
     * Ward alerts waiting now, and how quickly staff answered them. Only a
     * response by someone counts: alerts the system closed itself (an EWS back
     * to normal, a pump alarm that cleared) have no responder.
     */
    private static function alerts(array $wardIds, CarbonInterface $now): array
    {
        $pending = WardNotification::pending()
            ->whereIn('ward_id', $wardIds)
            ->get(['id', 'ward_id', 'severity', 'created_at'])
            ->map(fn (WardNotification $alert) => [
                'ward_id' => $alert->ward_id,
                'urgent' => $alert->severity === WardNotification::SEVERITY_URGENT,
                'minutes' => max(0, $alert->created_at->diffInMinutes($now)),
            ]);
        $waiting = $pending->where('minutes', '>', self::ALERT_WAIT_MINUTES);

        $answered = WardNotification::responded()
            ->whereIn('ward_id', $wardIds)
            ->whereNotNull('responded_by')
            ->whereBetween('responded_at', [$now->copy()->subDays(7), $now])
            ->get(['created_at', 'responded_at'])
            ->map(fn (WardNotification $alert) => [
                'today' => $alert->responded_at->isSameDay($now),
                'minutes' => max(0, $alert->created_at->diffInSeconds($alert->responded_at) / 60),
            ]);
        $answeredToday = $answered->where('today', true);

        $wards = [];
        foreach ($pending->groupBy('ward_id') as $wardId => $alerts) {
            $late = $alerts->where('minutes', '>', self::ALERT_WAIT_MINUTES);
            $wards[$wardId] = [
                'pending' => $alerts->count(),
                'urgent' => $alerts->where('urgent', true)->count(),
                'waiting' => $late->count(),
                'waiting_urgent' => $late->where('urgent', true)->count(),
                'oldest' => (int) floor($alerts->max('minutes')),
            ];
        }

        return [
            'wards' => $wards,
            'pending' => $pending->count(),
            'urgent' => $pending->where('urgent', true)->count(),
            'waiting' => $waiting->count(),
            'waiting_urgent' => $waiting->where('urgent', true)->count(),
            'oldest' => $pending->isEmpty() ? null : (int) floor($pending->max('minutes')),
            'answered_today' => $answeredToday->count(),
            'median_today' => self::median($answeredToday->pluck('minutes')),
            'median_week' => self::median($answered->pluck('minutes')),
        ];
    }

    /**
     * The nurses on each ward's current shift in the AI Nurse Schedule, read
     * as its roster board does: a nurse's own roster entry for the day says
     * where and which shift they work; without one, the beds they hold in the
     * Ward Schedule do. A ward with neither for the day has no roster (null).
     *
     * The ON shift on a date is the night that starts that evening, so in the
     * small hours the night on duty is the one dated yesterday (RosterSlot).
     *
     * @param  Collection<int, int>  $inpatients  per ward id
     * @return array<int, array|null>
     */
    private static function staffing(EloquentCollection $wards, Collection $inpatients, CarbonInterface $now): array
    {
        $plans = [];
        foreach ($wards as $ward) {
            $slot = RosterSlot::current($ward->id, $now);
            if ($slot === null) {
                continue;
            }
            $plans[$ward->id] = [
                'ward' => $ward,
                'shift' => ['code' => $slot['code'], 'name' => $slot['name'], 'time' => $slot['time']],
                'date' => $slot['date'],
            ];
        }

        $dates = array_values(array_unique(array_column($plans, 'date')));
        $entries = $dates === [] ? collect() : NurseRosterEntry::whereIn('roster_date', $dates)
            ->get(['ward_id', 'nurse_id', 'roster_date', 'shift']);
        $assignments = $dates === [] ? collect() : WardScheduleAssignment::whereIn('scheduled_date', $dates)
            ->get(['ward_id', 'nurse_id', 'scheduled_date', 'shift']);
        $hasEntry = $entries->mapWithKeys(fn (NurseRosterEntry $entry) => [$entry->nurse_id . '|' . $entry->roster_date->toDateString() => true]);

        $staffing = [];
        foreach ($plans as $wardId => ['ward' => $ward, 'shift' => $shift, 'date' => $date]) {
            $rostered = $entries->filter(fn (NurseRosterEntry $entry) => (int) $entry->ward_id === $wardId && $entry->roster_date->toDateString() === $date);
            $held = $assignments->filter(fn (WardScheduleAssignment $bed) => (int) $bed->ward_id === $wardId && $bed->scheduled_date->toDateString() === $date);
            if ($rostered->isEmpty() && $held->isEmpty()) {
                $staffing[$wardId] = null;
                continue;
            }

            $onDuty = $rostered->where('shift', $shift['code'])->pluck('nurse_id')
                ->merge($held->groupBy('nurse_id')
                    ->reject(fn (Collection $beds, int $nurseId) => $hasEntry->has($nurseId . '|' . $date))
                    ->filter(fn (Collection $beds) => collect(WardShifts::CODES)->first(fn (string $code) => $beds->contains('shift', $code)) === $shift['code'])
                    ->keys())
                ->unique()
                ->count();
            $minimum = RosterRules::forWard($ward)->minStaff[$shift['code']] ?? 0;
            $patients = (int) ($inpatients[$wardId] ?? 0);

            $staffing[$wardId] = [
                'shift' => $shift['code'],
                'shift_name' => $shift['name'],
                'shift_time' => $shift['time'],
                'on_duty' => $onDuty,
                'minimum' => $minimum,
                'patients' => $patients,
                'ratio' => $onDuty > 0 ? round($patients / $onDuty, 1) : null,
                'status' => $onDuty < $minimum ? 'serious' : 'good',
            ];
        }

        return $staffing;
    }

    private static function quality(Collection $rows, array $live, array $alerts, Collection $attention): array
    {
        $due = $rows->sum('obs_due');
        $done = $rows->sum('obs_done');
        $clinical = $attention->whereIn('topic', ['ews', 'escalation', 'alerts', 'overdue', 'observations']);

        return [
            'inpatients' => $rows->sum('inpatients'),
            'needs_attention' => $live['attention_total'],
            'ews_urgent' => $rows->sum('ews_urgent'),
            'ews_warning' => $rows->sum('ews_warning'),
            'escalations' => $rows->sum('escalations'),
            'overdue_care' => $rows->sum('overdue_care'),
            'assessments_overdue' => $live['stats']['assessments_overdue'],
            'doses_overdue' => $live['stats']['doses_overdue'],
            'fluid_alerts' => $live['stats']['fluid_alerts'],
            'obs_due' => $due,
            'obs_done' => $done,
            'obs_pct' => self::percent($done, $due),
            'alerts' => array_diff_key($alerts, ['wards' => 0]),
            'status' => self::worst($clinical->pluck('severity')),
        ];
    }

    private static function workforce(Collection $rows): array
    {
        $rostered = $rows->filter(fn (array $row) => $row['staffing'] !== null);
        $onDuty = $rostered->sum('staffing.on_duty');
        $minimum = $rostered->sum('staffing.minimum');
        $patients = $rostered->sum('inpatients');
        $below = $rostered->where('staffing.status', 'serious')->count();

        return [
            'wards' => $rows->count(),
            'rostered_wards' => $rostered->count(),
            'shifts' => $rostered->pluck('staffing.shift')->unique()->values()->all(),
            'on_duty' => $onDuty,
            'minimum' => $minimum,
            'coverage' => self::percent($onDuty, $minimum),
            'patients' => $patients,
            'ratio' => $onDuty > 0 ? round($patients / $onDuty, 1) : null,
            'below_minimum' => $below,
            'status' => $rostered->isEmpty() ? null : ($below > 0 ? 'serious' : 'good'),
        ];
    }

    /** Share of inpatients carrying each kind of acuity or risk; each ward's line has its own counts. */
    private static function acuity(EloquentCollection $inpatients, Collection $rows): array
    {
        $counts = self::riskCounts($inpatients) + ['ventilated' => $rows->sum('ventilated'), 'transfusing' => $rows->sum('transfusing')];

        return collect(self::ACUITY)->map(fn (array $labels, string $key) => [
            'key' => $key,
            'label' => $labels[0],
            'short' => $labels[1],
            'count' => $counts[$key],
            'pct' => self::percent($counts[$key], $inpatients->count()),
        ])->values()->all();
    }

    /** How many of these patients are high acuity (nursing level 3–4), at high risk of falling, or isolated. */
    private static function riskCounts(Collection $patients): array
    {
        $in = fn (string $field, array $values) => $patients
            ->filter(fn (Patient $patient) => in_array(strtolower((string) $patient->{$field}), $values, true))
            ->count();

        return [
            'high_acuity' => $in('nursing_level', ['level_3', 'level_4']),
            'high_fall' => $in('fall_risk', ['high', 'alert_active']),
            'isolation' => $patients
                ->filter(fn (Patient $patient) => $patient->isolation_type && strtolower($patient->isolation_type) !== 'none')
                ->count(),
        ];
    }

    /**
     * What management should look at first: capacity running out, patients
     * deteriorating, alerts left waiting, shifts below minimum staffing and
     * care falling behind. Critical, then serious, then warning; within each
     * by TOPICS, then the bigger number first.
     *
     * @return Collection<int, array{key: string, severity: string, topic: string, title: string, detail: string, ward_id: ?int, url: ?string, weight: int}>
     */
    private static function attention(Collection $rows, array $capacity): Collection
    {
        $items = collect();
        $add = function (string $key, string $severity, string $topic, string $title, string $detail, ?array $ward = null, int $weight = 0) use ($items) {
            $items->push([
                'key' => $key,
                'severity' => $severity,
                'topic' => $topic,
                'title' => $title,
                'detail' => $detail,
                'ward_id' => $ward['id'] ?? null,
                'url' => $ward['url'] ?? null,
                'weight' => $weight,
            ]);
        };
        $patients = fn (int $count) => $count . ' ' . Str::plural('patient', $count);

        $occupancy = $capacity['occupancy'];
        if ($occupancy !== null && $occupancy >= self::OCCUPANCY_CRITICAL) {
            $add('hospital:occupancy', 'critical', 'capacity', 'Hospital occupancy at ' . self::number($occupancy) . '%',
                $capacity['free'] . ' free ' . Str::plural('bed', $capacity['free']) . ' across ' . $capacity['wards'] . ' ' . Str::plural('ward', $capacity['wards']), null, 1000);
        } elseif ($occupancy !== null && $occupancy > self::OCCUPANCY_TARGET) {
            $add('hospital:occupancy', 'warning', 'capacity', 'Hospital occupancy above target',
                self::number($occupancy) . '% against the ' . self::OCCUPANCY_TARGET . '% target', null, 1000);
        }

        if ($capacity['incoming'] > $capacity['free'] + $capacity['pending_discharge']) {
            $add('hospital:bed-gap', 'serious', 'capacity', 'More prebooked patients than beds coming free',
                $capacity['incoming'] . ' prebooked · ' . $capacity['free'] . ' free · ' . $capacity['pending_discharge'] . ' pending discharge', null, 1000);
        }

        foreach ($rows as $ward) {
            $name = $ward['name'];

            if ($ward['beds'] > 0 && $ward['free'] === 0) {
                $detail = $ward['occupied'] . ' of ' . $ward['beds'] . ' occupied'
                    . ($ward['reserved'] ? ' · ' . $ward['reserved'] . ' reserved' : '')
                    . ($ward['maintenance'] ? ' · ' . $ward['maintenance'] . ' in maintenance' : '')
                    . ($ward['incoming'] ? ' · ' . $ward['incoming'] . ' prebooked' : '');
                $add("ward:{$ward['id']}:full", $ward['incoming'] ? 'critical' : 'serious', 'capacity', $name . ' has no free beds', $detail, $ward, $ward['incoming']);
            } elseif ($ward['occupancy'] !== null && $ward['occupancy'] >= self::OCCUPANCY_CRITICAL) {
                $add("ward:{$ward['id']}:occupancy", 'warning', 'capacity', $name . ' at ' . self::number($ward['occupancy']) . '% occupancy',
                    $ward['free'] . ' free ' . Str::plural('bed', $ward['free']), $ward, (int) $ward['occupancy']);
            }

            if ($ward['alerts_waiting']) {
                $add("ward:{$ward['id']}:alerts", $ward['alerts_waiting_urgent'] ? 'critical' : 'warning', 'alerts',
                    $name . ': ' . $ward['alerts_waiting'] . ' ' . Str::plural('alert', $ward['alerts_waiting']) . ' waiting over ' . self::ALERT_WAIT_MINUTES . ' min',
                    'Oldest ' . self::age($ward['alerts_oldest']) . ($ward['alerts_waiting_urgent'] ? ' · ' . $ward['alerts_waiting_urgent'] . ' urgent' : ''),
                    $ward, $ward['alerts_waiting']);
            }

            if ($ward['ews_urgent']) {
                $add("ward:{$ward['id']}:ews", 'serious', 'ews', $name . ': ' . $patients($ward['ews_urgent']) . ' at EWS 5 or more',
                    $ward['ews_warning'] ? $ward['ews_warning'] . ' more at EWS 3–4' : 'From vital signs in the last ' . CommandCenterLive::EWS_CURRENT_HOURS . ' h',
                    $ward, $ward['ews_urgent']);
            }

            if ($ward['escalations']) {
                $add("ward:{$ward['id']}:escalation", 'serious', 'escalation', $name . ': ' . $patients($ward['escalations']) . ' at a monitor escalation level',
                    'Hemodynamic or ventilator readings, last ' . ClinicalIndicatorReadings::RECENT_HOURS . ' h', $ward, $ward['escalations']);
            }

            $staffing = $ward['staffing'];
            if ($staffing && $staffing['status'] === 'serious') {
                $add("ward:{$ward['id']}:staffing", 'serious', 'staffing',
                    $name . ': ' . $staffing['on_duty'] . ' of ' . $staffing['minimum'] . ' nurses on the ' . $staffing['shift'] . ' shift',
                    $patients($staffing['patients']) . ($staffing['ratio'] !== null ? ' · ' . self::number($staffing['ratio']) . ' per nurse' : ' · no nurse rostered'),
                    $ward, $staffing['minimum'] - $staffing['on_duty']);
            }

            if ($ward['overdue_care']) {
                $add("ward:{$ward['id']}:overdue", 'warning', 'overdue', $name . ': ' . $patients($ward['overdue_care']) . ' with care overdue',
                    'Monitored assessments or medication doses', $ward, $ward['overdue_care']);
            }

            $unobserved = $ward['obs_due'] - $ward['obs_done'];
            if ($unobserved > 0) {
                $add("ward:{$ward['id']}:observations", 'warning', 'observations',
                    $name . ': ' . $patients($unobserved) . ' without vital signs in ' . CommandCenterLive::EWS_CURRENT_HOURS . ' h',
                    self::number($ward['obs_pct']) . '% observed', $ward, $unobserved);
            }
        }

        $severity = array_flip(self::SEVERITIES);
        $topic = array_flip(self::TOPICS);

        return $items
            ->sort(fn (array $a, array $b) => [$severity[$a['severity']], $topic[$a['topic']], -$a['weight'], $a['title']]
                <=> [$severity[$b['severity']], $topic[$b['topic']], -$b['weight'], $b['title']])
            ->values();
    }

    private static function occupancyStatus(?float $occupancy): ?string
    {
        return match (true) {
            $occupancy === null => null,
            $occupancy >= self::OCCUPANCY_CRITICAL => 'critical',
            $occupancy > self::OCCUPANCY_TARGET => 'warning',
            default => 'good',
        };
    }

    /** The worst of some severities, or good when there are none. */
    private static function worst(Collection $severities): string
    {
        return collect(self::SEVERITIES)->first(fn (string $severity) => $severities->contains($severity)) ?? 'good';
    }

    private static function percent(int|float $count, int|float $of): ?float
    {
        return $of > 0 ? round($count / $of * 100, 1) : null;
    }

    private static function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }
        $sorted = $values->sort()->values();
        $middle = intdiv($sorted->count(), 2);

        return round($sorted->count() % 2 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2, 1);
    }

    /** 87, 87.5: no trailing .0 */
    private static function number(int|float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    /** 12 min, 2 h 5 min, 3 d 4 h */
    private static function age(int $minutes): string
    {
        return match (true) {
            $minutes < 60 => $minutes . ' min',
            $minutes < 1440 => intdiv($minutes, 60) . ' h' . ($minutes % 60 ? ' ' . ($minutes % 60) . ' min' : ''),
            default => intdiv($minutes, 1440) . ' d' . (intdiv($minutes % 1440, 60) ? ' ' . intdiv($minutes % 1440, 60) . ' h' : ''),
        };
    }
}
