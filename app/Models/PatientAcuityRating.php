<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One classification of a patient's care level (1 Minimal to 4 Intensive) by a
 * nurse, with what the indicators suggested at the time. See PatientAcuity.
 */
class PatientAcuityRating extends Model
{
    protected $fillable = [
        'patient_id',
        'ward_id',
        'bed_number',
        'level',
        'suggested_level',
        'previous_level',
        'reasons',
        'note',
        'shift_date',
        'shift',
        'rated_by',
        'rated_by_name',
        'rated_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'suggested_level' => 'integer',
        'previous_level' => 'integer',
        'reasons' => 'array',
        'shift_date' => 'date',
        'rated_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /** Agrees with the indicators, or rated above or below them. Null when there was no suggestion. */
    public function agreement(): ?string
    {
        if ($this->suggested_level === null) {
            return null;
        }

        return match (true) {
            $this->level === $this->suggested_level => 'agrees',
            $this->level > $this->suggested_level => 'above',
            default => 'below',
        };
    }
}
