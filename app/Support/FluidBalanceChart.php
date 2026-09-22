<?php

namespace App\Support;

use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Models\WardDashboardSetting;
use App\Services\ShiftHandover;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A patient's I/O chart (fluid balance), one chart day at a time.
 *
 * A chart day runs for 24 hours from the start of the ward's first shift
 * (07:00 with the default shift times) and is subtotalled by the ward's
 * shifts, the way totals are handed over. Only entries that are not struck
 * out count. A day is held to the fluid plan in force at its end (today: the
 * plan in force now), and the latest overload assessment stands until a
 * newer one replaces it.
 */
final class FluidBalanceChart
{
    /** The key of the I/O Chart tab in the per-user Patient Details tab settings. */
    public const SETTINGS_TAB = 'io';

    /**
     * Urine is only judged against its hourly minimum once this much of the
     * day has passed, so a patient who has not voided yet this morning is not
     * flagged straight away.
     */
    public const URINE_CHECK_AFTER_HOURS = 4;

    /** Chart days listed in the day summary, today included. */
    public const SUMMARY_DAYS = 7;

    private const LEVEL_RANK = ['critical' => 0, 'warning' => 1, 'info' => 2];

    /** The I/O Chart tab is on unless a user switches it off in Settings. */
    public static function enabledFor(?WardDashboardSetting $settings): bool
    {
        return (bool) (($settings?->patient_details_tabs ?? [])[self::SETTINGS_TAB] ?? true);
    }

    /**
     * The chart day a moment falls in, as [start, end).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayAt(?int $wardId, CarbonInterface $at): array
    {
        return self::window(self::startTime(ShiftHandover::shifts($wardId)), Carbon::instance($at));
    }

    /**
     * Everything the I/O Chart tab shows for one chart day: the day starting
     * on $date (Y-m-d), or the current day. A date before the admission or
     * after today falls back to the nearest day there is.
     */
    public static function forPatient(Patient $patient, ?string $date = null, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $shifts = ShiftHandover::shifts($patient->ward_id);
        $startTime = self::startTime($shifts);

        [$currentStart] = self::window($startTime, $now);
        $firstStart = $patient->admitted_at
            ? self::window($startTime, Carbon::instance($patient->admitted_at))[0]
            : null;
        if ($firstStart && $firstStart->greaterThan($currentStart)) {
            $firstStart = $currentStart->copy();
        }

        $start = self::parseDate($date)?->setTimeFromTimeString($startTime) ?? $currentStart->copy();
        if ($start->greaterThan($currentStart)) {
            $start = $currentStart->copy();
        }
        if ($firstStart && $start->lessThan($firstStart)) {
            $start = $firstStart->copy();
        }
        $end = $start->copy()->addDay();
        $isCurrent = $start->equalTo($currentStart);

        $plans = FluidBalancePlan::where('patient_id', $patient->id)
            ->when($patient->admitted_at, fn ($query) => $query->where('created_at', '>=', $patient->admitted_at))
            ->with('setBy:id,name')
            ->orderBy('id')
            ->get();
        $plan = self::planAt($plans, $isCurrent ? $now : $end);

        $entries = FluidBalanceEntry::where('patient_id', $patient->id)
            ->where('recorded_at', '>=', $start)
            ->where('recorded_at', '<', $end)
            ->with(['recordedBy:id,name', 'voidedBy:id,name'])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();
        $totals = self::totals($entries->reject(fn (FluidBalanceEntry $entry) => $entry->isVoided()));

        $assessments = FluidOverloadAssessment::where('patient_id', $patient->id)
            ->when($patient->admitted_at, fn ($query) => $query->where('created_at', '>=', $patient->admitted_at))
            ->with('recordedBy:id,name')
            ->orderByDesc('assessed_at')
            ->orderByDesc('id')
            ->get();
        $weight = self::weightTrend($assessments);

        return [
            'day' => [
                'start' => $start,
                'end' => $end,
                'key' => $start->toDateString(),
                'is_current' => $isCurrent,
                'previous' => !$firstStart || $start->greaterThan($firstStart) ? $start->copy()->subDay()->toDateString() : null,
                'next' => $isCurrent ? null : $start->copy()->addDay()->toDateString(),
            ],
            'plan' => $plan,
            'current_plan' => self::planAt($plans, $now),
            'plan_history' => $plans->reverse()->values(),
            'entries' => $entries,
            'shifts' => self::shiftGroups($entries, $shifts, $startTime, $start, $end, $isCurrent ? $now : null),
            'totals' => $totals,
            'status' => self::evaluate(
                $plan, $totals, self::observed($patient, $start, $isCurrent ? $now : $end),
                $isCurrent, $assessments->first(), $weight, $end
            ),
            'assessments' => $assessments,
            'latest_assessment' => $assessments->first(),
            'weight' => $weight,
            'days' => self::daySummary($patient, $plans, $startTime, $currentStart, $firstStart, $now),
        ];
    }

