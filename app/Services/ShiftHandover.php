<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\ShiftSetting;
use App\Services\NurseScheduling\RosterSlot;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The roster slot looking after a patient's bed now, and the one taking over
 * next, each with the nurse the ward roster puts on the bed. A slot is a date
 * plus a shift code, the key the roster (Ward Schedule) is stored under.
 *
 * "Now" is the slot on duty (RosterSlot): a shift belongs to the date it
 * starts, so after midnight the night on duty is last night's ON. "Next" is
 * whichever shift is on the moment the current one ends, on the date it
 * starts. So at 02:00 in the night shift the next slot is this morning's AM,
 * while at 23:30 it is tomorrow's.
 *
 * The nurses come from PatientRoster, which covers the day the slot on duty
 * started and the day after; the next slot never falls later than that.
 */
class ShiftHandover
{
    /**
     * @return array{current: ?array, next: ?array}
     */
    public static function slotsFor(Patient $patient, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $current = RosterSlot::current($patient->ward_id, $now);
        $next = RosterSlot::next($patient->ward_id, $now);
        $roster = PatientRoster::forPatient($patient, $now);

        return [
            'current' => $current ? self::slot($current, $roster) : null,
            'next' => $next ? self::slot($next, $roster) : null,
        ];
    }

    /**
     * "AM", "AM tomorrow", "ON yesterday", "PM 19 Sep". The night on duty
     * reads "ON" after midnight too (see RosterSlot::label).
     */
    public static function label(string $code, ?string $date, ?CarbonInterface $now = null): string
    {
        return $date === null ? $code : RosterSlot::label($code, $date, $now);
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

    /** A RosterSlot slot with the nurse the roster puts on the patient's bed. */
    private static function slot(array $slot, array $roster): array
    {
        $day = collect($roster['days'])->first(fn (array $day) => $day['date']->toDateString() === $slot['date']);
        $entry = $day ? collect($day['shifts'])->firstWhere('code', $slot['code']) : null;

        return [
            'date' => $slot['date'],
            'code' => $slot['code'],
            'name' => $slot['name'],
            'time' => $slot['time'],
            'label' => $slot['label'],
            'nurse' => $entry['nurse'] ?? null,
        ];
    }
}
