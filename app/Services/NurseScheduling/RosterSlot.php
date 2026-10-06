<?php

namespace App\Services\NurseScheduling;

use App\Models\ShiftSetting;
use App\Models\WardScheduleAssignment;
use App\Services\ShiftHandover;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The roster slot on duty on a ward, and the one after it. A slot is a date
 * plus a shift code, the key the roster stores a shift under
 * (ward_schedule_assignments, nurse_roster_entries).
 *
 * A shift belongs to the date it starts. ON on a date is the night that starts
 * that evening, so after midnight the slot on duty is dated yesterday: at 02:00
 * on the 6th it is ON on the 5th. Every screen that asks who is on now reads it
 * from here, so they all agree (the nurse app's demo does the same, see
 * currentShift() in nurse_app/src/data/demoTime.js).
 *
 * Shift times are the ward's own, or the defaults (ShiftHandover::shifts).
 */
final class RosterSlot
{
    /**
     * The slot on duty at a time (now by default), or null in a gap between
     * the ward's shifts. Its label is the bare code: it is the one on now.
     *
     * @return array{date: string, code: string, name: ?string, time: string, label: string, starts_at: Carbon, ends_at: Carbon, shift: ShiftSetting}|null
     */
    public static function current(?int $wardId, ?CarbonInterface $at = null): ?array
    {
        $slot = self::on(ShiftHandover::shifts($wardId), self::moment($at));

        return $slot ? $slot + ['label' => $slot['code']] : null;
    }

    /**
     * The slot that takes over when the current one ends, dated the day it
     * starts: at 02:00 in the night this morning's AM, at 23:30 tomorrow's.
     * Between shifts, the next one to start.
     *
     * @return array{date: string, code: string, name: ?string, time: string, label: string, starts_at: Carbon, ends_at: Carbon, shift: ShiftSetting}|null
     */
    public static function next(?int $wardId, ?CarbonInterface $at = null): ?array
    {
        $at = self::moment($at);
        $shifts = ShiftHandover::shifts($wardId);
        $current = self::on($shifts, $at);
        $from = $current['ends_at'] ?? $at;
        $next = self::on($shifts, $from) ?? self::startingAfter($shifts, $from);

        return $next ? $next + ['label' => self::labelFor($next['code'], $next['date'], $current, $at)] : null;
    }

    /**
     * "AM", "AM tomorrow", "ON yesterday", "PM 19 Sep": a slot by its day,
     * counted from today. The shift on now counts from the day it started, so
     * after midnight the night on duty reads "ON" (not "ON yesterday") and
     * tonight's "ON tomorrow".
     */
    public static function label(string $code, string $date, ?CarbonInterface $now = null, ?int $wardId = null): string
    {
        $now = self::moment($now);

        return self::labelFor($code, $date, self::on(ShiftHandover::shifts($wardId), $now), $now);
    }

    /**
     * The bed assignments a nurse holds in the slot on duty, on each ward by
     * that ward's shift times: after midnight, last night's ON. On a ward
     * between shifts, all of the nurse's beds there today.
     *
     * @return Collection<int, WardScheduleAssignment>
     */
    public static function assignmentsOnDuty(int $nurseId, ?CarbonInterface $at = null): Collection
    {
        $at = self::moment($at);
        $today = $at->toDateString();
        $slots = [];

        return WardScheduleAssignment::where('nurse_id', $nurseId)
            ->whereIn('scheduled_date', [$today, $at->copy()->subDay()->toDateString()])
            ->get()
            ->filter(function (WardScheduleAssignment $assignment) use (&$slots, $at, $today) {
                $wardId = (int) $assignment->ward_id;
                if (!array_key_exists($wardId, $slots)) {
                    $slots[$wardId] = $wardId ? self::current($wardId, $at) : null;
                }
                $slot = $slots[$wardId];
                $date = $assignment->scheduled_date->toDateString();

                return $slot
                    ? $date === $slot['date'] && $assignment->shift === $slot['code']
                    : $date === $today;
            })
            ->values();
    }

    // -------------------------------------------------------------- helpers

    private static function moment(?CarbonInterface $at): Carbon
    {
        return $at ? Carbon::instance($at) : now();
    }

    /** The slot of the shift on at a time. */
    private static function on(Collection $shifts, Carbon $at): ?array
    {
        $time = $at->format('H:i:s');
        $shift = $shifts->first(fn (ShiftSetting $shift) => $shift->isTimeInShift($time));

        if (!$shift) {
            return null;
        }

        // Past midnight in a shift that began the evening before
        $date = $at->copy()->startOfDay();
        if (self::seconds($shift->start_time) > self::seconds($shift->end_time) && $time < self::seconds($shift->start_time)) {
            $date->subDay();
        }

        return self::slot($shift, $date);
    }

    /** The slot of the shift that starts soonest after a time. */
    private static function startingAfter(Collection $shifts, Carbon $after): ?array
    {
        return $shifts
            ->map(function (ShiftSetting $shift) use ($after) {
                $start = $after->copy()->setTimeFromTimeString(self::seconds($shift->start_time));

                return self::slot($shift, ($start->lessThanOrEqualTo($after) ? $start->addDay() : $start)->startOfDay());
            })
            ->sortBy(fn (array $slot) => $slot['starts_at']->getTimestamp())
            ->first();
    }

    private static function slot(ShiftSetting $shift, Carbon $date): array
    {
        $start = substr(self::seconds($shift->start_time), 0, 5);
        $end = substr(self::seconds($shift->end_time), 0, 5);
        $startsAt = $date->copy()->setTimeFromTimeString($start);
        $endsAt = $date->copy()->setTimeFromTimeString($end);
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt->addDay();
        }

        return [
            'date' => $date->toDateString(),
            'code' => $shift->shift_code,
            'name' => $shift->shift_name,
            'time' => $start . ' - ' . $end,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'shift' => $shift,
        ];
    }

    private static function labelFor(string $code, string $date, ?array $current, Carbon $now): string
    {
        $from = $current && $current['code'] === $code ? Carbon::parse($current['date']) : $now->copy()->startOfDay();
        $day = Carbon::parse($date)->startOfDay();
        $offset = (int) round(($day->getTimestamp() - $from->getTimestamp()) / 86400);

        return $code . match ($offset) {
            0 => '',
            1 => ' tomorrow',
            -1 => ' yesterday',
            default => ' ' . $day->format('j M'),
        };
    }

    /** A shift time as "HH:MM:SS" ("23:00" from the defaults, "23:00:00" from the database). */
    private static function seconds(?string $time): string
    {
        $time = (string) $time;

        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
