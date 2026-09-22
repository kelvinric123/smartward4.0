<?php

namespace App\Services\NurseScheduling;

use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\PublicHoliday;
use App\Models\Ward;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Plans who works which shift, day by day, for a ward's team.
 *
 * Hard rules, never broken:
 * - nobody works on a leave day, twice in one day, or in two wards on one day;
 * - after a night (ON) the next day is another night or a day off;
 * - no more than max_consecutive_days working days in a row, and no more than
 *   max_consecutive_nights nights in a row.
 *
 * First it fills each shift's minimum staffing, nights first as they are the
 * hardest to cover. A night goes to a nurse part-way through a run of nights
 * where possible, so nights come in runs of about three rather than singles.
 * Otherwise each shift goes to the nurse it is fairest to give it to: fewest
 * shifts this week and over the last four weeks, fewest nights, and behind
 * the team average on weekends and public holidays, with no PM followed by an
 * AM unless there is no one else. Then it tops nurses up towards their weekly
 * target on the thinnest day shifts, steering those ahead on weekends and
 * holidays to weekdays.
 *
 * Manual entries are kept and planned around. The generator's own earlier
 * entries for this ward in the period are replaced.
 */
final class RosterGenerator
{
    private const HISTORY_DAYS = 28;
    private const LOOKAHEAD_DAYS = 7;
    private const WORKING = ['AM', 'PM', 'ON'];

    /** Nights in a row a run aims for; the ward's maximum still caps it. */
    private const PREFERRED_NIGHT_RUN = 3;

    /** nurse id => date => shift, for entries that are kept (any ward) */
    private array $fixed = [];
    /** nurse id => date => ward id, for the kept entries */
    private array $fixedWard = [];
    /** nurse id => date => shift planned by this run */
    private array $planned = [];
    /** nurse id => date => true */
    private array $leave = [];
    /** date => true */
    private array $holidays = [];

    /** Fairness counters per nurse id, over the last four weeks plus this run */
    private array $recentShifts = [];
    private array $nights = [];
    private array $weekends = [];
    private array $holidayShifts = [];
    /** nurse id => ISO week => shifts */
    private array $weekShifts = [];

    /** nurse id => position in the team, for rotating ties */
    private array $position = [];

    public function __construct(
        private readonly Ward $ward,
        private readonly Collection $team,
        private readonly RosterRules $rules,
    ) {
    }

    /**
     * Plan $from to $to inclusive and save it.
     *
     * @return array{created: int, shortfalls: array<int, array{date: string, shift: string, missing: int}>}
     */
    public function generate(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->startOfDay();
        $this->position = array_flip($this->team->pluck('id')->values()->all());

        $this->load($from, $to);

        $dates = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $dates[] = $date->copy();
        }

        $shortfalls = [];

        foreach ($dates as $dayIndex => $date) {
            foreach (['ON', 'AM', 'PM'] as $shift) {
                $missing = ($this->rules->minStaff[$shift] ?? 0) - $this->staffed($date, $shift);

                while ($missing > 0) {
                    $nurseId = $this->bestCandidate($date, $shift, $dayIndex);
                    if ($nurseId === null) {
                        $shortfalls[] = ['date' => $date->toDateString(), 'shift' => $shift, 'missing' => $missing];
                        break;
                    }
                    $this->assign($nurseId, $date, $shift);
                    $missing--;
                }
            }
        }

        $this->topUp($dates);

