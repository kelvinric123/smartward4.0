<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mrn',
        'rn',
        'ic_passport',
        'age',
        'gender',
        'date_of_birth',
        'race',
        'religion',
        'phone',
        'address',
        'is_active',
        'ward_id',
        'bed_number',
        'target_bed_number',
        'consultant_id',
        'nurse_id',
        'anaesthetist_id',
        'admitted_at',
        'expected_discharge_at',
        'pending_discharge_at',
        'discharged_at',
        'estimated_length_of_stay',
        'booked_at',
        'status',
        'visit_number',
        'patient_class',
        'nursing_level',
        'diet_types',
        'fall_risk',
        'isolation_type',
        'allergies',
        'hgt_enabled',
        'hgt_frequency',
    ];

    /**
     * Patient status constants
     */
    const STATUS_PREBOOK = 'prebook';
    const STATUS_PREBOOK_PENDING = 'prebook_pending'; // Prebook for a bed with pending discharge
    const STATUS_ADMITTED = 'admitted';
    const STATUS_PENDING_DISCHARGE = 'pending_discharge';
    const STATUS_DISCHARGED = 'discharged';
    const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'is_active' => 'boolean',
        'admitted_at' => 'datetime',
        'expected_discharge_at' => 'datetime',
        'pending_discharge_at' => 'datetime',
        'discharged_at' => 'datetime',
        'booked_at' => 'datetime',
        'date_of_birth' => 'date',
        'allergies' => 'array',
        'address' => 'array',
        'diet_types' => 'array',
        'hgt_enabled' => 'boolean',
    ];

    /**
     * Check if patient is pending discharge
     */
    public function isPendingDischarge(): bool
    {
        return $this->status === self::STATUS_PENDING_DISCHARGE || $this->pending_discharge_at !== null;
    }

    /**
     * Check if patient is currently admitted
     */
    public function isAdmitted(): bool
    {
        return in_array($this->status, [self::STATUS_ADMITTED, self::STATUS_PENDING_DISCHARGE]);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function consultant()
    {
        return $this->belongsTo(Consultant::class);
    }

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }

    public function anaesthetist()
    {
        return $this->belongsTo(Anaesthetist::class);
    }

    public function bed()
    {
        return $this->hasOne(Bed::class, 'patient_id');
    }

    public function consultants()
    {
        return $this->hasManyThrough(
            Consultant::class,
            Bed::class,
            'patient_id',
            'id',
            'id',
            'consultant_id'
        )->join('bed_consultant', 'consultants.id', '=', 'bed_consultant.consultant_id')
            ->where('bed_consultant.bed_id', '=', function ($query) {
                $query->select('id')
                    ->from('beds')
                    ->whereColumn('beds.patient_id', 'patients.id');
            });
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PatientMovement::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(PatientReferral::class);
    }

    /**
     * Get all care providers from ADT (attending, referring, consulting doctors)
     */
    public function careProviders(): HasMany
    {
        return $this->hasMany(PatientCareProvider::class);
    }

    /**
     * Get active care providers
     */
    public function activeCareProviders(): HasMany
    {
        return $this->hasMany(PatientCareProvider::class)->where('is_active', true);
    }

    /**
     * Get attending doctors (PV1-7)
     */
    public function attendingDoctors(): HasMany
    {
        return $this->hasMany(PatientCareProvider::class)
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->where('is_active', true);
    }

    /**
     * Get referring doctors (PV1-8)
     */
    public function referringDoctors(): HasMany
    {
        return $this->hasMany(PatientCareProvider::class)
            ->where('role', PatientCareProvider::ROLE_REFERRING)
            ->where('is_active', true);
    }

    /**
     * Get consulting doctors (PV1-9)
     */
    public function consultingDoctors(): HasMany
    {
        return $this->hasMany(PatientCareProvider::class)
            ->where('role', PatientCareProvider::ROLE_CONSULTING)
            ->where('is_active', true);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }

    /**
     * Get all sugar readings for this patient
     */
    public function sugarReadings(): HasMany
    {
        return $this->hasMany(SugarReading::class)->orderBy('recorded_at', 'desc');
    }

    /**
     * Get the latest sugar reading for this patient
     */
    public function latestSugarReading()
    {
        return $this->hasOne(SugarReading::class)->latestOfMany('recorded_at');
    }

    /**
     * Get the current admission ID for this patient
     */
    public function getCurrentAdmissionId(): ?string
    {
        if ($this->status === 'admitted' && $this->admitted_at) {
            return 'ADM-' . $this->id . '-' . $this->admitted_at->format('YmdHis');
        }
        return null;
    }

    /**
     * Get all unique admission IDs for this patient from vital signs
     */
    public function getAdmissionHistory(): array
    {
        return $this->vitalSigns()
            ->whereNotNull('admission_id')
            ->distinct()
            ->pluck('admission_id')
            ->toArray();
    }
}

