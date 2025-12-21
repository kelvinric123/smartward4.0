<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionLog extends Model
{
    protected $fillable = [
        'patient_id',
        'ward_id',
        'user_id',
        'bed_number',
        'action',
        'patient_name',
        'mrn',
        'consultant_name',
        'nurse_name',
        'gender',
        'age',
        'notes',
        'admitted_at',
        'booked_at',
        'source', // 'manual' or 'adt'
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'booked_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