        return ['created' => $this->save($from, $to), 'shortfalls' => $shortfalls];
    }

    private function load(Carbon $from, Carbon $to): void
    {
        $nurseIds = $this->team->pluck('id')->all();
        $windowStart = $from->copy()->subDays(self::HISTORY_DAYS);
        $windowEnd = $to->copy()->addDays(self::LOOKAHEAD_DAYS);
        $periodStart = $from->toDateString();
        $periodEnd = $to->toDateString();

        // The team's entries in any ward, plus anyone else rostered on this ward
        $entries = NurseRosterEntry::where(fn ($query) => $query->whereIn('nurse_id', $nurseIds)->orWhere('ward_id', $this->ward->id))
            ->whereBetween('roster_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->get();

        foreach ($entries as $entry) {
            $day = $entry->roster_date->toDateString();
            $replaced = $entry->ward_id === $this->ward->id
                && $entry->source === NurseRosterEntry::SOURCE_AUTO
                && $day >= $periodStart && $day <= $periodEnd;

            if (!$replaced) {
                $this->fixed[$entry->nurse_id][$day] = $entry->shift;
                $this->fixedWard[$entry->nurse_id][$day] = $entry->ward_id;
            }
        }

        foreach (NurseLeave::whereIn('nurse_id', $nurseIds)->overlapping($windowStart, $windowEnd)->get() as $leave) {
            $day = ($leave->start_date->gt($windowStart) ? $leave->start_date : $windowStart)->copy();
            $last = ($leave->end_date->lt($windowEnd) ? $leave->end_date : $windowEnd)->copy();
            for (; $day->lte($last); $day->addDay()) {
                $this->leave[$leave->nurse_id][$day->toDateString()] = true;
            }
        }

        $this->holidays = PublicHoliday::whereBetween('holiday_date', [$windowStart->toDateString(), $windowEnd->toDateString()])
            ->pluck('holiday_date')
            ->mapWithKeys(fn ($date) => [$date->toDateString() => true])
            ->all();

        foreach ($this->fixed as $nurseId => $days) {
            foreach ($days as $day => $shift) {
                if ($day <= $periodEnd && in_array($shift, self::WORKING, true)) {
                    $this->count($nurseId, Carbon::parse($day), $shift);
                }
            }
        }
    }

    private function bestCandidate(Carbon $date, string $shift, int $dayIndex): ?int
    {
        $candidates = $this->team->pluck('id')
            ->filter(fn (int $nurseId) => $this->canWork($nurseId, $date, $shift, false));

        if ($candidates->isEmpty()) {
            return null;
        }

        // Nights in blocks: someone part-way through a run of nights carries on,
        // up to the preferred length, so nights are neither singles nor all
        // stacked on a few nurses
        if ($shift === 'ON') {
            $preferred = min(self::PREFERRED_NIGHT_RUN, $this->rules->maxConsecutiveNights);
            $continuing = $candidates->filter(function (int $nurseId) use ($date, $preferred) {
                $run = $this->streak($nurseId, $date, -1, 'ON');

                return $run > 0 && $run < $preferred;
            });
            if ($continuing->isNotEmpty()) {
                $candidates = $continuing;
            }
        }

        return $candidates->sortBy(fn (int $nurseId) => $this->score($nurseId, $date, $shift, $dayIndex))->first();
    }

    /**
     * Lower is better: whoever it is fairest to give this shift to.
     */
    private function score(int $nurseId, Carbon $date, string $shift, int $dayIndex): float
    {
        $previous = $this->shiftOn($nurseId, $date->copy()->subDay()->toDateString());
        $thisWeek = $this->weekCount($nurseId, $date);

        $score = $thisWeek * 10 + ($this->recentShifts[$nurseId] ?? 0);

        if ($thisWeek >= $this->rules->targetShiftsPerWeek) {
            $score += 60;
        }

        if ($shift === 'ON') {
            $score += ($this->nights[$nurseId] ?? 0) * 6;
            if (in_array($previous, ['AM', 'PM'], true)) {
                $score += 3; // better to start nights after a day off
            }
        } elseif ($previous === $shift) {
            $score -= 2; // a steady run of the same shift
        }

        // Weekends and holidays are measured against the team's average, so
        // whoever is behind on them is picked before whoever is ahead
        if ($date->isWeekend()) {
            $score += (($this->weekends[$nurseId] ?? 0) - $this->averageOf($this->weekends)) * 8;
        }

        if (isset($this->holidays[$date->toDateString()])) {
            $score += (($this->holidayShifts[$nurseId] ?? 0) - $this->averageOf($this->holidayShifts)) * 10;
        }

        if ($this->rules->avoidQuickReturn && $previous === 'PM' && $shift === 'AM') {
            $score += 40;
        }

        // Rotate ties, so the same nurses are not always picked first
        $size = max(1, count($this->position));

        return $score + (($this->position[$nurseId] + $dayIndex * 3) % $size) / ($size * 100);
    }

    /**
     * Strict is for topping up: no quick returns and nobody past their weekly
     * target. Minimum staffing may still use a quick return when there is no
     * one else.
     */
    private function canWork(int $nurseId, Carbon $date, string $shift, bool $strict): bool
    {
        $day = $date->toDateString();

        if (isset($this->leave[$nurseId][$day]) || $this->shiftOn($nurseId, $day) !== null) {
            return false;
        }

        $previous = $this->shiftOn($nurseId, $date->copy()->subDay()->toDateString());
        $next = $this->shiftOn($nurseId, $date->copy()->addDay()->toDateString());

        // After a night, the next day is another night or off
        if ($previous === 'ON' && $shift !== 'ON') {
            return false;
        }
        if ($shift === 'ON' && $next !== null && !in_array($next, ['ON', NurseRosterEntry::OFF], true)) {
            return false;
        }

        if ($this->streak($nurseId, $date, -1) + 1 + $this->streak($nurseId, $date, 1) > $this->rules->maxConsecutiveDays) {
            return false;
        }
        if ($shift === 'ON'
            && $this->streak($nurseId, $date, -1, 'ON') + 1 + $this->streak($nurseId, $date, 1, 'ON') > $this->rules->maxConsecutiveNights) {
            return false;
        }

        if ($strict) {
            $quickReturn = ($previous === 'PM' && $shift === 'AM') || ($shift === 'PM' && $next === 'AM');
            if ($this->rules->avoidQuickReturn && $quickReturn) {
                return false;
            }
            if ($this->weekCount($nurseId, $date) >= $this->rules->targetShiftsPerWeek) {
                return false;
            }
        }

        return true;
    }

    /**
     * Give nurses still short of their weekly target an extra day shift, on
     * whichever AM or PM is thinnest, until everyone is at target or there is
     * nowhere left to put them.
     */
    private function topUp(array $dates): void
    {
        $weeks = collect($dates)->groupBy(fn (Carbon $date) => $date->format('o-W'));

        foreach ($weeks as $week => $weekDates) {
            do {
                $added = false;

                $nurseIds = $this->team->pluck('id')
                    ->sortBy(fn (int $nurseId) => [$this->weekShifts[$nurseId][$week] ?? 0, $this->position[$nurseId]]);

                foreach ($nurseIds as $nurseId) {
                    if (($this->weekShifts[$nurseId][$week] ?? 0) >= $this->rules->targetShiftsPerWeek) {
                        continue;
                    }

                    $best = null;
                    foreach ($weekDates as $date) {
                        foreach (['AM', 'PM'] as $shift) {
                            $minimum = $this->rules->minStaff[$shift] ?? 0;
                            $staffed = $this->staffed($date, $shift);

                            if ($staffed >= $minimum + RosterRules::MAX_EXTRA_PER_DAY_SHIFT) {
                                continue;
                            }
                            if (!$this->canWork($nurseId, $date, $shift, true)) {
                                continue;
                            }

                            // Thinnest shift first, but a nurse who has had more weekends
                            // or holidays than others is steered to an ordinary weekday
                            $cost = $staffed / max(1, $minimum);
                            if ($date->isWeekend()) {
                                $cost += 0.4 * max(0, ($this->weekends[$nurseId] ?? 0) - $this->averageOf($this->weekends));
                            }
                            if (isset($this->holidays[$date->toDateString()])) {
                                $cost += 0.6 * max(0, ($this->holidayShifts[$nurseId] ?? 0) - $this->averageOf($this->holidayShifts));
                            }
                            if ($best === null || $cost < $best['cost']) {
                                $best = ['date' => $date, 'shift' => $shift, 'cost' => $cost];
                            }
                        }
                    }

                    if ($best) {
                        $this->assign($nurseId, $best['date'], $best['shift']);
                        $added = true;
                    }
                }
            } while ($added);
        }
    }

    private function assign(int $nurseId, Carbon $date, string $shift): void
    {
        $this->planned[$nurseId][$date->toDateString()] = $shift;
        $this->count($nurseId, $date, $shift);
    }

    private function count(int $nurseId, Carbon $date, string $shift): void
    {
        $week = $date->format('o-W');

        $this->recentShifts[$nurseId] = ($this->recentShifts[$nurseId] ?? 0) + 1;
        $this->weekShifts[$nurseId][$week] = ($this->weekShifts[$nurseId][$week] ?? 0) + 1;

        if ($shift === 'ON') {
            $this->nights[$nurseId] = ($this->nights[$nurseId] ?? 0) + 1;
        }
        if ($date->isWeekend()) {
            $this->weekends[$nurseId] = ($this->weekends[$nurseId] ?? 0) + 1;
        }
        if (isset($this->holidays[$date->toDateString()])) {
            $this->holidayShifts[$nurseId] = ($this->holidayShifts[$nurseId] ?? 0) + 1;
        }
    }

    /** A fairness counter's average across the whole team, counting nurses at zero. */
    private function averageOf(array $counts): float
    {
        $team = count($this->position);

        return $team ? array_sum(array_intersect_key($counts, $this->position)) / $team : 0.0;
    }

    private function shiftOn(int $nurseId, string $day): ?string
    {
        return $this->planned[$nurseId][$day] ?? $this->fixed[$nurseId][$day] ?? null;
    }

    private function weekCount(int $nurseId, Carbon $date): int
    {
        return $this->weekShifts[$nurseId][$date->format('o-W')] ?? 0;
    }

    /** Nurses on this ward's shift that day, whether planned now or already fixed. */
    private function staffed(Carbon $date, string $shift): int
    {
        $day = $date->toDateString();
        $count = 0;

        foreach ($this->planned as $days) {
            if (($days[$day] ?? null) === $shift) {
                $count++;
            }
        }

        foreach ($this->fixed as $nurseId => $days) {
            if (($days[$day] ?? null) === $shift && ($this->fixedWard[$nurseId][$day] ?? null) === $this->ward->id) {
                $count++;
            }
        }

        return $count;
    }

    /** Working days (or only nights) in a row beside $date, walking $step days at a time. */
    private function streak(int $nurseId, Carbon $date, int $step, ?string $only = null): int
    {
        $count = 0;
        $day = $date->copy()->addDays($step);

        while ($count < 31) {
            $shift = $this->shiftOn($nurseId, $day->toDateString());
            $working = $only ? $shift === $only : in_array($shift, self::WORKING, true);
            if (!$working) {
                break;
            }
            $count++;
            $day->addDays($step);
        }

        return $count;
    }

    private function save(Carbon $from, Carbon $to): int
    {
        return DB::transaction(function () use ($from, $to) {
            NurseRosterEntry::where('ward_id', $this->ward->id)
                ->where('source', NurseRosterEntry::SOURCE_AUTO)
                ->whereBetween('roster_date', [$from->toDateString(), $to->toDateString()])
                ->delete();

            $now = now();
            $rows = [];
            foreach ($this->planned as $nurseId => $days) {
                foreach ($days as $day => $shift) {
                    $rows[] = [
                        'ward_id' => $this->ward->id,
                        'nurse_id' => $nurseId,
                        'roster_date' => $day,
                        'shift' => $shift,
                        'source' => NurseRosterEntry::SOURCE_AUTO,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                NurseRosterEntry::insert($chunk);
            }

            return count($rows);
        });
    }
}
