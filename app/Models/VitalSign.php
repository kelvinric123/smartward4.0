<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VitalSign extends Model
{
    protected $fillable = [
        'patient_id',
        'admission_id',
        'recorded_by',
        'systolic_bp',
        'diastolic_bp',
        'pulse_rate',
        'temperature',
        'spo2',
        'respiratory_rate',
        'reading_type',
        'notes',
        'recorded_at',
    ];

    protected $casts = [
        'systolic_bp' => 'integer',
        'diastolic_bp' => 'integer',
        'pulse_rate' => 'integer',
        'temperature' => 'decimal:1',
        'spo2' => 'integer',
        'respiratory_rate' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the blood pressure as a formatted string (e.g., "120/80")
     */
    public function getBloodPressureAttribute(): ?string
    {
        if ($this->systolic_bp && $this->diastolic_bp) {
            return "{$this->systolic_bp}/{$this->diastolic_bp}";
        }
        return null;
    }

    /**
     * Check if this is a full reading (all vital signs recorded)
     */
    public function getIsFullReadingAttribute(): bool
    {
        return $this->reading_type === 'full' || (
            $this->systolic_bp !== null &&
            $this->diastolic_bp !== null &&
            $this->pulse_rate !== null &&
            $this->temperature !== null &&
            $this->spo2 !== null &&
            $this->respiratory_rate !== null
        );
    }

    /**
     * Scope to filter by admission
     */
    public function scopeForAdmission($query, $admissionId)
    {
        return $query->where('admission_id', $admissionId);
    }

    /**
     * Scope to get latest vital signs first
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope to search by patient name or MRN
     */
    public function scopeSearchPatient($query, $search)
    {
        return $query->whereHas('patient', function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
              ->orWhere('mrn', 'like', '%' . $search . '%');
        });
    }
}






