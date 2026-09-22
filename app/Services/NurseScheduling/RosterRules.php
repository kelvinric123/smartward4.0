<?php

namespace App\Services\NurseScheduling;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\WardRosterSetting;

/**
 * A ward's staffing rules for the AI Nurse Schedule: what the stored settings
 * say, with sensible defaults for anything a nurse manager has not set.
 *
 * Minimum staffing defaults to one nurse per 5 beds on AM, per 6 on PM and
 * per 8 overnight, rounded up.
 */
final class RosterRules
{
    /** Beds per nurse used to suggest minimum staffing. */
    public const BEDS_PER_NURSE = ['AM' => 5, 'PM' => 6, 'ON' => 8];

    public const DEFAULTS = [
        'target_shifts_per_week' => 5,
        'max_consecutive_days' => 6,
        'max_consecutive_nights' => 4,
        'avoid_quick_return' => true,
    ];

    /** How far above the minimum a day shift may be topped up to give nurses their weekly shifts. */
    public const MAX_EXTRA_PER_DAY_SHIFT = 2;

    public function __construct(
        public readonly array $minStaff,
        public readonly int $targetShiftsPerWeek,
        public readonly int $maxConsecutiveDays,
        public readonly int $maxConsecutiveNights,
        public readonly bool $avoidQuickReturn,
        public readonly array $suggestedMinStaff,
    ) {
    }

    public static function forWard(Ward $ward): self
    {
        $stored = WardRosterSetting::where('ward_id', $ward->id)->value('rules') ?? [];
        $stored = is_array($stored) ? $stored : [];

        $beds = Bed::where('ward_id', $ward->id)->where('is_active', true)->count();
        $suggested = self::suggestedMinStaff($beds);

        $minStaff = [];
        foreach ($suggested as $shift => $count) {
            $minStaff[$shift] = max(0, (int) ($stored['min_staff'][$shift] ?? $count));
        }

        return new self(
            minStaff: $minStaff,
            targetShiftsPerWeek: (int) ($stored['target_shifts_per_week'] ?? self::DEFAULTS['target_shifts_per_week']),
            maxConsecutiveDays: (int) ($stored['max_consecutive_days'] ?? self::DEFAULTS['max_consecutive_days']),
            maxConsecutiveNights: (int) ($stored['max_consecutive_nights'] ?? self::DEFAULTS['max_consecutive_nights']),
            avoidQuickReturn: (bool) ($stored['avoid_quick_return'] ?? self::DEFAULTS['avoid_quick_return']),
            suggestedMinStaff: $suggested,
        );
    }

    /** @return array{AM: int, PM: int, ON: int} */
    public static function suggestedMinStaff(int $beds): array
    {
        return collect(self::BEDS_PER_NURSE)
            ->map(fn (int $perNurse) => max(1, (int) ceil($beds / $perNurse)))
            ->all();
    }

    /** The shape stored in ward_roster_settings.rules. */
    public function toArray(): array
    {
        return [
            'min_staff' => $this->minStaff,
            'target_shifts_per_week' => $this->targetShiftsPerWeek,
            'max_consecutive_days' => $this->maxConsecutiveDays,
            'max_consecutive_nights' => $this->maxConsecutiveNights,
            'avoid_quick_return' => $this->avoidQuickReturn,
        ];
    }
}
