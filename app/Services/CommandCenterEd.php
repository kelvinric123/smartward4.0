<?php

namespace App\Services;

use App\Models\AdmissionLog;
use App\Models\AmbulanceCall;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\Ward;
use App\Support\DemoDay;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Command Center V2 (ED): the emergency department summed up on the command
 * centre's screen. It covers the wards of an Emergency ward type, the ED's
 * zones (Red, Yellow, Green, Waiting Area...), which the hospital-wide
 * Command Center V2 leaves out. Aggregate figures only, never a patient's name.
 *
 *  - now: patients in the ED per zone, beds, and how long each has been in
 *    the ED since arriving (their admitted_at: C+'s census Entry Date), against
 *    the hospital's time-in-ED target (hospitals.ed_target_minutes);
 *  - flow today: arrivals (admit entries in an ED zone) and departures
 *    (discharge entries from one), split into admitted to a ward (to_ward_id)
 *    and gone home, and how long those who left had been in the ED; and
 *    proceed for admission, the decisions to admit (proceed_admission_at)
 *    and those still in the ED waiting for a ward bed, or made-up ones
 *    (admission_demo) for showing the board;
 *  - ambulance cases today: calls (ambulance_calls), the cases attended and
 *    how fast the crews reached the patient, or the same card from made-up
 *    calls (ambulance_demo) for showing the board;
 *  - doctors: patients per attending doctor;
 *  - trends: arrivals by hour, today against yesterday, and arrivals and
 *    departures a day for TREND_DAYS days;
 *  - needs attention: patients over the target (the longest first) and zones
 *    that are full.
 */
final class CommandCenterEd
{
    public const REFRESH_SECONDS = 30;

    /** The time-in-ED target until someone sets one, and the choices the settings offer, in minutes. */
    public const DEFAULT_TARGET_MINUTES = 120;
    public const TARGET_CHOICES = [60, 90, 120, 150, 180, 240, 300, 360, 480, 720];

    /** The daily trend runs this many days, today included. */
    public const TREND_DAYS = 14;

    /** How much of the attention list the screen shows; the rest are counted. */
    public const ATTENTION_LIMIT = 12;

    /** Occupancy (%) at which the ED as a whole is busy. A zone is full at 100%. */
    public const OCCUPANCY_BUSY = 85;

    /** Time in ED, grouped for the chart: [label, from minute, up to minute]. */
    public const BUCKETS = [
        ['Under 1 h', 0, 60],
        ['1–2 h', 60, 120],
        ['2–4 h', 120, 240],
        ['4–6 h', 240, 360],
        ['6 h or more', 360, null],
    ];

    public const SEVERITIES = ['critical', 'serious', 'warning', 'good'];

    private const IN_ED = [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE];

    public static function targetMinutes(?Hospital $hospital): int
    {
        return (int) ($hospital?->ed_target_minutes ?: self::DEFAULT_TARGET_MINUTES);
    }

    public static function build(?Hospital $hospital): array
    {
        $now = now();
        $target = self::targetMinutes($hospital);
        $midnight = $now->copy()->startOfDay();

        $zones = Ward::where('is_active', true)
            ->emergency()
            ->with(['beds' => fn ($beds) => $beds->where('is_active', true)])
            ->orderBy('ward_name')
            ->get();
        $zoneIds = $zones->modelKeys();

        // Until its migration has run there is no proceed-for-admission time to read, rather than the board failing
        $admissionsRecorded = Schema::hasColumn('patients', 'proceed_admission_at');
        $patients = Patient::whereIn('ward_id', $zoneIds)
            ->where('is_active', true)
            ->whereIn('status', self::IN_ED)
            ->get(['id', 'ward_id', 'bed_number', 'admitted_at', 'consultant_id', ...($admissionsRecorded ? ['proceed_admission_at'] : [])]);
        $doctors = self::doctors($patients);
        $patients->each(function (Patient $patient) use ($now, $doctors) {
            $patient->setAttribute('minutes', $patient->admitted_at ? max(0, (int) $patient->admitted_at->diffInMinutes($now)) : null);
            $patient->setAttribute('doctor', $doctors[$patient->id] ?? null);
        });

        $arrivals = AdmissionLog::where('action', 'admit')
            ->whereIn('ward_id', $zoneIds)
            ->where('admitted_at', '>=', $now->copy()->subDays(self::TREND_DAYS)->startOfDay())
            ->where('admitted_at', '<=', $now)
            ->get(['id', 'patient_id', 'ward_id', 'admitted_at']);
        $departures = AdmissionLog::where('action', 'discharge')
            ->whereIn('ward_id', $zoneIds)
            ->where('discharged_at', '>=', $now->copy()->subDays(self::TREND_DAYS)->startOfDay())
            ->where('discharged_at', '<=', $now)
            ->get(['id', 'patient_id', 'ward_id', 'to_ward_id', 'discharged_at']);

        $rows = $zones->map(fn (Ward $zone) => self::zoneRow(
            $zone,
            $patients->where('ward_id', $zone->id),
            $arrivals->where('ward_id', $zone->id)->where('admitted_at', '>=', $midnight)->count(),
            $departures->where('ward_id', $zone->id)->where('discharged_at', '>=', $midnight)->count(),
            $target
        ))->values();

        $current = self::current($rows, $patients, $target);
        $flow = self::flow($arrivals, $departures, $zoneIds, $now, $target);
        $attention = self::attention($rows, $patients, $target);

        return [
            'generated_at' => $now->toIso8601String(),
            'generated_time' => $now->format('H:i:s'),
            'refresh_seconds' => self::REFRESH_SECONDS,
            'targets' => [
                'minutes' => $target,
                'choices' => self::TARGET_CHOICES,
                'busy' => self::OCCUPANCY_BUSY,
            ],
            'now' => $current,
            'flow' => $flow,
            'admission' => self::proceedForAdmission($patients, $now, $admissionsRecorded),
            'admission_demo' => self::proceedForAdmissionDemo($now),
            'ambulance' => self::ambulance($now),
            'ambulance_demo' => self::ambulanceDemo($now),
            'doctors' => self::doctorRows($patients, $target),
            'buckets' => self::buckets($patients, $target),
            'zones' => $rows->all(),
            'attention' => $attention->take(self::ATTENTION_LIMIT)->values()->all(),
            'attention_total' => $attention->count(),
            'trend' => self::trend($arrivals, $departures, $now),
        ];
    }

    // -- zones and the ED now -------------------------------------------------

    private static function zoneRow(Ward $zone, Collection $patients, int $arrivalsToday, int $departuresToday, int $target): array
    {
        $beds = $zone->beds->count();
        $closed = $zone->beds->where('status', Bed::STATUS_MAINTENANCE)->count();
        $occupied = $patients->count();
        $minutes = $patients->pluck('minutes')->filter(fn ($m) => $m !== null)->sort()->values();
        $over = $minutes->filter(fn ($m) => $m > $target)->count();
        $occupancy = $beds ? round($occupied / $beds * 100, 1) : null;

        return [
            'id' => $zone->id,
            'name' => $zone->ward_name,
            'short' => self::shortName($zone->ward_name),
            'code' => $zone->ward_code,
            'colour' => self::colour($zone->ward_name),
            'waiting' => self::isWaiting($zone->ward_name),
            'url' => route('ward.dashboard', ['ward_id' => $zone->id]),
            'beds' => $beds,
            'occupied' => $occupied,
            'closed' => $closed,
            'free' => max(0, $beds - $occupied - $closed),
            'occupancy' => $occupancy,
            'full' => $beds > 0 && $occupied >= $beds - $closed,
            'over_target' => $over,
            'median' => self::median($minutes),
            'longest' => $minutes->last(),
            'arrivals_today' => $arrivalsToday,
            'departures_today' => $departuresToday,
            'status' => self::worst([
                self::minutesSeverity($minutes->last(), $target),
                $beds > 0 && $occupied >= $beds - $closed ? (self::colour($zone->ward_name) === 'red' ? 'serious' : 'warning') : 'good',
            ]),
        ];
    }

    private static function current(Collection $rows, Collection $patients, int $target): array
    {
        $minutes = $patients->pluck('minutes')->filter(fn ($m) => $m !== null)->sort()->values();
        $beds = $rows->sum('beds');
        $occupied = $rows->sum('occupied');
        $longest = $patients->filter(fn ($p) => $p->minutes !== null)->sortByDesc('minutes')->first();
        $known = $minutes->count();
        $within = $minutes->filter(fn ($m) => $m <= $target)->count();
        $occupancy = $beds ? round($occupied / $beds * 100, 1) : null;

        return [
            'patients' => $patients->count(),
            'zones' => $rows->count(),
            'beds' => $beds,
            'occupied' => $occupied,
            'free' => $rows->sum('free'),
            'occupancy' => $occupancy,
            'occupancy_status' => $occupancy === null ? 'good' : ($occupancy >= 100 ? 'critical' : ($occupancy >= self::OCCUPANCY_BUSY ? 'warning' : 'good')),
            'waiting' => $rows->where('waiting', true)->sum('occupied'),
            'known' => $known,
            'unknown' => $patients->count() - $known,
            'within_target' => $within,
            'within_pct' => $known ? round($within / $known * 100, 1) : null,
            'over_target' => $known - $within,
            'over_double' => $minutes->filter(fn ($m) => $m > 2 * $target)->count(),
            'median' => self::median($minutes),
            'longest' => $longest?->minutes,
            'longest_zone' => $longest ? ($rows->firstWhere('id', $longest->ward_id)['short'] ?? null) : null,
            'time_status' => self::worst($minutes->map(fn ($m) => self::minutesSeverity($m, $target))->all()),
        ];
    }

    /** Patients in the ED now by time since they arrived, against the fixed BUCKETS. */
    private static function buckets(Collection $patients, int $target): array
    {
        $minutes = $patients->pluck('minutes')->filter(fn ($m) => $m !== null);

        return collect(self::BUCKETS)->map(fn (array $bucket) => [
            'label' => $bucket[0],
            'count' => $minutes->filter(fn ($m) => $m >= $bucket[1] && ($bucket[2] === null || $m < $bucket[2]))->count(),
            'over_target' => $bucket[1] >= $target,
        ])->all();
    }

    // -- flow ------------------------------------------------------------------

    private static function flow(Collection $arrivals, Collection $departures, array $zoneIds, CarbonInterface $now, int $target): array
    {
        $midnight = $now->copy()->startOfDay();
        $yesterday = $midnight->copy()->subDay();
        $sameTimeYesterday = $now->copy()->subDay();

        $leftToday = $departures->where('discharged_at', '>=', $midnight);
        $admitted = $leftToday->whereNotNull('to_ward_id')->count();

        // How long each who left today had been in the ED: from their last arrival in an ED zone before leaving
        $stays = self::stays($leftToday, $zoneIds);

        return [
            'arrivals_today' => $arrivals->where('admitted_at', '>=', $midnight)->count(),
            'arrivals_same_time_yesterday' => $arrivals->whereBetween('admitted_at', [$yesterday, $sameTimeYesterday])->count(),
            'departures_today' => $leftToday->count(),
            'departures_same_time_yesterday' => $departures->whereBetween('discharged_at', [$yesterday, $sameTimeYesterday])->count(),
            'admitted' => $admitted,
            'home' => $leftToday->count() - $admitted,
            'admission_rate' => $leftToday->count() ? round($admitted / $leftToday->count() * 100, 1) : null,
            'stay_median' => self::median($stays->sort()->values()),
            'stays_known' => $stays->count(),
            'left_within_target' => $stays->filter(fn ($m) => $m <= $target)->count(),
        ];
    }

    /** Minutes in the ED for each departure, where its arrival is known. */
    private static function stays(Collection $departures, array $zoneIds): Collection
    {
        if ($departures->isEmpty()) {
            return collect();
        }

        $arrivals = AdmissionLog::where('action', 'admit')
            ->whereIn('ward_id', $zoneIds)
            ->whereIn('patient_id', $departures->pluck('patient_id')->unique()->all())
            ->where('admitted_at', '>=', $departures->min('discharged_at')->copy()->subDays(3))
            ->get(['patient_id', 'admitted_at'])
            ->groupBy('patient_id');

        return $departures->map(function (AdmissionLog $departure) use ($arrivals) {
            $arrived = ($arrivals[$departure->patient_id] ?? collect())
                ->filter(fn ($log) => $log->admitted_at && $log->admitted_at->lte($departure->discharged_at))
                ->max('admitted_at');

            return $arrived ? (int) $arrived->diffInMinutes($departure->discharged_at) : null;
        })->filter(fn ($m) => $m !== null)->values();
    }

    private static function trend(Collection $arrivals, Collection $departures, CarbonInterface $now): array
    {
        $today = $now->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $hours = fn (Collection $logs, CarbonInterface $day) => collect(range(0, 23))->map(
            fn (int $h) => $logs->filter(fn ($log) => $log->admitted_at->between($day->copy()->addHours($h), $day->copy()->addHours($h + 1)->subSecond()))->count()
        )->all();

        $days = collect(range(self::TREND_DAYS - 1, 0))->map(fn (int $back) => $today->copy()->subDays($back));
        $onDay = fn (Collection $logs, string $field, CarbonInterface $day) => $logs
            ->filter(fn ($log) => $log->{$field} && $log->{$field}->isSameDay($day));

        return [
            'hour_now' => (int) $now->format('G'),
            'hourly_today' => $hours($arrivals->where('admitted_at', '>=', $today), $today),
            'hourly_yesterday' => $hours($arrivals->whereBetween('admitted_at', [$yesterday, $today->copy()->subSecond()]), $yesterday),
            'days' => $days->map(fn ($day) => $day->format('D j M'))->all(),
            'labels' => $days->map(fn ($day) => $day->format('j M'))->all(),
            'arrivals' => $days->map(fn ($day) => $onDay($arrivals, 'admitted_at', $day)->count())->all(),
            'admitted' => $days->map(fn ($day) => $onDay($departures, 'discharged_at', $day)->whereNotNull('to_ward_id')->count())->all(),
            'home' => $days->map(fn ($day) => $onDay($departures, 'discharged_at', $day)->whereNull('to_ward_id')->count())->all(),
        ];
    }

    // -- ambulance cases today -------------------------------------------------

    /** Ambulance calls since midnight, from the ambulance_calls table. */
    private static function ambulance(CarbonInterface $now): array
    {
        // Until the table's migration has run, the card says no calls rather than the board failing
        if (! Schema::hasTable('ambulance_calls')) {
            return self::ambulanceFigures(collect(), 0) + ['demo' => false];
        }

        $midnight = $now->copy()->startOfDay();
        $sameTimeYesterday = $now->copy()->subDay();
        $calls = AmbulanceCall::where('called_at', '>=', $midnight->copy()->subDay())
            ->where('called_at', '<=', $now)
            ->get(['id', 'called_at', 'arrived_at', 'cancelled_at']);

        $today = $calls->filter(fn (AmbulanceCall $call) => $call->called_at->gte($midnight))->map(fn (AmbulanceCall $call) => [
            'called' => $call->called_at,
            'arrived' => $call->arrived_at && $call->arrived_at->lte($now) ? $call->arrived_at : null,
            'cancelled' => $call->cancelled_at && $call->cancelled_at->lte($now) ? $call->cancelled_at : null,
        ])->values();
        $yesterday = $calls->filter(fn (AmbulanceCall $call) => $call->called_at->lt($midnight) && $call->called_at->lte($sameTimeYesterday))->count();

        return self::ambulanceFigures($today, $yesterday) + ['demo' => false];
    }

    /**
     * The ambulance card from made-up calls, for showing the board. The same
     * calls all day (they follow from the date), adding up as the day goes on:
     * a call counts once its time has come, and is attended once its crew's
     * response time has passed.
     */
    public static function ambulanceDemo(CarbonInterface $now): array
    {
        $midnight = $now->copy()->startOfDay();
        $today = self::demoCalls($midnight)
            ->filter(fn (array $call) => $call['called']->lte($now))
            ->map(fn (array $call) => [
                'called' => $call['called'],
                'arrived' => ! $call['cancels'] && $call['arrives']->lte($now) ? $call['arrives'] : null,
                'cancelled' => $call['cancels'] && $call['arrives']->lte($now) ? $call['arrives'] : null,
            ])->values();
        $yesterday = self::demoCalls($midnight->copy()->subDay())
            ->filter(fn (array $call) => $call['called']->lte($now->copy()->subDay()))
            ->count();

        return self::ambulanceFigures($today, $yesterday) + ['demo' => true];
    }

    /** A day's made-up calls: busier by day than by night, most crews there within 7 to 20 minutes. */
    private static function demoCalls(CarbonInterface $day): Collection
    {
        $random = DemoDay::random('ambulance-demo', $day);
        $hours = [2, 1.5, 1, 1, 1, 1.5, 2.5, 4, 5, 5.5, 5.5, 5, 4.5, 4.5, 4.5, 5, 5.5, 6, 6, 5.5, 5, 4, 3, 2.5];

        return collect(range(1, 14 + (int) floor($random('count') * 11)))->map(function (int $i) use ($day, $random, $hours) {
            $called = DemoDay::moment($day, $random, $hours, $i);
            $minutes = 7 + 13 * $random("response.{$i}") ** 1.6 + ($random("far.{$i}") < 0.1 ? 12 : 0);

            return [
                'called' => $called,
                'arrives' => $called->copy()->addSeconds((int) round($minutes * 60)),
                'cancels' => $random("cancel.{$i}") < 0.06,
            ];
        })->sortBy('called')->values();
    }

    /**
     * What the card shows, from today's calls so far ([called, arrived, cancelled]
     * each), and how many had come in by this time yesterday.
     */
    private static function ambulanceFigures(Collection $calls, int $sameTimeYesterday): array
    {
        $attended = $calls->filter(fn (array $call) => $call['arrived'] !== null);
        $cancelled = $calls->filter(fn (array $call) => $call['arrived'] === null && $call['cancelled'] !== null)->count();
        $responses = $attended
            ->map(fn (array $call) => (int) round(max(0, $call['arrived']->getTimestamp() - $call['called']->getTimestamp()) / 60))
            ->sort()->values();

        return [
            'calls' => $calls->count(),
            'calls_same_time_yesterday' => $sameTimeYesterday,
            'attended' => $attended->count(),
            'attended_pct' => $calls->count() ? round($attended->count() / $calls->count() * 100, 1) : null,
            'open' => $calls->count() - $attended->count() - $cancelled,
            'cancelled' => $cancelled,
            'response_median' => self::median($responses),
            'response_average' => $responses->count() ? round($responses->avg(), 1) : null,
            'response_longest' => $responses->max(),
        ];
    }

    // -- proceed for admission -------------------------------------------------

    /**
     * Proceed for admission: the decisions to admit today (proceed_admission_at,
     * whether or not the patient has since gone to the ward), and the patients
     * still in the ED waiting for a ward bed, decided during their stay.
     */
    private static function proceedForAdmission(Collection $patients, CarbonInterface $now, bool $recorded): array
    {
        if (! $recorded) {
            return self::admissionFigures(0, 0, collect()) + ['demo' => false];
        }

        $midnight = $now->copy()->startOfDay();
        $decided = fn (CarbonInterface $from, CarbonInterface $to) => Patient::whereBetween('proceed_admission_at', [$from, $to])->count();
        $waiting = $patients
            ->filter(fn (Patient $patient) => $patient->proceed_admission_at
                && $patient->proceed_admission_at->lte($now)
                && (! $patient->admitted_at || $patient->proceed_admission_at->gte($patient->admitted_at)))
            ->map(fn (Patient $patient) => (int) round(($now->getTimestamp() - $patient->proceed_admission_at->getTimestamp()) / 60))
            ->values();

        return self::admissionFigures($decided($midnight, $now), $decided($midnight->copy()->subDay(), $now->copy()->subDay()), $waiting) + ['demo' => false];
    }

    /**
     * Proceed for admission from made-up decisions, for showing the board, like
     * ambulanceDemo(): the same all day, adding up as it goes, each patient
     * waiting for a bed until their made-up wait is over.
     */
    public static function proceedForAdmissionDemo(CarbonInterface $now): array
    {
        $midnight = $now->copy()->startOfDay();
        $today = self::demoAdmissions($midnight)->filter(fn (array $decision) => $decision['decided']->lte($now));
        $yesterday = self::demoAdmissions($midnight->copy()->subDay());
        $waiting = $today->concat($yesterday)
            ->filter(fn (array $decision) => $decision['decided']->lte($now) && $decision['moves']->gt($now))
            ->map(fn (array $decision) => (int) round(($now->getTimestamp() - $decision['decided']->getTimestamp()) / 60))
            ->values();
        $sameTimeYesterday = $yesterday->filter(fn (array $decision) => $decision['decided']->lte($now->copy()->subDay()))->count();

        return self::admissionFigures($today->count(), $sameTimeYesterday, $waiting) + ['demo' => true];
    }

    /** A day's made-up decisions to admit: most by day, each waiting half an hour to four hours for a bed. */
    private static function demoAdmissions(CarbonInterface $day): Collection
    {
        $random = DemoDay::random('admission-demo', $day);
        $hours = [1.5, 1.5, 1, 1, 1, 1, 1.5, 2, 3, 4, 4.5, 5, 5, 5, 5, 5, 5, 5.5, 5.5, 5, 4.5, 4, 3, 2];

        return collect(range(1, 6 + (int) floor($random('count') * 7)))->map(function (int $i) use ($day, $random, $hours) {
            $decided = DemoDay::moment($day, $random, $hours, $i);

            return ['decided' => $decided, 'moves' => $decided->copy()->addMinutes(30 + (int) round(210 * $random("wait.{$i}") ** 1.5))];
        })->sortBy('decided')->values();
    }

    /** What the row shows: today's decisions, as many by this time yesterday, and the minutes each still in the ED has waited. */
    private static function admissionFigures(int $today, int $sameTimeYesterday, Collection $waitingMinutes): array
    {
        return [
            'today' => $today,
            'same_time_yesterday' => $sameTimeYesterday,
            'waiting' => $waitingMinutes->count(),
            'waiting_median' => self::median($waitingMinutes),
            'waiting_longest' => $waitingMinutes->max(),
        ];
    }

    // -- doctors ---------------------------------------------------------------

    /** Each patient's attending doctor: their active attending care provider, else their consultant. */
    private static function doctors(Collection $patients): array
    {
        if ($patients->isEmpty()) {
            return [];
        }

        $attending = PatientCareProvider::whereIn('patient_id', $patients->modelKeys())
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->where('is_active', true)
            ->with(['consultant:id,name', 'anaesthetist:id,name'])
            ->orderByDesc('assigned_at')
            ->get()
            ->groupBy('patient_id');
        $consultants = Consultant::whereIn('id', $patients->pluck('consultant_id')->filter()->unique()->all())->pluck('name', 'id');

        return $patients->mapWithKeys(fn (Patient $patient) => [
            $patient->id => ($attending[$patient->id] ?? collect())->first()?->display_name
                ?: ($consultants[$patient->consultant_id] ?? null),
        ])->all();
    }

    private static function doctorRows(Collection $patients, int $target): array
    {
        $rows = $patients->groupBy(fn ($p) => $p->doctor ?: '')->map(fn (Collection $group, string $name) => [
            'name' => $name === '' ? null : $name,
            'patients' => $group->count(),
            'over_target' => $group->filter(fn ($p) => $p->minutes !== null && $p->minutes > $target)->count(),
        ])->values();

        $named = $rows->whereNotNull('name')->sortBy([['patients', 'desc'], ['name', 'asc']])->values();

        return [
            'count' => $named->count(),
            'per_doctor' => $named->count() ? round($named->sum('patients') / $named->count(), 1) : null,
            'unassigned' => $patients->filter(fn ($p) => ! $p->doctor)->count(),
            'rows' => $named->all(),
        ];
    }

    // -- attention -------------------------------------------------------------

    private static function attention(Collection $rows, Collection $patients, int $target): Collection
    {
        $items = collect();

        foreach ($patients->filter(fn ($p) => $p->minutes !== null && $p->minutes > $target) as $patient) {
            $zone = $rows->firstWhere('id', $patient->ward_id);
            $items->push([
                'key' => 'patient.' . $patient->id,
                'severity' => self::minutesSeverity($patient->minutes, $target),
                'topic' => 'time',
                'minutes' => $patient->minutes,
                'zone' => $zone['short'] ?? null,
                'colour' => $zone['colour'] ?? null,
                'title' => trim(($zone['short'] ?? 'ED') . ' · ' . ($patient->bed_number ?: 'no bed')),
                'detail' => 'In the ED ' . self::duration($patient->minutes) . ', since ' . $patient->admitted_at->format('H:i')
                    . ($patient->admitted_at->isToday() ? '' : ' ' . $patient->admitted_at->format('j M'))
                    . ' · ' . ($patient->doctor ?: 'no doctor assigned'),
                // The "since" time in the detail, and when it was, so the board can say it in its own time format
                'since_label' => $patient->admitted_at->format('H:i'),
                'since' => $patient->admitted_at->toIso8601String(),
                'url' => $zone['url'] ?? null,
            ]);
        }

        foreach ($rows->where('full', true) as $zone) {
            $items->push([
                'key' => 'zone.' . $zone['id'],
                'severity' => $zone['colour'] === 'red' ? 'serious' : 'warning',
                'topic' => 'capacity',
                'minutes' => null,
                'zone' => $zone['short'],
                'colour' => $zone['colour'],
                'title' => $zone['short'] . ' is full',
                'detail' => "{$zone['occupied']} of {$zone['beds']} beds taken" . ($zone['closed'] ? " · {$zone['closed']} closed" : ''),
                'since_label' => null,
                'since' => null,
                'url' => $zone['url'],
            ]);
        }

        return $items->sortBy([
            fn ($a, $b) => array_search($a['severity'], self::SEVERITIES) <=> array_search($b['severity'], self::SEVERITIES),
            fn ($a, $b) => ($b['minutes'] ?? PHP_INT_MAX) <=> ($a['minutes'] ?? PHP_INT_MAX),
        ])->values();
    }

    // -- helpers ---------------------------------------------------------------

    /** Over the target is worth watching, over twice it serious, over three times critical. */
    public static function minutesSeverity(?int $minutes, int $target): string
    {
        return match (true) {
            $minutes === null || $minutes <= $target => 'good',
            $minutes > 3 * $target => 'critical',
            $minutes > 2 * $target => 'serious',
            default => 'warning',
        };
    }

    private static function worst(array $severities): string
    {
        foreach (self::SEVERITIES as $severity) {
            if (in_array($severity, $severities, true)) {
                return $severity;
            }
        }

        return 'good';
    }

    private static function median(Collection $sorted): ?int
    {
        $n = $sorted->count();
        if (! $n) {
            return null;
        }
        $sorted = $sorted->sort()->values();

        return (int) round($n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2);
    }

    /** "ED - YELLOW ZONE" -> "Yellow Zone": the zone as the board names it. */
    public static function shortName(string $name): string
    {
        $short = preg_replace('/^\s*(ED|A&E|AE|ER)\s*[-–:]\s*/i', '', $name);

        return Str::title(Str::lower(trim($short) ?: $name));
    }

    /** The triage colour a zone's name carries, if any. */
    public static function colour(string $name): ?string
    {
        foreach (['red', 'yellow', 'green'] as $colour) {
            if (preg_match('/\b' . $colour . '\b/i', $name)) {
                return $colour;
            }
        }

        return null;
    }

    private static function isWaiting(string $name): bool
    {
        return (bool) preg_match('/\bwait/i', $name);
    }

    public static function duration(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        return $minutes < 60 ? "{$minutes} min" : intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
