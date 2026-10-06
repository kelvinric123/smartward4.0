<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Models\WardScheduleAssignment;
use App\Models\WardSpecialDuty;
use App\Services\NurseScheduling\RosterSlot;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The nurses the ward roster puts on a patient's bed: for each shift of two
 * days, the rostered nurse with anyone tagging along, and that shift's team
 * leader. Read-only here; nurses are assigned in the roster (Ward Schedule).
 *
 * "On duty now" is the slot on duty (RosterSlot), the same one the bed boxes
 * and the bedside patient app show. The days start on the day that slot
 * started: today, or after midnight in the night shift yesterday, whose ON is
 * the night still on.
 */
class PatientRoster
{
    /** Days shown, starting on the day of the slot on duty. */
    public const DAYS = 2;

    /**
     * @return array{bed: ?Bed, days: array, current: ?array}
     */
    public static function forPatient(Patient $patient, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();

        $bed = $patient->ward_id && $patient->bed_number
            ? Bed::where('ward_id', $patient->ward_id)->where('bed_number', $patient->bed_number)->first()
            : null;

        if (!$bed) {
            return ['bed' => null, 'days' => [], 'current' => null];
        }

        // The ward's own shift times, or the defaults the dashboard would create
        $shifts = ShiftHandover::shifts($bed->ward_id);
        $current = RosterSlot::current($bed->ward_id, $now);
        $first = $current ? Carbon::parse($current['date']) : $now->copy()->startOfDay();
        $today = $now->copy()->startOfDay();

        $dates = collect(range(0, self::DAYS - 1))->map(fn (int $offset) => $first->copy()->addDays($offset));
        $between = [$dates->first()->toDateString(), $dates->last()->toDateString()];

        $assignments = WardScheduleAssignment::where('bed_id', $bed->id)
            ->whereBetween('scheduled_date', $between)
            ->with('nurse.taggingNurses')
            ->get()
            ->keyBy(fn (WardScheduleAssignment $assignment) => $assignment->scheduled_date->toDateString() . '|' . $assignment->shift);

        $teamLeaders = WardSpecialDuty::where('ward_id', $bed->ward_id)
            ->where('duty_type', 'team_leader')
            ->whereBetween('date', $between)
            ->with('nurse')
            ->get()
            ->keyBy(fn (WardSpecialDuty $duty) => $duty->date->toDateString() . '|' . $duty->shift);

        $days = $dates->map(function (Carbon $date) use ($shifts, $assignments, $teamLeaders, $current, $today) {
            $day = $date->toDateString();

            return [
                'date' => $date,
                'label' => match ((int) round(($date->getTimestamp() - $today->getTimestamp()) / 86400)) {
                    -1 => 'Yesterday',
                    0 => 'Today',
                    1 => 'Tomorrow',
                    default => $date->format('l'),
                },
                'shifts' => $shifts->map(fn (ShiftSetting $shift) => [
                    'code' => $shift->shift_code,
                    'name' => $shift->shift_name,
                    'time' => substr((string) $shift->start_time, 0, 5) . ' - ' . substr((string) $shift->end_time, 0, 5),
                    'nurse' => $assignments->get($day . '|' . $shift->shift_code)?->nurse,
                    'team_leader' => $teamLeaders->get($day . '|' . $shift->shift_code)?->nurse,
                    'is_current' => $current !== null && $day === $current['date'] && $shift->shift_code === $current['code'],
                ])->values()->all(),
            ];
        })->all();

        return [
            'bed' => $bed,
            'days' => $days,
            'current' => collect($days[0]['shifts'])->firstWhere('is_current', true),
        ];
    }
}
