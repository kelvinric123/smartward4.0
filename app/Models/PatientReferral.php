<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'referral_type',
        'consultant_id',
        'anaesthetist_id',
        'reason',
        'notes',
        'status',
        'created_by',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }

    public function anaesthetist(): BelongsTo
    {
        return $this->belongsTo(Anaesthetist::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}


