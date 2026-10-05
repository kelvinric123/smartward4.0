<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'alias_name',
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
        'feeding_routes',
        'diet_orders',
        'payor_type',
        'payor_name',
        'payor_policy_number',
        'payor_gl_number',
        'payor_gl_amount',
        'payor_status',
        'payor_remarks',
        'coe_indicators',
        'total_charges',
        'deposit_paid',
        'charges_updated_at',
    ];

    /**
     * Payor types (value => label)
     */
    const PAYOR_TYPES = [
        'self_pay' => 'Self Pay',
        'insurance' => 'Insurance',
        'corporate' => 'Corporate / Panel',
        'government' => 'Government',
        'other' => 'Other',
    ];

    /**
     * Payor / insurance (GL) status (value => label)
     */
    const PAYOR_STATUSES = [
        'pending' => 'Pending Verification',
        'gl_requested' => 'GL Requested',
        'approved' => 'GL Approved',
        'partial' => 'Partially Approved',
        'rejected' => 'Rejected',
        'not_required' => 'Not Required',
    ];

    /**
     * Suggested Centre of Excellence (COE) programmes. Custom values can also be entered.
     */
    /**
     * Non-oral feeding routes (value => label), used when the ward manages diet directly
     */
    const FEEDING_ROUTES = [
        'ngt' => 'Nasogastric Tube (NGT)',
        'ogt' => 'Orogastric Tube (OGT)',
        'peg' => 'PEG / Gastrostomy',
        'nj' => 'Nasojejunal Tube (NJ)',
        'tpn' => 'Total Parenteral Nutrition (TPN)',
    ];

    /**
     * Diet codes that mean Nil By Mouth; the NBM switch adds/removes the first one.
     */
    const NBM_DIET_CODES = ['NBM', 'NPO'];

    const COE_INDICATOR_SUGGESTIONS = [
        'CCPC Breast',
        'Chronic Kidney Disease',
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
        'cplus_isolation' => 'boolean',
        'feeding_routes' => 'array',
        'coe_indicators' => 'array',
        'payor_gl_amount' => 'decimal:2',
        'total_charges' => 'decimal:2',
        'deposit_paid' => 'decimal:2',
        'charges_updated_at' => 'datetime',
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

    /**
     * Admission status label and badge colours (shared by the patient list and view pages)
     */
    const STATUS_LABELS = [
        self::STATUS_PREBOOK => 'Prebook',
        self::STATUS_PREBOOK_PENDING => 'Prebook (Awaiting Bed)',
        self::STATUS_ADMITTED => 'Admitted',
        self::STATUS_PENDING_DISCHARGE => 'Pending Discharge',
        self::STATUS_DISCHARGED => 'Discharged',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    const STATUS_BADGE_CLASSES = [
        self::STATUS_PREBOOK => 'bg-amber-100 text-amber-800',
        self::STATUS_PREBOOK_PENDING => 'bg-amber-100 text-amber-800',
        self::STATUS_ADMITTED => 'bg-green-100 text-green-800',
        self::STATUS_PENDING_DISCHARGE => 'bg-orange-100 text-orange-800',
        self::STATUS_DISCHARGED => 'bg-gray-100 text-gray-700',
        self::STATUS_CANCELLED => 'bg-red-100 text-red-800',
    ];

    const PAYOR_STATUS_BADGE_CLASSES = [
        'pending' => 'bg-amber-100 text-amber-800',
        'gl_requested' => 'bg-blue-100 text-blue-800',
        'approved' => 'bg-green-100 text-green-800',
        'partial' => 'bg-teal-100 text-teal-800',
        'rejected' => 'bg-red-100 text-red-800',
        'not_required' => 'bg-gray-100 text-gray-700',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? Str::headline((string) ($this->status ?: 'Unknown'));
    }

    public function statusBadgeClass(): string
    {
        return self::STATUS_BADGE_CLASSES[$this->status] ?? 'bg-gray-100 text-gray-700';
    }

    public function payorStatusLabel(): ?string
    {
        return self::PAYOR_STATUSES[$this->payor_status] ?? null;
    }

    public function payorStatusBadgeClass(): string
    {
        return self::PAYOR_STATUS_BADGE_CLASSES[$this->payor_status] ?? 'bg-gray-100 text-gray-700';
    }

    /**
     * Compact length of stay for table rows, e.g. "3d 5h"
     */
    public function lengthOfStayLabel(): ?string
    {
        $minutes = $this->lengthOfStayMinutes();
        if ($minutes === null) {
            return null;
        }

        return intdiv($minutes, 1440) . 'd ' . intdiv($minutes % 1440, 60) . 'h';
    }

    /**
     * Length of stay in minutes, from admission to discharge (or now while still admitted).
     * Null when never admitted, or discharged without a recorded discharge time.
     */
    public function lengthOfStayMinutes(): ?int
    {
        if (!$this->admitted_at) {
            return null;
        }

        $end = $this->discharged_at ?? ($this->isAdmitted() ? now() : null);
        if (!$end) {
            return null;
        }

        return max(0, (int) $this->admitted_at->diffInMinutes($end));
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

