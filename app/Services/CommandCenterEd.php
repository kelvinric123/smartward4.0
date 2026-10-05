<?php

namespace App\Services;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\Ward;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
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
 *    and gone home, and how long those who left had been in the ED;
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

        $patients = Patient::whereIn('ward_id', $zoneIds)
            ->where('is_active', true)
            ->whereIn('status', self::IN_ED)
            ->get(['id', 'ward_id', 'bed_number', 'admitted_at', 'consultant_id']);
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
