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
use App\Support\DemoDay;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Command Center V2: every ward summed up for hospital management, on the
 * command centre's screen. Aggregate figures only, never a patient's name.
 * Wards of an Emergency ward type are left out: the ED has its own board
 * (CommandCenterEd).
 *
 * Four areas, hospital-wide and per ward:
 *  - capacity: beds by status, and occupancy against OCCUPANCY_TARGET, for
 *    the hospital and for each of OCCUPANCY_GROUPS;
 *  - patient flow: today's admissions and discharges from the admission log,
 *    the share discharged by noon and the average length of stay, which
 *    lengthOfStay() breaks down by day and by ward;
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

    /**
     * The bed occupancy card's sections. A ward is in Labour room & nursery when
     * its ward type is marked so (Ward Types), and in General ward & critical
     * otherwise, a ward without a type included.
     */
    public const OCCUPANCY_GROUPS = [
        'general' => 'General ward & critical',
        'labour_nursery' => 'Labour room & nursery',
    ];

    /** The daily trends run this many days, today included. */
    public const TREND_DAYS = 14;

    /** Average length of stay covers the patients discharged in this many days, against the same span before. */
    public const LOS_DAYS = 30;

    /** An inpatient in hospital this many days or more is a long stay. */
    public const LONG_STAY_DAYS = 7;

    /**
     * The hospital's stays by length, in whole days stayed: label => [fewest, most],
     * most null for no limit. The bands from LONG_STAY_DAYS on are its long stays.
     */
    public const STAY_BANDS = [
        'Under a day' => [0, 0],
        '1–2 days' => [1, 2],
        '3–6 days' => [3, 6],
        '7–13 days' => [7, 13],
        '14 days or more' => [14, null],
    ];

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

    /**
     * The demo's bed occupancy (%) for each of OCCUPANCY_GROUPS, a point either
     * way from day to day, and how far (in points) a ward may be from it.
     */
    public const DEMO_OCCUPANCY = ['general' => 98, 'labour_nursery' => 70];
    private const DEMO_OCCUPANCY_SPREAD = ['general' => 6, 'labour_nursery' => 30];

    /** The demo's admissions by hour of the day, and its discharges, mostly late morning to afternoon. */
    private const DEMO_ADMIT_HOURS = [1, 0.8, 0.6, 0.5, 0.5, 0.6, 1, 1.5, 2.5, 3.5, 4, 4.5, 4.5, 4.5, 4.5, 4.5, 4.5, 4.5, 4, 3.5, 3, 2.5, 2, 1.5];
    private const DEMO_DISCHARGE_HOURS = [0.2, 0.1, 0.1, 0.1, 0.1, 0.1, 0.2, 0.5, 1.5, 3, 4.5, 5.5, 6, 6, 5.5, 5, 4, 3, 2, 1.2, 0.8, 0.5, 0.3, 0.2];

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
     * @param  bool  $demo  made-up figures for showing the board (demo()) instead of the recorded ones
     */
    public static function build(string $ewsSystem = 'ews_ihh', bool $demo = false): array
    {
        $now = now();
        // The ED's zones (Emergency ward types) have their own board, Command Center V2 (ED)
        $wards = Ward::where('is_active', true)->notEmergency()->orderBy('ward_name')->get();

        return $demo ? self::demo($wards, $now) : self::live($wards, $ewsSystem, $now);
    }

    /** The board from the wards' records. */
    private static function live(EloquentCollection $wards, string $ewsSystem, CarbonInterface $now): array
    {
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
        [$capacity, $attention, $rows] = self::overview($rows);

        return self::snapshot(
            now: $now,
            demo: false,
            capacity: $capacity,
            flow: $flow['summary'],
            quality: self::quality($rows, $live, $alerts, $attention),
            rows: $rows,
            acuity: self::acuity(self::riskCounts($inpatients) + ['ventilated' => $rows->sum('ventilated'), 'transfusing' => $rows->sum('transfusing')], $inpatients->count()),
            attention: $attention,
            trend: $flow['trend'],
            los: self::lengthOfStay($flow['stays'], $inpatients, $rows, $flow['trend']['dates'], $now),
        );
    }

    /**
     * From the wards' lines: the beds, the list management should look at
     * first, and each ward's status, the worst on that list for it.
     *
     * @return array{0: array, 1: Collection, 2: Collection}
     */
    private static function overview(Collection $rows): array
    {
        $capacity = self::capacity($rows);
        $attention = self::attention($rows, $capacity);
        $rows = $rows->map(fn (array $row) => $row + [
            'status' => self::worst($attention->where('ward_id', $row['id'])->pluck('severity')),
        ]);

        return [$capacity, $attention, $rows];
    }

    /** What the screen is sent, live or demo alike. */
    private static function snapshot(CarbonInterface $now, bool $demo, array $capacity, array $flow, array $quality, Collection $rows,
        array $acuity, Collection $attention, array $trend, array $los): array
    {
        return [
            'generated_at' => $now->toIso8601String(),
            'generated_time' => $now->format('H:i:s'),
            'refresh_seconds' => self::REFRESH_SECONDS,
            'demo' => $demo,
            'targets' => [
                'occupancy' => self::OCCUPANCY_TARGET,
                'occupancy_critical' => self::OCCUPANCY_CRITICAL,
                'alert_wait_minutes' => self::ALERT_WAIT_MINUTES,
                'noon_hour' => self::NOON_HOUR,
                'los_days' => self::LOS_DAYS,
                'long_stay_days' => self::LONG_STAY_DAYS,
                'obs_hours' => CommandCenterLive::EWS_CURRENT_HOURS,
            ],
            'capacity' => $capacity,
            'flow' => $flow,
            'quality' => $quality,
            'workforce' => self::workforce($rows),
            'acuity' => $acuity,
            'wards' => $rows->values()->all(),
            'attention' => $attention->take(self::ATTENTION_LIMIT)->map(fn (array $item) => array_diff_key($item, ['weight' => 0]))->values()->all(),
            'attention_total' => $attention->count(),
            'trend' => $trend,
            'los' => $los,
        ];
    }

    /** Which of OCCUPANCY_GROUPS a ward's beds count toward. */
    private static function occupancyGroup(Ward $ward): string
    {
        return $ward->wardType?->is_labour_nursery ? 'labour_nursery' : 'general';
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
            'occupancy_group' => self::occupancyGroup($ward),
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
            // The card's two sections: the wards of a Labour Room & Nursery type, and all the others
            'groups' => collect(self::OCCUPANCY_GROUPS)->map(function (string $label, string $key) use ($rows) {
                $group = $rows->where('occupancy_group', $key);
                $occupancy = self::percent($group->sum('occupied'), $group->sum('beds'));

                return [
                    'key' => $key,
                    'label' => $label,
                    'wards' => $group->count(),
                    'beds' => $group->sum('beds'),
                    'occupied' => $group->sum('occupied'),
                    'free' => $group->sum('free'),
                    'occupancy' => $occupancy,
                    'status' => self::occupancyStatus($occupancy),
                ];
            })->values()->all(),
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
        // Every stay read, so the length of stay's day-by-day trend has its days too
        $stays = self::stayLengths($discharges);
        $recentStays = $stays->filter(fn (array $stay) => $stay['at'] > $losSplit)->pluck('days');
        $earlierStays = $stays->filter(fn (array $stay) => $stay['at'] >= $losStart->getTimestamp() && $stay['at'] <= $losSplit)->pluck('days');

        $admitsPerDay = $admits->countBy(fn (array $log) => date('Y-m-d', $log['at']));
        $dischargesPerDay = $discharges->countBy(fn (array $log) => date('Y-m-d', $log['at']));
        $trend = self::trend(
            $now,
            fn (string $date) => $admitsPerDay[$date] ?? 0,
            fn (string $date) => $dischargesPerDay[$date] ?? 0,
            $inpatients,
        );

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
                'alos' => self::average($recentStays),
                'alos_previous' => self::average($earlierStays),
                'alos_stays' => $recentStays->count(),
            ],
            'trend' => $trend,
            'stays' => $stays,
        ];
    }

    /**
     * The daily trend: TREND_DAYS to today, each day's admissions and
     * discharges, and the inpatients at each midnight worked back from the
     * inpatients now (see flow()).
     *
     * @param  \Closure(string): int  $admissions  a day's admissions, by its date
     * @param  \Closure(string): int  $discharges  a day's discharges, by its date
     */
    private static function trend(CarbonInterface $now, \Closure $admissions, \Closure $discharges, int $inpatients): array
    {
        $start = $now->copy()->startOfDay()->subDays(self::TREND_DAYS - 1);
        $trend = ['dates' => [], 'labels' => [], 'days' => [], 'census' => [], 'admissions' => [], 'discharges' => []];
        for ($offset = 0; $offset < self::TREND_DAYS; $offset++) {
            $day = $start->copy()->addDays($offset);
            $trend['dates'][] = $day->toDateString();
            $trend['labels'][] = $day->format('j M');
            $trend['days'][] = $day->format('D j M');
            $trend['admissions'][] = $admissions($day->toDateString());
            $trend['discharges'][] = $discharges($day->toDateString());
        }
        $census = [self::TREND_DAYS - 1 => $inpatients];
        for ($i = self::TREND_DAYS - 2; $i >= 0; $i--) {
            $census[$i] = $census[$i + 1] - $trend['admissions'][$i + 1] + $trend['discharges'][$i + 1];
        }
        ksort($census);
        $trend['census'] = array_map(fn (int $count) => max(0, $count), array_values($census));

        return $trend;
    }

    /**
     * Length of stay, for the hospital as a whole and then ward by ward: how
     * long the patients discharged in the last LOS_DAYS stayed, against the
     * LOS_DAYS before; the same day by day across the trend; and how long
     * today's inpatients have been in so far, with how many are long stays;
     * and the hospital's stays by length, in STAY_BANDS. A stay runs from the admission to the hospital, so a transfer does not
     * restart it, and counts toward the ward that discharged the patient.
     * The wards come longest average stay first, those with no discharges last.
     *
     * @param  Collection<int, array{ward_id: int, at: int, days: float}>  $stays
     * @param  list<string>  $dates  the trend's days
     */
    private static function lengthOfStay(Collection $stays, EloquentCollection $inpatients, Collection $rows, array $dates, CarbonInterface $now): array
    {
        $split = $now->copy()->subDays(self::LOS_DAYS)->getTimestamp();
        $from = $now->copy()->subDays(self::LOS_DAYS * 2)->getTimestamp();
        $recent = $stays->filter(fn (array $stay) => $stay['at'] > $split);
        $earlier = $stays->filter(fn (array $stay) => $stay['at'] >= $from && $stay['at'] <= $split);
        // Today's inpatients, and the days each has been in (none without a sound admission time)
        $inFor = $inpatients->map(fn (Patient $patient) => [
            'ward_id' => (int) $patient->ward_id,
            'days' => $patient->admitted_at && $patient->admitted_at->lte($now) ? ($now->getTimestamp() - $patient->admitted_at->getTimestamp()) / 86400 : null,
        ])->toBase();

        $figures = function (Collection $recent, Collection $earlier, Collection $inFor) {
            $inDays = $inFor->pluck('days')->filter(fn ($days) => $days !== null);

            return [
                'alos' => self::average($recent->pluck('days')),
                'alos_previous' => self::average($earlier->pluck('days')),
                'median' => self::median($recent->pluck('days')),
                'stays' => $recent->count(),
                'inpatients' => $inFor->count(),
                'stay_so_far' => self::average($inDays),
                'long_stays' => $inDays->filter(fn ($days) => $days >= self::LONG_STAY_DAYS)->count(),
            ];
        };

        $perDay = $stays->groupBy(fn (array $stay) => date('Y-m-d', $stay['at']));
        $wholeDays = $recent->map(fn (array $stay) => (int) floor($stay['days']));
        $bands = collect(self::STAY_BANDS)->map(function (array $range, string $label) use ($wholeDays) {
            [$fewest, $most] = $range;
            $count = $wholeDays->filter(fn (int $days) => $days >= $fewest && ($most === null || $days <= $most))->count();

            return ['label' => $label, 'count' => $count, 'pct' => self::percent($count, $wholeDays->count()), 'long' => $fewest >= self::LONG_STAY_DAYS];
        });
        $recentByWard = $recent->groupBy('ward_id');
        $earlierByWard = $earlier->groupBy('ward_id');
        $inByWard = $inFor->groupBy('ward_id');

        $wards = $rows->map(fn (array $row) => [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'critical_care' => $row['critical_care'],
            'url' => $row['url'],
        ] + $figures($recentByWard->get($row['id'], collect()), $earlierByWard->get($row['id'], collect()), $inByWard->get($row['id'], collect())));

        return [
            'hospital' => $figures($recent, $earlier, $inFor),
            'bands' => $bands->values()->all(),
            'trend' => [
                'alos' => array_map(fn (string $date) => isset($perDay[$date]) ? self::average($perDay[$date]->pluck('days')) : null, $dates),
                'stays' => array_map(fn (string $date) => isset($perDay[$date]) ? $perDay[$date]->count() : 0, $dates),
            ],
            'wards' => $wards
                ->sort(fn (array $a, array $b) => [$a['alos'] === null, -($a['alos'] ?? 0), $a['name']] <=> [$b['alos'] === null, -($b['alos'] ?? 0), $b['name']])
                ->values()
                ->all(),
        ];
    }

    /**
     * How long each discharged patient stayed: from their latest admission
     * before the discharge, or failing a logged one the admission time on
     * their record, while that is still the stay's.
     *
     * @param  Collection<int, array{patient_id: ?int, ward_id: int, at: int}>  $discharges
     * @return Collection<int, array{ward_id: int, at: int, days: float}>
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

            return $start === null ? null : ['ward_id' => $discharge['ward_id'], 'at' => $discharge['at'], 'days' => ($discharge['at'] - $start) / 86400];
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

    /**
     * Share of inpatients carrying each kind of acuity or risk; each ward's line has its own counts.
     *
     * @param  array<string, int>  $counts  the inpatients with each of ACUITY
     */
    private static function acuity(array $counts, int $inpatients): array
    {
        return collect(self::ACUITY)->map(fn (array $labels, string $key) => [
            'key' => $key,
            'label' => $labels[0],
            'short' => $labels[1],
            'count' => $counts[$key],
            'pct' => self::percent($counts[$key], $inpatients),
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

    // -- demo ------------------------------------------------------------------

    /**
     * The board from made-up figures, for showing it (the screen's data mode in
     * Settings). The hospital's own wards and beds, with patients, flow,
     * clinical flags and nurses that follow from the date: nothing is saved,
     * and every screen shows the same demo all day. The beds are as full as
     * DEMO_OCCUPANCY all day; admissions and discharges add up as the day goes
     * on; the clinical flags and alerts change from hour to hour.
     */
    private static function demo(EloquentCollection $wards, CarbonInterface $now): array
    {
        $wards->load(['wardType', 'beds' => fn ($query) => $query->where('is_active', true)]);
        $today = $now->copy()->startOfDay();
        $day = DemoDay::random('ccv2-demo', $today);
        $hour = DemoDay::random('ccv2-demo-hour-' . $now->format('G'), $today);

        $occupied = self::demoOccupied($wards, $today);
        $rows = $wards->map(fn (Ward $ward) => self::demoWardRow($ward, $occupied[$ward->id], $now))->toBase();
        [$capacity, $attention, $rows] = self::overview($rows);

        // Today's admissions and discharges so far, and each day's before
        $perDay = fn (string $kind, string $todays) => fn (string $date) => $date === $today->toDateString()
            ? $rows->sum($todays)
            : $wards->sum(fn (Ward $ward) => self::demoCount($ward, Carbon::parse($date), $kind));
        $trend = self::trend($now, $perDay('admit', 'admissions_today'), $perDay('discharge', 'discharges_today'), $rows->sum('inpatients'));
        $los = self::demoLengthOfStay($wards, $rows, $trend, $now);

        $soFar = fn (string $kind, CarbonInterface $on, CarbonInterface $by, ?int $beforeHour = null) => $wards->sum(fn (Ward $ward) => self::demoTimes($ward, $on, $kind)
            ->filter(fn (CarbonInterface $at) => $at->lte($by) && ($beforeHour === null || $at->hour < $beforeHour))
            ->count());
        $discharged = $rows->sum('discharges_today');
        $byNoon = $soFar('discharge', $today, $now, self::NOON_HOUR);

        // The ward dashboards' own concerns: a patient can have more than one
        $fluid = $rows->sum(fn (array $row) => (int) floor($hour("fluid.{$row['id']}") ** 2 * (1 + $row['inpatients'] / 25)));
        $concerned = $rows->sum(fn (array $row) => min($row['inpatients'],
            $row['ews_urgent'] + $row['ews_warning'] + max(0, $row['escalations'] - $row['ews_urgent']) + $row['overdue_care']));
        $answered = (int) round(($now->getTimestamp() - $today->getTimestamp()) / 3600 * (2 + 1.5 * $day('answered')));
        $alerts = [
            'wards' => [],
            'pending' => $rows->sum('alerts'),
            'urgent' => $rows->sum('alerts_urgent'),
            'waiting' => $rows->sum('alerts_waiting'),
            'waiting_urgent' => $rows->sum('alerts_waiting_urgent'),
            'oldest' => $rows->max('alerts_oldest'),
            'answered_today' => $answered,
            'median_today' => $answered ? round(3 + 5 * $hour('alerts-median'), 1) : null,
            'median_week' => round(4 + 3 * $day('alerts-median-week'), 1),
        ];
        $live = [
            'attention_total' => min($rows->sum('inpatients'), $concerned + $fluid),
            'stats' => [
                'assessments_overdue' => $rows->sum(fn (array $row) => (int) ceil($row['overdue_care'] * 0.7)),
                'doses_overdue' => $rows->sum(fn (array $row) => (int) ceil($row['overdue_care'] * 0.5)),
                'fluid_alerts' => $fluid,
            ],
        ];

        return self::snapshot(
            now: $now,
            demo: true,
            capacity: $capacity,
            flow: [
                'admissions_today' => $rows->sum('admissions_today'),
                'admissions_same_time_yesterday' => $soFar('admit', $today->copy()->subDay(), $now->copy()->subDay()),
                'discharges_today' => $discharged,
                'discharges_same_time_yesterday' => $soFar('discharge', $today->copy()->subDay(), $now->copy()->subDay()),
                'discharged_by_noon' => $byNoon,
                'discharged_by_noon_pct' => self::percent($byNoon, $discharged),
                'discharged_by_noon_pct_period' => round(30 + 8 * $day('by-noon'), 1),
                'discharges_period' => $los['hospital']['stays'],
                'alos' => $los['hospital']['alos'],
                'alos_previous' => $los['hospital']['alos_previous'],
                'alos_stays' => $los['hospital']['stays'],
            ],
            quality: self::quality($rows, $live, $alerts, $attention),
            rows: $rows,
            acuity: self::acuity(collect(array_keys(self::ACUITY))->mapWithKeys(fn (string $key) => [$key => $rows->sum($key)])->all(), $rows->sum('inpatients')),
            attention: $attention,
            trend: $trend,
            los: $los,
        );
    }

    /**
     * The demo's occupied beds, ward id => beds: each section about its
     * DEMO_OCCUPANCY, each ward near its section's figure, then evened out to
     * it a bed at a time (one more where most are free, one fewer where fewest are).
     *
     * @return array<int, int>
     */
    private static function demoOccupied(EloquentCollection $wards, CarbonInterface $today): array
    {
        $random = DemoDay::random('ccv2-demo', $today);
        $occupied = $wards->mapWithKeys(fn (Ward $ward) => [$ward->id => 0])->all();

        foreach (array_keys(self::OCCUPANCY_GROUPS) as $group) {
            $beds = $wards->filter(fn (Ward $ward) => self::occupancyGroup($ward) === $group && $ward->beds->isNotEmpty())
                ->mapWithKeys(fn (Ward $ward) => [$ward->id => $ward->beds->count()])
                ->all();
            if ($beds === []) {
                continue;
            }
            $share = self::DEMO_OCCUPANCY[$group] / 100;
            $spread = self::DEMO_OCCUPANCY_SPREAD[$group] / 100;
            $target = (int) round(array_sum($beds) * min(1, $share + (2 * $random("occupancy.{$group}") - 1) / 100));

            $taken = [];
            foreach ($beds as $id => $count) {
                $taken[$id] = max(0, min($count, (int) round($count * ($share + $spread * ($random("ward.{$id}") - 0.5)))));
            }
            while (array_sum($taken) < $target) {
                $id = collect($taken)->filter(fn (int $n, int $id) => $n < $beds[$id])
                    ->sortByDesc(fn (int $n, int $id) => ($beds[$id] - $n) / $beds[$id])->keys()->first();
                $taken[$id]++;
            }
            while (array_sum($taken) > $target) {
                $id = collect($taken)->filter(fn (int $n) => $n > 0)
                    ->sortByDesc(fn (int $n, int $id) => $n / $beds[$id])->keys()->first();
                $taken[$id]--;
            }
            $occupied = array_replace($occupied, $taken);
        }

        return $occupied;
    }

    /** One ward's line from made-up figures, as wardRow() has it. */
    private static function demoWardRow(Ward $ward, int $occupied, CarbonInterface $now): array
    {
        $id = $ward->id;
        $today = $now->copy()->startOfDay();
        $day = DemoDay::random('ccv2-demo', $today);
        $hour = DemoDay::random('ccv2-demo-hour-' . $now->format('G'), $today);
        $beds = $ward->beds->count();
        $criticalCare = (bool) $ward->wardType?->is_critical_care;
        $group = self::occupancyGroup($ward);
        // Clinical flags are commonest in critical care, rarest in the labour room and nursery
        $acuity = $criticalCare ? 1.6 : ($group === 'labour_nursery' ? 0.4 : 1);
        // Up to about one per $per patients this hour, fewer the higher $power; and a day's share of the patients
        $some = fn (string $what, float $per, float $power = 1) => min($occupied, (int) floor($hour("{$what}.{$id}") ** $power * (1 + $occupied / $per)));
        $share = fn (string $what, float $least, float $most) => (int) round($occupied * ($least + ($most - $least) * $day("{$what}.{$id}")));

        // The beds no one is in: most free, a few reserved or in maintenance
        $reserved = $maintenance = 0;
        for ($bed = 1; $bed <= $beds - $occupied; $bed++) {
            $pick = $day("bed.{$id}.{$bed}");
            if ($pick < 0.1) {
                $reserved++;
            } elseif ($pick < 0.14) {
                $maintenance++;
            }
        }

        $admitted = self::demoTimes($ward, $today, 'admit')->filter(fn (CarbonInterface $at) => $at->lte($now));
        $discharged = self::demoTimes($ward, $today, 'discharge')->filter(fn (CarbonInterface $at) => $at->lte($now));
        // Those admitted within OBS_GRACE_MINUTES are not due their vital signs yet
        $settling = $admitted->filter(fn (CarbonInterface $at) => $at->gt($now->copy()->subMinutes(self::OBS_GRACE_MINUTES)))->count();
        $obsDue = max(0, $occupied - $settling);
        $obsDone = max(0, $obsDue - $some('unobserved', 10, 2));
        $ewsUrgent = $some('ews-urgent', 14 / $acuity, 1.5);

        $pending = $some('alerts', 16 / $acuity, 1.3);
        $alerts = collect($pending > 0 ? range(1, $pending) : [])->map(fn (int $i) => [
            'urgent' => $hour("alert.{$id}.{$i}.urgent") < 0.2,
            'minutes' => (int) round(40 * $hour("alert.{$id}.{$i}.age") ** 2.2),
        ]);
        $late = $alerts->where('minutes', '>', self::ALERT_WAIT_MINUTES);
        $occupancy = self::percent($occupied, $beds);

        return [
            'id' => $id,
            'name' => $ward->ward_name,
            'code' => $ward->ward_code,
            'type' => $ward->wardType?->name,
            'critical_care' => $criticalCare,
            'occupancy_group' => $group,
            'url' => $criticalCare ? route('critical-care.dashboard', ['ward_id' => $id]) : route('ward.dashboard', ['ward_id' => $id]),
            'beds' => $beds,
            'occupied' => $occupied,
            'free' => $beds - $occupied - $reserved - $maintenance,
            'reserved' => $reserved,
            'maintenance' => $maintenance,
            'occupancy' => $occupancy,
            'occupancy_status' => self::occupancyStatus($occupancy),
            'inpatients' => $occupied,
            'incoming' => (int) floor($day("incoming.{$id}") * (1 + $beds / 15)),
            'pending_discharge' => $some('pending-discharge', 10),
            'admissions_today' => $admitted->count(),
            'discharges_today' => $discharged->count(),
            'ews_urgent' => $ewsUrgent,
            'ews_warning' => min($occupied - $ewsUrgent, $some('ews-warning', 7 / $acuity)),
            'escalations' => $criticalCare ? $some('escalation', 6) : 0,
            'ventilated' => $criticalCare ? $share('ventilated', 0.35, 0.6) : 0,
            'transfusing' => $some('transfusing', 20, 2),
            'high_acuity' => $criticalCare ? $share('high-acuity', 0.6, 0.9) : $share('high-acuity', 0.03, 0.15),
            'high_fall' => $group === 'labour_nursery' ? $share('high-fall', 0, 0.05) : $share('high-fall', 0.1, 0.25),
            'isolation' => $share('isolation', 0.02, 0.08),
            'overdue_care' => $some('overdue', 12 / $acuity, 1.5),
            'obs_due' => $obsDue,
            'obs_done' => $obsDone,
            'obs_pct' => self::percent($obsDone, $obsDue),
            'alerts' => $alerts->count(),
            'alerts_urgent' => $alerts->where('urgent', true)->count(),
            'alerts_waiting' => $late->count(),
            'alerts_waiting_urgent' => $late->where('urgent', true)->count(),
            'alerts_oldest' => $alerts->max('minutes'),
            'staffing' => self::demoStaffing($ward, $occupied, $now),
        ];
    }

    /**
     * The demo's nurses on the ward's current shift: about its minimum
     * staffing (the AI Nurse Schedule's roster rules), now and then one short.
     */
    private static function demoStaffing(Ward $ward, int $patients, CarbonInterface $now): ?array
    {
        $slot = RosterSlot::current($ward->id, $now);
        if ($slot === null) {
            return null;
        }
        $minimum = RosterRules::forWard($ward)->minStaff[$slot['code']] ?? 0;
        $pick = DemoDay::random('ccv2-demo', $now->copy()->startOfDay())("staff.{$ward->id}.{$slot['code']}");
        $onDuty = max(min(1, $patients), $minimum + match (true) {
            $pick < 0.1 => -1,
            $pick < 0.55 => 0,
            $pick < 0.9 => 1,
            default => 2,
        });

        return [
            'shift' => $slot['code'],
            'shift_name' => $slot['name'],
            'shift_time' => $slot['time'],
            'on_duty' => $onDuty,
            'minimum' => $minimum,
            'patients' => $patients,
            'ratio' => $onDuty > 0 ? round($patients / $onDuty, 1) : null,
            'status' => $onDuty < $minimum ? 'serious' : 'good',
        ];
    }

    /** A ward's average stay in the demo, in days: by its kind, and its own a little either way, the same every day. */
    private static function demoAlos(Ward $ward): float
    {
        $kind = match (true) {
            (bool) $ward->wardType?->is_critical_care => 5.5,
            self::occupancyGroup($ward) === 'labour_nursery' => 2.4,
            default => 3.6,
        };

        return $kind * (0.8 + 0.4 * DemoDay::random('ccv2-demo')("alos.{$ward->id}"));
    }

    /**
     * How many patients a ward admits on a day in the demo (kind "admit"), or
     * discharges (kind "discharge"): as many as it admitted the day before, so
     * its beds stay about as full and the midnight census stays near the beds.
     */
    private static function demoCount(Ward $ward, CarbonInterface $day, string $kind): int
    {
        $day = $kind === 'discharge' ? $day->copy()->subDay() : $day;
        $random = DemoDay::random('ccv2-demo', $day);
        $perDay = $ward->beds->count() * self::DEMO_OCCUPANCY[self::occupancyGroup($ward)] / 100 / self::demoAlos($ward);

        return (int) round($perDay * (0.95 + 0.1 * $random('busy')) * (0.85 + 0.3 * $random("admit.{$ward->id}")));
    }

    /**
     * The times of day of a ward's demo admissions or discharges on a day.
     *
     * @return Collection<int, CarbonInterface>
     */
    private static function demoTimes(Ward $ward, CarbonInterface $day, string $kind): Collection
    {
        $count = self::demoCount($ward, $day, $kind);
        $random = DemoDay::random('ccv2-demo', $day);
        $hours = $kind === 'admit' ? self::DEMO_ADMIT_HOURS : self::DEMO_DISCHARGE_HOURS;

        return collect($count > 0 ? range(1, $count) : [])
            ->map(fn (int $i) => DemoDay::moment($day, $random, $hours, "{$kind}.{$ward->id}.{$i}"));
    }

    /**
     * Length of stay in the demo, as lengthOfStay() has it: each ward's
     * stays about its demoAlos(), as many as it discharged in the last LOS_DAYS,
     * and the hospital's from the wards', weighted by their stays (and by
     * their inpatients for the stays so far). The stays by length fall away
     * as they get longer, as stays do.
     */
    private static function demoLengthOfStay(EloquentCollection $wards, Collection $rows, array $trend, CarbonInterface $now): array
    {
        $today = $now->copy()->startOfDay();
        $day = DemoDay::random('ccv2-demo', $today);
        $rowsById = $rows->keyBy('id');

        $wardFigures = $wards->map(function (Ward $ward) use ($rowsById, $day, $today) {
            $row = $rowsById[$ward->id];
            $alos = self::demoAlos($ward);
            $stays = $row['discharges_today'];
            for ($back = 1; $back < self::LOS_DAYS; $back++) {
                $stays += self::demoCount($ward, $today->copy()->subDays($back), 'discharge');
            }
            $inpatients = $row['inpatients'];
            $about = fn (string $what, float $least, float $most) => round($alos * ($least + ($most - $least) * $day("{$what}.{$ward->id}")), 1);

            return [
                'id' => $row['id'],
                'name' => $row['name'],
                'code' => $row['code'],
                'critical_care' => $row['critical_care'],
                'url' => $row['url'],
                'alos' => $stays ? $about('los', 0.93, 1.07) : null,
                'alos_previous' => $stays ? $about('los-previous', 0.9, 1.1) : null,
                'median' => $stays ? $about('los-median', 0.7, 0.8) : null,
                'stays' => $stays,
                'inpatients' => $inpatients,
                'stay_so_far' => $inpatients ? $about('so-far', 0.85, 1.15) : null,
                'long_stays' => (int) round($inpatients * exp(-self::LONG_STAY_DAYS / $alos) * (0.7 + 0.6 * $day("long-stays.{$ward->id}"))),
            ];
        })->toBase();

        $weighted = function (string $key, string $by) use ($wardFigures): ?float {
            $known = $wardFigures->filter(fn (array $ward) => $ward[$key] !== null);
            $weight = $known->sum($by);

            return $weight > 0 ? round($known->sum(fn (array $ward) => $ward[$key] * $ward[$by]) / $weight, 1) : null;
        };
        $hospital = [
            'alos' => $weighted('alos', 'stays'),
            'alos_previous' => $weighted('alos_previous', 'stays'),
            'median' => $weighted('median', 'stays'),
            'stays' => $wardFigures->sum('stays'),
            'inpatients' => $wardFigures->sum('inpatients'),
            'stay_so_far' => $weighted('stay_so_far', 'inpatients'),
            'long_stays' => $wardFigures->sum('long_stays'),
        ];

        // A stay's whole days fall away as they get longer: the share at d days or more is e^(-d / the average stay)
        $mean = $hospital['alos'] ?: 1;
        $counts = array_map(fn (array $range) => (int) round($hospital['stays']
            * (exp(-$range[0] / $mean) - ($range[1] === null ? 0 : exp(-($range[1] + 1) / $mean)))), self::STAY_BANDS);
        // Rounding's odd stay or two goes to the biggest band, so the bands add up to the stays
        $counts[array_search(max($counts), $counts, true)] += $hospital['stays'] - array_sum($counts);
        $bands = collect($counts)->map(fn (int $count, string $label) => [
            'label' => $label,
            'count' => $count,
            'pct' => self::percent($count, $hospital['stays']),
            'long' => self::STAY_BANDS[$label][0] >= self::LONG_STAY_DAYS,
        ]);

        return [
            'hospital' => $hospital,
            'bands' => $bands->values()->all(),
            'trend' => [
                'alos' => array_map(fn (string $date, int $stays) => $stays && $hospital['alos'] !== null
                    ? round($hospital['alos'] * (0.85 + 0.3 * DemoDay::random('ccv2-demo', Carbon::parse($date))('los')), 1)
                    : null, $trend['dates'], $trend['discharges']),
                'stays' => $trend['discharges'],
            ],
            'wards' => $wardFigures
                ->sort(fn (array $a, array $b) => [$a['alos'] === null, -($a['alos'] ?? 0), $a['name']] <=> [$b['alos'] === null, -($b['alos'] ?? 0), $b['name']])
                ->values()
                ->all(),
        ];
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

    private static function average(Collection $values): ?float
    {
        return $values->isEmpty() ? null : round($values->avg(), 1);
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
