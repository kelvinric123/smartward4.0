<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'shift_code',
        'shift_name',
        'start_time',
        'end_time',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the ward that owns this shift setting.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get default shift settings for a ward.
     */
    public static function getDefaults(): array
    {
        return [
            [
                'shift_code' => 'AM',
                'shift_name' => 'Morning',
                'start_time' => '07:00',
                'end_time' => '14:00',
                'display_order' => 1,
            ],
            [
                'shift_code' => 'PM',
                'shift_name' => 'Afternoon',
                'start_time' => '14:00',
                'end_time' => '23:00',
                'display_order' => 2,
            ],
            [
                'shift_code' => 'ON',
                'shift_name' => 'Night',
                'start_time' => '23:00',
                'end_time' => '07:00',
                'display_order' => 3,
            ],
        ];
    }

    /**
     * Determine the current shift based on time.
     */
    public static function getCurrentShift(?int $wardId, ?\Carbon\Carbon $time = null): ?self
    {
        if (!$wardId) {
            return null;
        }

        $time = $time ?? now();
        $currentTime = $time->format('H:i:s');

        $shifts = self::where('ward_id', $wardId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        foreach ($shifts as $shift) {
            if ($shift->isTimeInShift($currentTime)) {
                return $shift;
            }
        }

        return null;
    }

    /**
     * The shift running at $time, with the datetimes it started and ends.
     *
     * Returns ['shift' => ShiftSetting, 'starts_at' => Carbon, 'ends_at' => Carbon]
     * or null when no shift is running.
     */
    public static function getCurrentPeriod(?int $wardId, ?\Carbon\Carbon $time = null): ?array
    {
        $time = $time ?? now();
        $shift = self::getCurrentShift($wardId, $time);
        if (!$shift) {
            return null;
        }

        // Overnight shifts started the day before when we are past midnight
        $startsAt = $time->copy()->setTimeFromTimeString($shift->start_time);
        if ($startsAt->gt($time)) {
            $startsAt->subDay();
        }
        $endsAt = $startsAt->copy()->setTimeFromTimeString($shift->end_time);
        if ($endsAt->lte($startsAt)) {
            $endsAt->addDay();
        }

        return [
            'shift' => $shift,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    /**
     * The shift that ended most recently before $time, and when it ended.
     *
     * Returns ['shift' => ShiftSetting, 'ends_at' => Carbon] or null when the
     * ward has no active shifts.
     */
    public static function getPreviousShift(?int $wardId, ?\Carbon\Carbon $time = null): ?array
    {
        if (!$wardId) {
            return null;
        }

        $time = $time ?? now();

        $previous = null;
        $shifts = self::where('ward_id', $wardId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        foreach ($shifts as $shift) {
            $endsAt = $time->copy()->setTimeFromTimeString($shift->end_time);
            if ($endsAt->gt($time)) {
                $endsAt->subDay();
            }
            if (!$previous || $endsAt->gt($previous['ends_at'])) {
                $previous = ['shift' => $shift, 'ends_at' => $endsAt];
            }
        }

        return $previous;
    }

    /**
     * Determine the shift that follows the current one, and when it starts.
     *
     * Returns ['shift' => ShiftSetting, 'starts_at' => Carbon] or null when
     * the ward has no active shifts. `starts_at->toDateString()` is the
     * scheduled_date used for that shift's schedule assignments.
     */
    public static function getNextShift(?int $wardId, ?\Carbon\Carbon $time = null): ?array
    {
        if (!$wardId) {
            return null;
        }

        $time = $time ?? now();

        $shifts = self::where('ward_id', $wardId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->values();

        if ($shifts->isEmpty()) {
            return null;
        }

        $startsAt = function (self $shift) use ($time) {
            $start = $time->copy()->setTimeFromTimeString($shift->start_time);
            return $start->lte($time) ? $start->addDay() : $start;
        };

        $currentTime = $time->format('H:i:s');
        $current = $shifts->first(fn($s) => $s->isTimeInShift($currentTime));

        if ($current) {
            // Prefer the shift that starts when the current one ends,
            // otherwise the next one in display order (wrapping around).
            $next = $shifts->first(fn($s) => $s->id !== $current->id
                && substr((string) $s->start_time, 0, 5) === substr((string) $current->end_time, 0, 5));
            if (!$next) {
                $index = $shifts->search(fn($s) => $s->id === $current->id);
                $next = $shifts[($index + 1) % $shifts->count()];
            }
        } else {
            // Between shifts: whichever starts soonest
            $next = $shifts->sortBy(fn($s) => $startsAt($s)->timestamp)->first();
        }

        return [
            'shift' => $next,
            'starts_at' => $startsAt($next),
        ];
    }

    /**
     * Check if a given time falls within this shift.
     */
    public function isTimeInShift(string $time): bool
    {
        $start = $this->start_time;
        $end = $this->end_time;

        // Handle overnight shifts (e.g., 23:00 - 07:00)
        if ($start > $end) {
            // Time is in shift if it's >= start OR < end
            return $time >= $start || $time < $end;
        }

        // Normal shifts (e.g., 07:00 - 14:00)
        return $time >= $start && $time < $end;
    }
}
















