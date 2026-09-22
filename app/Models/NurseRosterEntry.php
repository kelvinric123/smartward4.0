<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The shift one nurse works on one day in the AI Nurse Schedule: AM, PM or
 * ON, or OFF for a fixed day off.
 *
 * ON on a date is the night that starts that evening. "auto" entries come
 * from the generator and are replaced each time it runs; "manual" ones are
 * kept, and the generator plans around them.
 */
class NurseRosterEntry extends Model
{
    public const SHIFT_AM = 'AM';
    public const SHIFT_PM = 'PM';
    public const SHIFT_NIGHT = 'ON';
    public const OFF = 'OFF';

    /** The working shifts, in the order a day runs. */
    public const SHIFTS = [self::SHIFT_AM, self::SHIFT_PM, self::SHIFT_NIGHT];

    public const SOURCE_AUTO = 'auto';
    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'ward_id',
        'nurse_id',
        'roster_date',
        'shift',
        'source',
    ];

    protected $casts = [
        'roster_date' => 'date',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    public function isWorking(): bool
    {
        return in_array($this->shift, self::SHIFTS, true);
    }
}
