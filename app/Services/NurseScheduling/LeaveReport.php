<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\NurseRosterRequest;
use App\Models\PublicHoliday;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leave and public holidays on a ward over a month, a quarter or a year, for
 * the leave and holiday printout: every leave of the ward's nurses in the
 * period, days by nurse and by type, who worked each public holiday and who
 * was away, how many nurses were free each day, and the leave and swap
 * requests made from the nurse app.
 *
 * The team is the ward's (WardTeam) plus anyone rostered on the ward in the
 * period, as the roster grid has it. Who is on duty each day is read the way
 * RosterBoard reads it, a roster entry on this ward or else beds held in the
 * Ward Schedule, but straight from the tables: a year is 53 roster weeks.
 *
 * A leave note can hold health details, so notes (and the notes on requests)
 * are in here only when asked for. Plain arrays out, no models.
 */
final class LeaveReport
{
    public const PERIODS = ['month', 'quarter', 'year'];

    /** A day with this share of the team or more on leave is flagged on the calendar. */
    public const BUSY_PERCENT = 20;

    /**
     * The first and last day of the month, the quarter or the year that
     * $month falls in.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function period(CarbonInterface $month, string $period): array
    {
        $month = Carbon::instance($month)->copy()->startOfMonth()->startOfDay();

        return match ($period) {
            'quarter' => [$month->copy()->firstOfQuarter(), $month->copy()->lastOfQuarter()->startOfDay()],
            'year' => [$month->copy()->startOfYear(), $month->copy()->endOfYear()->startOfDay()],
            default => [$month->copy(), $month->copy()->endOfMonth()->startOfDay()],
        };
    }

    /**
     * Options: notes (bool, default false) puts the leave notes and the
     * request notes in.
     */
    public static function build(Ward $ward, CarbonInterface $from, CarbonInterface $to, array $options = []): array
    {
        $from = Carbon::instance($from)->copy()->startOfDay();
        $to = Carbon::instance($to)->copy()->startOfDay();
        $notes = (bool) ($options['notes'] ?? false);

        $team = self::team($ward, $from, $to);
        $names = $team->pluck('name', 'id');
        $rostered = self::rostered($ward, $from->toDateString(), $to->toDateString());
        $holidays = PublicHoliday::whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('holiday_date')
            ->get()
            ->mapWithKeys(fn (PublicHoliday $holiday) => [$holiday->holiday_date->toDateString() => $holiday->name])
            ->all();

        $leaves = NurseLeave::with('createdBy:id,name')
            ->whereIn('nurse_id', $team->pluck('id'))
            ->overlapping($from, $to)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        // Each nurse's leave code on each day of the period
        $away = [];
        $entries = [];
        foreach ($leaves as $leave) {
            $start = $leave->start_date->max($from)->copy();
            $end = $leave->end_date->min($to)->copy();
            $inPeriod = 0;
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $away[$date->toDateString()][$leave->nurse_id] ??= ['type' => $leave->type, 'code' => $leave->code(), 'label' => $leave->label()];
                $inPeriod++;
            }

            $entries[] = [
                'nurse_id' => $leave->nurse_id,
                'nurse' => $names[$leave->nurse_id] ?? 'Nurse',
                'type' => $leave->type,
                'code' => $leave->code(),
                'label' => $leave->label(),
                'start' => $leave->start_date->copy(),
                'end' => $leave->end_date->copy(),
                'days' => $inPeriod,
                'total_days' => $leave->days(),
                'clipped' => $inPeriod !== $leave->days(),
                'note' => $notes ? $leave->note : null,
                'booked_by' => $leave->createdBy?->name,
                'booked_at' => $leave->created_at?->copy(),
            ];
        }

        // Days away per nurse and type, from the day map: two leaves booked over the same day
        // (nothing stops it) count that day once, under the leave that started first, as the calendar does
        $types = self::types(collect($entries)->pluck('type'));
        $daysAway = [];
        foreach ($away as $onDay) {
            foreach ($onDay as $nurseId => $leave) {
                $daysAway[$nurseId][$leave['type']] = ($daysAway[$nurseId][$leave['type']] ?? 0) + 1;
            }
        }
        $byType = fn (array $days) => collect($types)->mapWithKeys(fn (array $type, string $key) => [$key => $days[$key] ?? 0])->all();

