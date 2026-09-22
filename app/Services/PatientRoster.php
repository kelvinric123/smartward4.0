<?php

namespace App\Services;

use App\Models\Bed;
use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Models\WardScheduleAssignment;
use App\Models\WardSpecialDuty;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The nurses the ward roster puts on a patient's bed: for each shift of today
 * and tomorrow, the rostered nurse with anyone tagging along, and that shift's
 * team leader. Read-only here; nurses are assigned in the roster (Ward Schedule).
 *
 * "On duty now" is today's entry for the current shift, the same rule the bed
 * boxes and the bedside patient app use.
 */
class PatientRoster
{
    /** Days shown, starting today. */
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
        $shifts = ShiftSetting::where('ward_id', $bed->ward_id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();
        if ($shifts->isEmpty()) {
            $shifts = collect(ShiftSetting::getDefaults())->map(fn (array $shift) => new ShiftSetting($shift));
        }
        $currentCode = ShiftSetting::getCurrentShift($bed->ward_id, $now)?->shift_code;

        $dates = collect(range(0, self::DAYS - 1))->map(fn (int $offset) => $now->copy()->startOfDay()->addDays($offset));
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

        $days = $dates->map(function (Carbon $date, int $offset) use ($shifts, $assignments, $teamLeaders, $currentCode) {
            $day = $date->toDateString();

            return [
                'date' => $date,
                'label' => $offset === 0 ? 'Today' : ($offset === 1 ? 'Tomorrow' : $date->format('l')),
                'shifts' => $shifts->map(fn (ShiftSetting $shift) => [
                    'code' => $shift->shift_code,
                    'name' => $shift->shift_name,
                    'time' => substr((string) $shift->start_time, 0, 5) . ' - ' . substr((string) $shift->end_time, 0, 5),
                    'nurse' => $assignments->get($day . '|' . $shift->shift_code)?->nurse,
                    'team_leader' => $teamLeaders->get($day . '|' . $shift->shift_code)?->nurse,
                    'is_current' => $offset === 0 && $shift->shift_code === $currentCode,
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