    /**
     * What the bed boxes show, keyed by patient id: today's totals against
     * the plan and anything flagged. Patients with no plan, no entries today
     * and no overload assessment are left out.
     *
     * @param  iterable<Patient>  $patients
     * @return array<int, array{level: ?string, intake: int, output: int, balance: int, limit: ?array, alerts: array}>
     */
    public static function alertsForPatients(iterable $patients, ?CarbonInterface $now = null): array
    {
        $patients = collect($patients)->filter(fn (Patient $patient) => $patient->ward_id)->keyBy('id');

        if ($patients->isEmpty()) {
            return [];
        }

        $now = $now ? Carbon::instance($now) : now();
        $ids = $patients->keys();
        // Only rows from the earliest current stay are needed, unless a patient has no admission time
        $since = $patients->contains(fn (Patient $patient) => !$patient->admitted_at)
            ? null
            : $patients->pluck('admitted_at')->min();

        // Rows saved during each patient's current stay
        $thisStay = fn (Collection $rows, int $patientId) => $rows->filter(
            fn ($row) => !$patients[$patientId]->admitted_at
                || $row->created_at->greaterThanOrEqualTo($patients[$patientId]->admitted_at)
        )->values();

        $plans = FluidBalancePlan::whereIn('patient_id', $ids)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->orderBy('id')
            ->get()
            ->groupBy('patient_id');

        $assessments = FluidOverloadAssessment::whereIn('patient_id', $ids)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->with('recordedBy:id,name')
            ->orderByDesc('assessed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id');

        // Today's totals: one query per ward, as each ward may start its day at another time
        $days = [];
        $totals = [];
        foreach ($patients->groupBy('ward_id') as $wardId => $wardPatients) {
            $days[$wardId] = self::dayAt($wardId, $now);

            FluidBalanceEntry::counted()
                ->whereIn('patient_id', $wardPatients->pluck('id'))
                ->where('recorded_at', '>=', $days[$wardId][0])
                ->where('recorded_at', '<', $days[$wardId][1])
                ->selectRaw('patient_id, direction, category, SUM(volume_ml) as volume_ml')
                ->groupBy('patient_id', 'direction', 'category')
                ->get()
                ->groupBy('patient_id')
                ->each(function (Collection $rows, $patientId) use (&$totals) {
                    $totals[$patientId] = self::totals($rows);
                });
        }

        $result = [];
        foreach ($patients as $id => $patient) {
            $plan = $thisStay($plans->get($id, collect()), $id)->last();
            $patientAssessments = $thisStay($assessments->get($id, collect()), $id);
            $latest = $patientAssessments->first();

            if (!isset($totals[$id]) && !$plan?->hasLimits() && !$latest) {
                continue;
            }

            [$start, $end] = $days[$patient->ward_id];
            $dayTotals = $totals[$id] ?? self::totals(collect());
            $status = self::evaluate(
                $plan, $dayTotals, self::observed($patient, $start, $now),
                true, $latest, self::weightTrend($patientAssessments), $end
            );

            $result[$id] = [
                'level' => $status['alerts'][0]['level'] ?? null,
                'intake' => $dayTotals['intake'],
                'output' => $dayTotals['output'],
                'balance' => $dayTotals['balance'],
                'limit' => $status['limit'],
                'alerts' => $status['alerts'],
            ];
        }

        return $result;
    }

    /**
     * The part of a chart day the patient has been on the ward for: from the
     * day's start, or the admission if later, up to $until (now, for today).
     *
     * @return array{from: Carbon, hours: float}
     */
    private static function observed(Patient $patient, Carbon $dayStart, Carbon $until): array
    {
        $from = $patient->admitted_at && $patient->admitted_at->greaterThan($dayStart)
            ? Carbon::instance($patient->admitted_at)
            : $dayStart;

        return ['from' => $from, 'hours' => max(0, $until->getTimestamp() - $from->getTimestamp()) / 3600];
    }

