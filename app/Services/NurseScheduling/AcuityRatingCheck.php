<?php

namespace App\Services\NurseScheduling;

use App\Models\PatientAcuityRating;
use App\Models\Ward;
use Illuminate\Support\Carbon;

/**
 * The rating check on the Patient acuity tab: over the last few weeks, how
 * often the nurses' care level ratings agreed with the level the indicators
 * suggested at the time, for the ward and for each nurse who rated.
 *
 * Monitoring for the "improving" phase (ANA, 2017, pp. 4, 8). Agreement with
 * SmartWard's own indicators, which are not validated, is neither validity
 * nor reliability in ANA's sense: for reliability, nurses rate the same
 * patients independently, at least once a year (ANA, 2017, pp. 13-14). It is
 * not a score of the nurses: the indicators cannot see everything a nurse can, so
 * a rating that differs is often right. A nurse who differs a lot is worth a
 * conversation, and the indicators themselves are worth a look when many do.
 */
final class AcuityRatingCheck
{
    public const DAYS = 30;

    /** The nurses listed, most ratings first. */
    public const RATERS = 10;

    /**
     * @return array{days: int, ratings: int, agrees: int, above: int, below: int, agreement: ?int,
     *     by_rater: list<array{name: string, ratings: int, agrees: int, above: int, below: int, agreement: ?int}>,
     *     last_at: ?Carbon}
     */
    public static function forWard(Ward $ward, int $days = self::DAYS): array
    {
        // Counted in the database: a busy ward rates every patient every shift.
        // A rating without a suggestion (none recorded) counts, but not toward agreement.
        $raters = PatientAcuityRating::where('ward_id', $ward->id)
            ->where('rated_at', '>=', now()->subDays($days))
            ->selectRaw('rated_by, rated_by_name, COUNT(*) AS ratings,
                SUM(CASE WHEN suggested_level IS NOT NULL THEN 1 ELSE 0 END) AS checked,
                SUM(CASE WHEN level = suggested_level THEN 1 ELSE 0 END) AS agrees,
                SUM(CASE WHEN level > suggested_level THEN 1 ELSE 0 END) AS above,
                SUM(CASE WHEN level < suggested_level THEN 1 ELSE 0 END) AS below,
                MAX(rated_at) AS last_at')
            ->groupBy('rated_by', 'rated_by_name')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'name' => $row->rated_by_name ?: 'Unknown',
                'ratings' => (int) $row->ratings,
                'checked' => (int) $row->checked,
                'agrees' => (int) $row->agrees,
                'above' => (int) $row->above,
                'below' => (int) $row->below,
                'last_at' => $row->last_at,
            ]);

        $checked = $raters->sum('checked');
        $lastAt = $raters->max('last_at');

        return [
            'days' => $days,
            'ratings' => $raters->sum('ratings'),
            'agrees' => $raters->sum('agrees'),
            'above' => $raters->sum('above'),
            'below' => $raters->sum('below'),
            'agreement' => self::percent($raters->sum('agrees'), $checked),
            'by_rater' => $raters
                ->sortBy([['ratings', 'desc'], ['name', 'asc']])
                ->take(self::RATERS)
                ->map(fn (array $rater) => [
                    'name' => $rater['name'],
                    'ratings' => $rater['ratings'],
                    'agrees' => $rater['agrees'],
                    'above' => $rater['above'],
                    'below' => $rater['below'],
                    'agreement' => self::percent($rater['agrees'], $rater['checked']),
                ])
                ->values()
                ->all(),
            'last_at' => $lastAt ? Carbon::parse($lastAt) : null,
        ];
    }

    private static function percent(int $part, int $whole): ?int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : null;
    }
}