        $nurses = $team->map(fn (Nurse $nurse) => [
            'id' => $nurse->id,
            'name' => $nurse->name,
            'designation' => $nurse->designation,
            'by_type' => $byType($daysAway[$nurse->id] ?? []),
            'days' => array_sum($daysAway[$nurse->id] ?? []),
            'entries' => collect($entries)->where('nurse_id', $nurse->id)->count(),
        ])->values()->all();

        $size = $team->count();
        $calendar = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $day = $date->toDateString();
            $onLeave = $away[$day] ?? [];
            $onDuty = array_count_values($rostered[$day] ?? []);
            $calendar[$date->format('Y-m')]['label'] ??= $date->format('F Y');
            $calendar[$date->format('Y-m')]['days'][] = [
                'date' => $date->copy(),
                'day' => $day,
                'weekend' => $date->isWeekend(),
                'holiday' => $holidays[$day] ?? null,
                'team' => $size,
                'on_leave' => count($onLeave),
                'codes' => array_count_values(array_column($onLeave, 'type')),
                'rostered' => collect(WardShifts::CODES)->mapWithKeys(fn (string $code) => [$code => $onDuty[$code] ?? 0])->all(),
                'busy' => $size > 0 && count($onLeave) * 100 >= self::BUSY_PERCENT * $size,
            ];
        }

        $holidayRows = collect($holidays)->map(fn (string $name, string $day) => [
            'date' => Carbon::parse($day),
            'name' => $name,
            'worked' => collect($rostered[$day] ?? [])
                ->map(fn (string $code, int $nurseId) => ['name' => $names[$nurseId] ?? 'Nurse', 'shift' => $code])
                ->sortBy(fn (array $row) => [array_search($row['shift'], WardShifts::CODES, true), mb_strtolower($row['name'])])
                ->values()
                ->all(),
            'on_leave' => collect($away[$day] ?? [])
                ->map(fn (array $leave, int $nurseId) => ['name' => $names[$nurseId] ?? 'Nurse', 'code' => $leave['code'], 'label' => $leave['label']])
                ->sortBy(fn (array $row) => mb_strtolower($row['name']))
                ->values()
                ->all(),
        ])->values()->all();

        $totalDays = array_sum(array_map('array_sum', $daysAway));
        $totalByType = [];
        foreach ($daysAway as $perType) {
            foreach ($perType as $type => $count) {
                $totalByType[$type] = ($totalByType[$type] ?? 0) + $count;
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'months' => count($calendar),
            'team' => $size,
            'busy_percent' => self::BUSY_PERCENT,
            'types' => $types,
            'entries' => $entries,
            'nurses' => $nurses,
            'calendar' => array_values($calendar),
            'holidays' => $holidayRows,
            'requests' => self::requests($ward, $from, $to, $notes),
            'notes' => $notes,
            'totals' => [
                'days' => $totalDays,
                'by_type' => $byType($totalByType),
                'entries' => count($entries),
                'nurses_with_leave' => collect($entries)->pluck('nurse_id')->unique()->count(),
                'average_days' => $size ? round($totalDays / $size, 1) : null,
                'holidays' => count($holidayRows),
                'holiday_shifts' => collect($holidayRows)->sum(fn (array $holiday) => count($holiday['worked'])),
                'busy_days' => collect($calendar)->flatMap(fn (array $month) => $month['days'])->where('busy', true)->count(),
            ],
        ];
    }

    /**
     * The ward's team (WardTeam) and anyone rostered on the ward in the
     * period, by roster entry or beds held, as the roster grid adds them;
     * sorted by name.
     */
    public static function team(Ward $ward, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $team = WardTeam::for($ward)['nurses'];
        $rostered = NurseRosterEntry::where('ward_id', $ward->id)
            ->whereBetween('roster_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('nurse_id')
            ->merge(WardScheduleAssignment::where('ward_id', $ward->id)
                ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
                ->pluck('nurse_id'))
            ->unique()
            ->diff($team->pluck('id'));

        if ($rostered->isNotEmpty()) {
            $team = $team->concat(Nurse::whereIn('id', $rostered)->get());
        }

        return $team->unique('id')->sortBy(fn (Nurse $nurse) => mb_strtolower($nurse->name))->values();
    }

    /**
     * Who is on duty on this ward each day and on which shift, [date][nurse
     * id] => code: a roster entry on this ward, or for a nurse with no roster
     * entry that day, beds held in the Ward Schedule (the first of AM, PM and
     * ON), as RosterBoard reads a day.
     */
    private static function rostered(Ward $ward, string $from, string $to): array
    {
        $beds = WardScheduleAssignment::where('ward_id', $ward->id)
            ->whereBetween('scheduled_date', [$from, $to])
            ->toBase()
            ->get(['nurse_id', 'scheduled_date', 'shift']);
        $entries = NurseRosterEntry::whereBetween('roster_date', [$from, $to])
            ->where(fn ($query) => $query->where('ward_id', $ward->id)->orWhereIn('nurse_id', $beds->pluck('nurse_id')->unique()))
            ->toBase()
            ->get(['nurse_id', 'ward_id', 'roster_date', 'shift']);

        $onDuty = [];
        $hasEntry = [];
        foreach ($entries as $entry) {
            $day = substr((string) $entry->roster_date, 0, 10);
            $hasEntry[$day][$entry->nurse_id] = true;
            if ((int) $entry->ward_id === $ward->id && in_array($entry->shift, WardShifts::CODES, true)) {
                $onDuty[$day][$entry->nurse_id] = $entry->shift;
            }
        }

        $held = $beds->groupBy(fn (object $bed) => substr((string) $bed->scheduled_date, 0, 10) . '|' . $bed->nurse_id);
        foreach ($held as $key => $rows) {
            [$day, $nurseId] = explode('|', $key);
            if (!isset($hasEntry[$day][(int) $nurseId])) {
                $code = collect(WardShifts::CODES)->first(fn (string $code) => $rows->contains('shift', $code));
                if ($code) {
                    $onDuty[$day][(int) $nurseId] = $code;
                }
            }
        }

        return $onDuty;
    }

    /** The leave types in NurseLeave's order, then any other type found in the period. */
    private static function types(Collection $found): array
    {
        $types = [];
        foreach (NurseLeave::TYPES as $key => $type) {
            $types[$key] = ['code' => $type['code'], 'label' => $type['label']];
        }
        foreach ($found->unique() as $key) {
            $types[$key] ??= ['code' => (new NurseLeave(['type' => $key]))->code(), 'label' => (new NurseLeave(['type' => $key]))->label()];
        }

        return $types;
    }

    /**
     * Requests from the nurse app for this ward in the period: leave that
     * overlaps it, and swaps of a shift within it. Their notes only when
     * notes are asked for.
     */
    private static function requests(Ward $ward, Carbon $from, Carbon $to, bool $notes): array
    {
        return NurseRosterRequest::with(['nurse:id,name', 'colleague:id,name'])
            ->where('ward_id', $ward->id)
            ->where(fn ($query) => $query
                ->where(fn ($leave) => $leave->where('type', NurseRosterRequest::TYPE_LEAVE)
                    ->where('start_date', '<=', $to->toDateString())
                    ->where('end_date', '>=', $from->toDateString()))
                ->orWhere(fn ($swap) => $swap->where('type', NurseRosterRequest::TYPE_SWAP)
                    ->whereBetween('shift_date', [$from->toDateString(), $to->toDateString()])))
            ->orderByRaw('COALESCE(start_date, shift_date)')
            ->orderBy('id')
            ->get()
            ->map(fn (NurseRosterRequest $request) => [
                'nurse' => $request->nurse?->name ?? 'Nurse',
                'type' => $request->isSwap() ? 'Swap' : 'Leave',
                'summary' => $request->summary(),
                'status' => $request->status,
                'status_label' => $request->statusLabel(),
                'asked_at' => $request->created_at?->copy(),
                'decided_by' => $request->decided_by_name,
                'decided_at' => $request->decided_at?->copy(),
                'note' => $notes ? $request->note : null,
                'decision_note' => $notes ? $request->decision_note : null,
            ])
            ->all();
    }
}
