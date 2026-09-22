<?php

namespace App\Services\NurseScheduling;

use App\Models\ShiftSetting;
use App\Services\ShiftHandover;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The three shifts of a ward's day (AM, PM and the ON night shift) with their
 * times and lengths, from the ward's shift settings or the defaults.
 */
final class WardShifts
{
    public const CODES = ['AM', 'PM', 'ON'];

    /**
     * @return array<string, array{code: string, name: string, start: string, end: string, time: string, hours: float}>
     */
    public static function forWard(?int $wardId): array
    {
        $settings = ShiftHandover::shifts($wardId)->keyBy('shift_code');
        $defaults = collect(ShiftSetting::getDefaults())->keyBy('shift_code');

        $shifts = [];
        foreach (self::CODES as $code) {
            $shift = $settings->get($code) ?? new ShiftSetting($defaults->get($code));
            $start = substr((string) $shift->start_time, 0, 5);
            $end = substr((string) $shift->end_time, 0, 5);

            $shifts[$code] = [
                'code' => $code,
                'name' => $shift->shift_name,
                'start' => $start,
                'end' => $end,
                'time' => $start . ' - ' . $end,
                'hours' => self::hoursBetween($start, $end),
            ];
        }

        return $shifts;
    }

    /** The shift the clock is in, by the ward's shift times. */
    public static function currentCode(array $shifts, ?CarbonInterface $now = null): ?string
    {
        $time = ($now ? Carbon::instance($now) : now())->format('H:i');

        foreach ($shifts as $code => $shift) {
            $inShift = $shift['start'] < $shift['end']
                ? $time >= $shift['start'] && $time < $shift['end']
                : $time >= $shift['start'] || $time < $shift['end'];

            if ($inShift) {
                return $code;
            }
        }

        return null;
    }

    /**
     * Whether a shift on a date is already over, so assigning beds for it would
     * only rewrite the past. The ON shift on a date runs into the next morning.
     */
    public static function hasEnded(string $date, array $shift, ?CarbonInterface $now = null): bool
    {
        $now = $now ? Carbon::instance($now) : now();
        $end = Carbon::parse($date . ' ' . $shift['start'])->addMinutes((int) round($shift['hours'] * 60));

        return $end->lessThanOrEqualTo($now);
    }

    private static function hoursBetween(string $start, string $end): float
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));
        $minutes = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);

        return round(($minutes <= 0 ? $minutes + 1440 : $minutes) / 60, 2);
    }
}