    /**
     * The limit and urine checks for a day's totals, and everything flagged,
     * worst first. Limit and urine flags are raised for the current day only;
     * overload signs and weight gain stand whichever day is being viewed.
     * Urine is averaged over the observed part of the day (see observed()).
     */
    private static function evaluate(
        ?FluidBalancePlan $plan,
        array $totals,
        array $observed,
        bool $isCurrent,
        ?FluidOverloadAssessment $latest,
        ?array $weight,
        Carbon $dayEnd
    ): array {
        $alerts = [];
        $limit = null;
        $urine = null;
        $hours = $observed['hours'];
        $urineAverage = $hours >= 1 ? (int) round($totals['urine'] / $hours) : null;

        if ($plan?->intake_limit_ml) {
            $cap = $plan->intake_limit_ml;
            $taken = $totals['intake'];
            $state = $taken > $cap
                ? 'over'
                : ($taken * 100 >= $cap * FluidBalancePlan::NEAR_LIMIT_PERCENT ? 'near' : 'ok');

            $limit = [
                'limit' => $cap,
                'taken' => $taken,
                'percent' => (int) floor($taken * 100 / $cap),
                'remaining' => max(0, $cap - $taken),
                'over_by' => max(0, $taken - $cap),
                'state' => $state,
            ];

            if ($isCurrent && $state === 'over') {
                $alerts[] = [
                    'level' => 'critical',
                    'title' => 'Over the intake limit',
                    'detail' => FluidBalanceEntry::formatMl($taken) . ' taken in against a limit of '
                        . FluidBalanceEntry::formatMl($cap) . ', ' . FluidBalanceEntry::formatMl($limit['over_by']) . ' over.',
                ];
            } elseif ($isCurrent && $state === 'near') {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'Near the intake limit',
                    'detail' => FluidBalanceEntry::formatMl($taken) . ' of ' . FluidBalanceEntry::formatMl($cap)
                        . ' taken in. ' . FluidBalanceEntry::formatMl($limit['remaining']) . ' left until '
                        . $dayEnd->format('H:i') . '.',
                ];
            }
        }

