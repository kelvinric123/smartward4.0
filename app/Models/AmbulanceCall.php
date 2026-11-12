<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An ambulance call to the ED. The response time runs from called_at to
 * arrived_at (the crew with the patient); a call with arrived_at is a case
 * attended, one with cancelled_at was called off, and one with neither is
 * still open (the crew on the way).
 */
class AmbulanceCall extends Model
{
    protected $fillable = [
        'reference',
        'called_at',
        'dispatched_at',
        'arrived_at',
        'cancelled_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'arrived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
