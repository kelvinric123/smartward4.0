<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A nurse confirming, in the nurse app, that they have seen their roster for a
 * week (Monday to Sunday). The fingerprint is the roster as they saw it: when it
 * changes afterwards, the week reads "changed since seen" until they look again.
 */
class NurseRosterAcknowledgement extends Model
{
    protected $fillable = [
        'nurse_id',
        'week_start',
        'fingerprint',
        'acknowledged_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'acknowledged_at' => 'datetime',
    ];

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }
}
