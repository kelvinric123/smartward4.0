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
    public static function getCurrentShift(int $wardId, ?\Carbon\Carbon $time = null): ?self
    {
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









