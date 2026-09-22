<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\ShiftSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The roster slot looking after a patient's bed now, and the one taking over
 * next, each with the nurse the ward roster puts on the bed. A slot is a date
 * plus a shift code, the key the roster (Ward Schedule) is stored under.
 *
 * "Now" follows the rule the bed boxes use: today's entry for the shift the
 * clock is in. "Next" is whichever shift is on the moment the current one
 * ends, on the date it ends. So at 02:00 in the night shift the next slot is
 * this morning's AM, while at 23:30 it is tomorrow's.
 *
 * The nurses come from PatientRoster, which covers today and tomorrow; the
 * next slot never falls later than that.
 */
class ShiftHandover
{
    /**
     * @return array{current: ?array, next: ?array}
     */
    public static function slotsFor(Patient $patient, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $shifts = self::shifts($patient->ward_id);
        $current = self::shiftAt($shifts, $now);

        if ($current) {
            $nextAt = $now->copy()->setTimeFromTimeString((string) $current->end_time);
            if ($nextAt->lessThanOrEqualTo($now)) {
                $nextAt->addDay();
            }
        } else {
            // In a gap between shifts: next is whichever starts soonest
            $nextAt = $shifts
                ->map(function (ShiftSetting $shift) use ($now) {
                    $start = $now->copy()->setTimeFromTimeString((string) $shift->start_time);

                    return $start->lessThanOrEqualTo($now) ? $start->addDay() : $start;
                })
                ->sort()
                ->first();
        }

        $next = $nextAt ? self::shiftAt($shifts, $nextAt) : null;
        $roster = PatientRoster::forPatient($patient, $now);

        return [
            'current' => $current ? self::slot($current, $now, $roster, $now) : null,
            'next' => $next ? self::slot($next, $nextAt, $roster, $now) : null,
        ];
    }

    /** "AM", "AM tomorrow", "ON yesterday", "PM 19 Sep". */
    public static function label(string $code, ?string $date, ?CarbonInterface $now = null): string
    {
        if ($date === null) {
            return $code;
        }

        $today = ($now ? Carbon::instance($now) : now())->copy()->startOfDay();
        $day = Carbon::parse($date)->startOfDay();
        $offset = (int) round(($day->getTimestamp() - $today->getTimestamp()) / 86400);

        return $code . match ($offset) {
            0 => '',
            1 => ' tomorrow',
            -1 => ' yesterday',
            default => ' ' . $day->format('j M'),
        };
    }

    /** The ward's own shift times, or the defaults the dashboard would create. */
    public static function shifts(?int $wardId): Collection
    {
        $shifts = $wardId
            ? ShiftSetting::where('ward_id', $wardId)->where('is_active', true)->orderBy('display_order')->get()
            : collect();

        return $shifts->isNotEmpty()
            ? $shifts
            : collect(ShiftSetting::getDefaults())->map(fn (array $shift) => new ShiftSetting($shift));
    }

    private static function shiftAt(Collection $shifts, Carbon $at): ?ShiftSetting
    {
        $time = $at->format('H:i:s');

        return $shifts->first(fn (ShiftSetting $shift) => $shift->isTimeInShift($time));
    }

    private static function slot(ShiftSetting $shift, Carbon $at, array $roster, Carbon $now): array
    {
        $date = $at->toDateString();
        $day = collect($roster['days'])->first(fn (array $day) => $day['date']->toDateString() === $date);
        $entry = $day ? collect($day['shifts'])->firstWhere('code', $shift->shift_code) : null;

        return [
            'date' => $date,
            'code' => $shift->shift_code,
            'name' => $shift->shift_name,
            'time' => substr((string) $shift->start_time, 0, 5) . ' - ' . substr((string) $shift->end_time, 0, 5),
            'label' => self::label($shift->shift_code, $date, $now),
            'nurse' => $entry['nurse'] ?? null,
        ];
    }
}
