<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use App\Models\NurseLeave;
use App\Models\NurseRosterEntry;
use App\Models\PublicHoliday;
use App\Models\Ward;
use App\Models\WardScheduleAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Everything the roster grid shows for one ward and one week: each nurse's
 * day, the staffing each shift reaches against its minimum, and each nurse's
 * totals for the week.
 *
 * A day comes from the nurse's roster entry, or, if there is none, from beds
 * assigned to them on the Ward Schedule page, so a roster made there still
 * shows here. Leave is shown on top, and anything that clashes with it is
 * flagged.
 */
final class RosterBoard
{
    public const DAYS = 7;

    public static function build(Ward $ward, CarbonInterface $weekStart, RosterRules $rules): array
    {
        $dates = collect(range(0, self::DAYS - 1))->map(fn (int $offset) => Carbon::instance($weekStart)->copy()->addDays($offset));
        $from = $dates->first()->toDateString();
        $to = $dates->last()->toDateString();
        $shifts = WardShifts::forWard($ward->id);

        ['nurses' => $team, 'source' => $teamSource] = WardTeam::for($ward);

        $entries = NurseRosterEntry::whereBetween('roster_date', [$from, $to])
            ->where(fn ($query) => $query->where('ward_id', $ward->id)->orWhereIn('nurse_id', $team->pluck('id')))
            ->with('ward:id,ward_name')
            ->get();

        $bedAssignments = WardScheduleAssignment::where('ward_id', $ward->id)
            ->whereBetween('scheduled_date', [$from, $to])
            ->get();

        // Someone rostered on this ward from outside the team still gets a row
        $outsiders = $entries->where('ward_id', $ward->id)->pluck('nurse_id')
            ->merge($bedAssignments->pluck('nurse_id'))
            ->unique()
            ->diff($team->pluck('id'));
        if ($outsiders->isNotEmpty()) {
            $team = $team->concat(Nurse::whereIn('id', $outsiders)->orderBy('name')->get())->values();
        }

        $entryFor = $entries->groupBy('nurse_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (NurseRosterEntry $entry) => $entry->roster_date->toDateString()));
        $bedsFor = $bedAssignments->groupBy('nurse_id')
            ->map(fn (Collection $rows) => $rows->groupBy(fn (WardScheduleAssignment $row) => $row->scheduled_date->toDateString()));
        $leaveFor = NurseLeave::whereIn('nurse_id', $team->pluck('id'))
            ->overlapping($dates->first(), $dates->last())
            ->get()
            ->groupBy('nurse_id');
        $holidays = PublicHoliday::whereBetween('holiday_date', [$from, $to])->get()
            ->mapWithKeys(fn (PublicHoliday $holiday) => [$holiday->holiday_date->toDateString() => $holiday->name])
            ->all();

        $staffing = [];
        foreach ($dates as $date) {
            foreach (WardShifts::CODES as $code) {
                $staffing[$date->toDateString()][$code] = ['count' => 0, 'min' => $rules->minStaff[$code] ?? 0];
            }
        }

        $cells = [];
        $stats = [];

        foreach ($team as $nurse) {
            $stat = ['shifts' => 0, 'AM' => 0, 'PM' => 0, 'ON' => 0, 'weekend' => 0, 'holiday' => 0, 'hours' => 0.0, 'leave' => 0];

            foreach ($dates as $date) {
                $day = $date->toDateString();
                $entry = $entryFor->get($nurse->id)?->get($day);
                $beds = $bedsFor->get($nurse->id)?->get($day) ?? collect();
                $leave = ($leaveFor->get($nurse->id) ?? collect())->first(fn (NurseLeave $leave) => $leave->covers($date));

                $cell = [
                    'shift' => null,
                    'source' => null,
                    'leave' => $leave,
                    'other_ward' => null,
                    'beds' => $beds->count(),
                    'conflict' => null,
                ];

                if ($entry) {
                    $cell['shift'] = $entry->shift;
                    $cell['source'] = $entry->source;
                    if ($entry->ward_id !== $ward->id) {
                        $cell['other_ward'] = $entry->ward->ward_name ?? 'another ward';
                    }
                } elseif ($beds->isNotEmpty()) {
                    $cell['shift'] = collect(WardShifts::CODES)->first(fn (string $code) => $beds->contains('shift', $code));
                    $cell['source'] = 'schedule';
                }

                $working = in_array($cell['shift'], WardShifts::CODES, true);

                if ($leave && $working) {
                    $cell['conflict'] = 'Rostered during ' . strtolower($leave->label());
                } elseif ($entry && $working && $beds->isNotEmpty() && $beds->pluck('shift')->unique()->diff([$entry->shift])->isNotEmpty()) {
                    $cell['conflict'] = 'Has beds on the ' . $beds->pluck('shift')->unique()->implode('/') . ' shift in the Ward Schedule';
                }

                if ($working) {
                    if (!$cell['other_ward']) {
                        $staffing[$day][$cell['shift']]['count']++;
                    }
                    $stat['shifts']++;
                    $stat[$cell['shift']]++;
                    $stat['hours'] += $shifts[$cell['shift']]['hours'];
                    $stat['weekend'] += $date->isWeekend() ? 1 : 0;
                    $stat['holiday'] += isset($holidays[$day]) ? 1 : 0;
                }
                if ($leave) {
                    $stat['leave']++;
                }

                $cells[$nurse->id][$day] = $cell;
            }

            $stats[$nurse->id] = $stat;
        }

        return [
            'team' => $team,
            'teamSource' => $teamSource,
            'dates' => $dates,
            'shifts' => $shifts,
            'cells' => $cells,
            'stats' => $stats,
            'staffing' => $staffing,
            'holidays' => $holidays,
            'autoCount' => $entries->where('ward_id', $ward->id)->where('source', NurseRosterEntry::SOURCE_AUTO)->count(),
            'scheduleOnly' => collect($cells)->flatten(1)->where('source', 'schedule')->count(),
        ];
    }

    /** The nurses working a shift on this ward that day, from the grid. */
    public static function onShift(array $board, string $day, string $code): Collection
    {
        return $board['team']->filter(function (Nurse $nurse) use ($board, $day, $code) {
            $cell = $board['cells'][$nurse->id][$day] ?? null;

            return $cell && $cell['shift'] === $code && !$cell['other_ward'];
        })->values();
    }
}
