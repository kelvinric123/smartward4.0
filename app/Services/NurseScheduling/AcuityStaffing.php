<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use Illuminate\Support\Collection;

/**
 * Acuity-based staffing: the nurses each shift needs for the patients on the
 * ward now, from their care levels (PatientAcuity) and the ward's hours of care
 * per level (AcuitySettings), set against the minimum in the staffing rules and
 * the nurses on the roster.
 *
 *   care hours in 24 h  = sum over patients of the hours for their level
 *   care hours on shift = care hours in 24 h x the shift's share
 *   nurses on shift     = care hours on shift / the shift's length, rounded up
 *
 * A fixed minimum or ratio does not follow the patients' changing needs
 * (Bolton et al., 2001, as cited in ANA, 2017, p. 11; AcuityReferences::RATIOS_CITE).
 * So the minimum stays the floor, a SmartWard rule, and the recommendation is
 * shown beside it, never in place of it. It is worked out from the patients on the
 * ward now: for later days of the week it is a projection, and for earlier days
 * a comparison only.
 */
final class AcuityStaffing
{
    /** A part of a nurse this small is not rounded up into a whole nurse. */
    public const ROUNDING_SLACK = 0.05;

    /** Designations that are not registered nurses: supernumerary students and care assistants. */
    public const NOT_REGISTERED = ['student nurse', 'health care assistant', 'patient care assistant'];

    /**
     * Per shift: the care hours it needs, its length, the exact and rounded-up
     * nurses, and the patients each nurse would have. Plus the ward totals.
     *
     * @param  Collection  $acuity  PatientAcuity rows
     * @param  array  $shifts  WardShifts::forWard()
     */
    public static function forShifts(Collection $acuity, array $shifts, AcuitySettings $settings): array
    {
        $byLevel = array_fill_keys(array_keys(PatientAcuity::LEVELS), 0);
        foreach ($acuity as $row) {
            if ($row['patient'] && $row['level']) {
                $byLevel[$row['level']]++;
            }
        }

        $patients = array_sum($byLevel);
        $careHours = 0.0;
        foreach ($byLevel as $level => $count) {
            $careHours += $count * $settings->hours($level);
        }

        $perShift = [];
        foreach (WardShifts::CODES as $code) {
            $length = (float) ($shifts[$code]['hours'] ?? 0);
            $needed = $careHours * $settings->share($code) / 100;
            $exact = $length > 0 ? $needed / $length : 0.0;
            $recommended = $patients ? max(1, (int) ceil(round($exact, 2) - self::ROUNDING_SLACK)) : 0;

            $perShift[$code] = [
                'care_hours' => round($needed, 1),
                'shift_hours' => $length,
                'exact' => round($exact, 2),
                'recommended' => $recommended,
                'patients_per_nurse' => $recommended ? round($patients / $recommended, 1) : null,
            ];
        }

        return [
            'patients' => $patients,
            'by_level' => $byLevel,
            'care_hours' => round($careHours, 1),
            'hours_per_patient' => $patients ? round($careHours / $patients, 1) : 0.0,
            'shifts' => $perShift,
        ];
    }

    /**
     * The week on the roster against the recommendation: per date and shift,
     * the nurses rostered, how many are registered nurses, the minimum, the
     * recommendation, and the gap (registered nurses less the recommendation).
     *
     * @param  array  $board  RosterBoard::build()
     * @param  array  $staffing  forShifts()
     * @return array<string, array<string, array{rostered: int, registered: int, min: int, recommended: int, gap: int, status: string}>>
     */
    public static function week(array $board, array $staffing): array
    {
        $week = [];
        foreach ($board['dates'] as $date) {
            $day = $date->toDateString();
            foreach (WardShifts::CODES as $code) {
                $onShift = RosterBoard::onShift($board, $day, $code);
                $registered = $onShift->filter(fn (Nurse $nurse) => self::isRegisteredNurse($nurse))->count();
                $recommended = $staffing['shifts'][$code]['recommended'] ?? 0;
                $gap = $registered - $recommended;

                $week[$day][$code] = [
                    'rostered' => $onShift->count(),
                    'registered' => $registered,
                    'min' => (int) ($board['staffing'][$day][$code]['min'] ?? 0),
                    'recommended' => $recommended,
                    'gap' => $gap,
                    'status' => $gap < 0 ? 'short' : ($gap > 0 ? 'over' : 'met'),
                ];
            }
        }

        return $week;
    }

    /** The shifts in the week with fewer registered nurses than the patients need. */
    public static function shortShifts(array $week): int
    {
        return collect($week)->flatten(1)->where('status', 'short')->count();
    }

    /** Registered nurses count toward the care hours; students and care assistants do not. */
    public static function isRegisteredNurse(Nurse $nurse): bool
    {
        return !in_array(mb_strtolower(trim((string) $nurse->designation)), self::NOT_REGISTERED, true);
    }
}
