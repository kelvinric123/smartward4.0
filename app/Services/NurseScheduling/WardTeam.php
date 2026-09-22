<?php

namespace App\Services\NurseScheduling;

use App\Models\Nurse;
use App\Models\Ward;
use Illuminate\Support\Collection;

/**
 * The nurses a ward's roster is planned for.
 *
 * Nurses whose home ward is this ward, when there are any. Otherwise every
 * active nurse without a home ward, which is how a hospital that has not set
 * home wards yet still gets a roster. Tagging nurses are left out: they work
 * alongside the nurse they tag, so they neither count towards staffing nor
 * get beds of their own.
 */
final class WardTeam
{
    public const SOURCE_WARD = 'ward';
    public const SOURCE_UNASSIGNED = 'unassigned';

    /**
     * @return array{nurses: Collection<int, Nurse>, source: string}
     */
    public static function for(Ward $ward): array
    {
        $home = self::base()->where('ward_id', $ward->id)->get();

        if ($home->isNotEmpty()) {
            return ['nurses' => $home, 'source' => self::SOURCE_WARD];
        }

        return ['nurses' => self::base()->whereNull('ward_id')->get(), 'source' => self::SOURCE_UNASSIGNED];
    }

    private static function base()
    {
        return Nurse::where('is_active', true)
            ->where(fn ($query) => $query->where('is_tagging', false)->orWhereNull('is_tagging'))
            ->orderBy('name');
    }
}