        if ($plan?->urine_min_ml_per_hour) {
            $min = $plan->urine_min_ml_per_hour;
            $state = $hours < self::URINE_CHECK_AFTER_HOURS
                ? 'pending'
                : ($urineAverage < $min ? 'low' : 'ok');

            $urine = ['min' => $min, 'average' => $urineAverage, 'hours' => (int) floor($hours), 'state' => $state];

            if ($isCurrent && $state === 'low') {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => 'Urine output below target',
                    'detail' => 'Averaging ' . $urineAverage . ' mL/h (' . FluidBalanceEntry::formatMl($totals['urine'])
                        . ' since ' . $observed['from']->format('H:i') . '), against at least ' . $min . ' mL/h.',
                ];
            }
        }

        if ($latest && $latest->hasOverloadSigns()) {
            $recorded = 'Recorded ' . $latest->assessed_at->format('d M H:i')
                . ($latest->recordedBy ? ' by ' . $latest->recordedBy->name : '') . '.';

            if ($latest->hasUrgentSign()) {
                $urgent = array_intersect_key(
                    FluidOverloadAssessment::SIGNS,
                    array_flip(array_intersect($latest->signs ?? [], FluidOverloadAssessment::URGENT_SIGNS))
                );
                $alerts[] = [
                    'level' => 'critical',
                    'title' => implode(', ', $urgent),
                    'detail' => 'Can mean fluid on the lungs. Escalate to the doctor now. ' . $recorded,
                ];
            }

            $alerts[] = [
                'level' => 'warning',
                'title' => 'Signs of fluid overload',
                'detail' => implode(', ', $latest->findings()) . '. ' . $recorded,
            ];
        }

        if ($weight && $weight['gain'] !== null) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Weight up ' . number_format($weight['gain'], 1) . ' kg',
                'detail' => number_format($weight['gain_from'], 1) . ' kg on ' . $weight['gain_since']->format('d M')
                    . ' to ' . number_format($weight['kg'], 1) . ' kg on ' . $weight['at']->format('d M')
                    . '. A gain of ' . number_format(FluidOverloadAssessment::WEIGHT_GAIN_FLAG_KG, 1) . ' kg within '
                    . FluidOverloadAssessment::WEIGHT_GAIN_WINDOW_DAYS . ' days suggests fluid is being retained.',
            ];
        }

        usort($alerts, fn ($a, $b) => self::LEVEL_RANK[$a['level']] <=> self::LEVEL_RANK[$b['level']]);

        return [
            'limit' => $limit,
            'urine' => $urine,
            'urine_average' => $urineAverage,
            'observed_from' => $observed['from'],
            'alerts' => $alerts,
        ];
    }

    /**
     * Totals by direction and type. Takes entries, or rows already summed per
     * type, as long as each has a direction, a category and a volume_ml.
     *
     * @return array{intake: int, output: int, balance: int, urine: int, by_type: array{intake: array<string, int>, output: array<string, int>}}
     */
    private static function totals(iterable $rows): array
    {
        $byType = [FluidBalanceEntry::DIRECTION_INTAKE => [], FluidBalanceEntry::DIRECTION_OUTPUT => []];

        foreach ($rows as $row) {
            $byType[$row->direction][$row->category] = ($byType[$row->direction][$row->category] ?? 0) + (int) $row->volume_ml;
        }

        $intake = array_sum($byType[FluidBalanceEntry::DIRECTION_INTAKE]);
        $output = array_sum($byType[FluidBalanceEntry::DIRECTION_OUTPUT]);

        return [
            'intake' => $intake,
            'output' => $output,
            'balance' => $intake - $output,
            'urine' => $byType[FluidBalanceEntry::DIRECTION_OUTPUT]['urine'] ?? 0,
            'by_type' => $byType,
        ];
    }

    /**
     * The day's entries under the shift each was recorded in, in the order
     * the shifts fall in the chart day, with a running balance on every entry
     * that counts. Shifts still to come today are left out.
     */
    private static function shiftGroups(Collection $entries, Collection $shifts, string $startTime, Carbon $dayStart, Carbon $dayEnd, ?Carbon $now): array
    {
        $dayStartMinute = self::minuteOfDay($startTime);
        $groups = [];

        foreach ($shifts as $index => $shift) {
            $offset = (self::minuteOfDay((string) $shift->start_time) - $dayStartMinute + 1440) % 1440;
            $length = (self::minuteOfDay((string) $shift->end_time) - self::minuteOfDay((string) $shift->start_time) + 1440) % 1440 ?: 1440;
            $from = $dayStart->copy()->addMinutes($offset);
            $to = $from->copy()->addMinutes($length)->min($dayEnd);

            $groups[$index] = [
                'code' => $shift->shift_code,
                'name' => $shift->shift_name,
                'time' => $from->format('H:i') . ' - ' . $to->format('H:i'),
                'offset' => $offset,
                'started' => !$now || $now->greaterThanOrEqualTo($from),
                'current' => $now && $now->greaterThanOrEqualTo($from) && $now->lessThan($to),
                'rows' => [],
                'intake' => 0,
                'output' => 0,
            ];
        }

        // Entries whose time falls in no shift, when the ward's shifts leave gaps
        $groups['other'] = [
            'code' => null, 'name' => 'Outside shift times', 'time' => null, 'offset' => 1440,
            'started' => true, 'current' => false, 'rows' => [], 'intake' => 0, 'output' => 0,
        ];

        $running = 0;
        foreach ($entries as $entry) {
            $time = $entry->recorded_at->format('H:i:s');
            $key = $shifts->search(fn (ShiftSetting $shift) => $shift->isTimeInShift($time));
            $key = $key === false ? 'other' : $key;

            if (!$entry->isVoided()) {
                $running += $entry->isIntake() ? $entry->volume_ml : -$entry->volume_ml;
                $groups[$key][$entry->isIntake() ? 'intake' : 'output'] += $entry->volume_ml;
            }

            $groups[$key]['rows'][] = ['entry' => $entry, 'running' => $entry->isVoided() ? null : $running];
        }

        return collect($groups)
            ->filter(fn (array $group) => $group['rows'] || ($group['code'] !== null && $group['started']))
            ->sortBy('offset')
            ->map(fn (array $group) => $group + ['balance' => $group['intake'] - $group['output']])
            ->values()
            ->all();
    }

    /**
     * Totals for each of the last few chart days of this stay, newest first,
     * and the balance over the whole stay so far.
     */
    private static function daySummary(Patient $patient, Collection $plans, string $startTime, Carbon $currentStart, ?Carbon $firstStart, Carbon $now): array
    {
        $from = $currentStart->copy()->subDays(self::SUMMARY_DAYS - 1);
        if ($firstStart && $from->lessThan($firstStart)) {
            $from = $firstStart->copy();
        }
        $currentEnd = $currentStart->copy()->addDay();

        $byDay = FluidBalanceEntry::counted()
            ->where('patient_id', $patient->id)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<', $currentEnd)
            ->get(['direction', 'category', 'volume_ml', 'recorded_at'])
            ->groupBy(fn (FluidBalanceEntry $entry) => self::window($startTime, $entry->recorded_at)[0]->toDateString());

        $days = [];
        for ($start = $currentStart->copy(); $start->greaterThanOrEqualTo($from); $start->subDay()) {
            $key = $start->toDateString();
            $isCurrent = $start->equalTo($currentStart);
            $totals = self::totals($byDay->get($key, collect()));
            $limit = self::planAt($plans, $isCurrent ? $now : $start->copy()->addDay())?->intake_limit_ml;

            $days[] = [
                'key' => $key,
                'start' => $start->copy(),
                'is_current' => $isCurrent,
                'entries' => $byDay->get($key, collect())->count(),
                'intake' => $totals['intake'],
                'output' => $totals['output'],
                'balance' => $totals['balance'],
                'limit' => $limit,
                'over' => $limit !== null && $totals['intake'] > $limit,
            ];
        }

        // Days before anything was charted say nothing; a gap between charted days does
        while (count($days) > 1 && end($days)['entries'] === 0) {
            array_pop($days);
        }

        // Over the whole stay, which may be longer than the days listed
        $stay = $firstStart
            ? FluidBalanceEntry::counted()
                ->where('patient_id', $patient->id)
                ->where('recorded_at', '>=', $firstStart)
                ->where('recorded_at', '<', $currentEnd)
                ->selectRaw("SUM(CASE WHEN direction = 'intake' THEN volume_ml ELSE 0 END) as intake")
                ->selectRaw("SUM(CASE WHEN direction = 'output' THEN volume_ml ELSE 0 END) as output")
                ->first()
            : null;

        return [
            'rows' => $days,
            'stay_balance' => $stay ? (int) $stay->intake - (int) $stay->output : null,
            'stay_since' => $firstStart,
        ];
    }

    /** The latest weight, its change from the one before, and any gain worth flagging. */
    private static function weightTrend(Collection $assessments): ?array
    {
        // Newest first, as the assessments are ordered
        $weighed = $assessments->filter(fn (FluidOverloadAssessment $a) => $a->weight_kg !== null)->values();
        $latest = $weighed->first();

        if (!$latest) {
            return null;
        }

        $kg = (float) $latest->weight_kg;
        $previous = $weighed->get(1);
        $windowStart = $latest->assessed_at->copy()->subDays(FluidOverloadAssessment::WEIGHT_GAIN_WINDOW_DAYS);
        $lowest = $weighed->slice(1)
            ->filter(fn (FluidOverloadAssessment $a) => $a->assessed_at->greaterThanOrEqualTo($windowStart))
            ->sortBy(fn (FluidOverloadAssessment $a) => (float) $a->weight_kg)
            ->first();
        $gain = $lowest ? round($kg - (float) $lowest->weight_kg, 1) : null;

        return [
            'kg' => $kg,
            'at' => $latest->assessed_at,
            'change' => $previous ? round($kg - (float) $previous->weight_kg, 1) : null,
            'previous_at' => $previous?->assessed_at,
            'gain' => $gain !== null && $gain >= FluidOverloadAssessment::WEIGHT_GAIN_FLAG_KG ? $gain : null,
            'gain_from' => $lowest ? (float) $lowest->weight_kg : null,
            'gain_since' => $lowest?->assessed_at,
        ];
    }

    private static function planAt(Collection $plans, Carbon $moment): ?FluidBalancePlan
    {
        return $plans->filter(fn (FluidBalancePlan $plan) => $plan->created_at->lessThanOrEqualTo($moment))->last();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function window(string $startTime, Carbon $at): array
    {
        $start = $at->copy()->setTimeFromTimeString($startTime);

        if ($start->greaterThan($at)) {
            $start->subDay();
        }

        return [$start, $start->copy()->addDay()];
    }

    /** "07:00": when the ward's first shift starts, which is when its chart day starts. */
    private static function startTime(Collection $shifts): string
    {
        return substr((string) ($shifts->first()?->start_time ?? '07:00'), 0, 5);
    }

    private static function minuteOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', array_pad(explode(':', $time), 2, 0));

        return ($hours * 60 + $minutes) % 1440;
    }

    private static function parseDate(?string $date): ?Carbon
    {
        if (!$date || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d', $date);
    }
}
