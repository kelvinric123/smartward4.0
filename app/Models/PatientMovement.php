<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'ward_id',
        'bed_number',
        'location',
        'location_type',
        'notes',
        'scheduled_at',
        'sent_at',
        'returned_at',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}


