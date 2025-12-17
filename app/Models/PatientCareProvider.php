<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientCareProvider extends Model
{
    use HasFactory;

    /**
     * Care provider roles from ADT PV1 segment
     */
    const ROLE_ATTENDING = 'attending';   // PV1-7
    const ROLE_REFERRING = 'referring';   // PV1-8
    const ROLE_CONSULTING = 'consulting'; // PV1-9

    /**
     * Source of the care provider record
     */
    const SOURCE_ADT = 'adt';
    const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'patient_id',
        'role',
        'doctor_code',
        'doctor_name',
        'consultant_id',
        'anaesthetist_id',
        'source',
        'visit_number',
        'assigned_at',
        'is_active',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the patient that this care provider is assigned to
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the linked consultant (if matched)
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }

    /**
     * Get the linked anaesthetist (if matched)
     */
    public function anaesthetist(): BelongsTo
    {
        return $this->belongsTo(Anaesthetist::class);
    }

    /**
     * Scope to filter by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to filter active providers only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for attending doctors
     */
    public function scopeAttending($query)
    {
        return $query->where('role', self::ROLE_ATTENDING);
    }

    /**
     * Scope for referring doctors
     */
    public function scopeReferring($query)
    {
        return $query->where('role', self::ROLE_REFERRING);
    }

    /**
     * Scope for consulting doctors
     */
    public function scopeConsulting($query)
    {
        return $query->where('role', self::ROLE_CONSULTING);
    }

    /**
     * Get the display name for the care provider
     * Returns linked consultant/anaesthetist name if available, otherwise doctor_name from ADT
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->consultant) {
            return $this->consultant->name;
        }
        
        if ($this->anaesthetist) {
            return $this->anaesthetist->name;
        }
        
        return $this->doctor_name ?: $this->doctor_code;
    }

    /**
     * Get the specialty for display
     */
    public function getSpecialtyAttribute(): ?string
    {
        if ($this->consultant && $this->consultant->specialty) {
            return $this->consultant->specialty->name;
        }
        
        if ($this->anaesthetist) {
            return 'Anaesthesiology';
        }
        
        return null;
    }

    /**
     * Get role display label
     */
    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_ATTENDING => 'Attending Doctor',
            self::ROLE_REFERRING => 'Referring Doctor',
            self::ROLE_CONSULTING => 'Consulting Doctor',
            default => ucfirst($this->role),
        };
    }

    /**
     * Get role badge color class
     */
    public function getRoleBadgeClassAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_ATTENDING => 'bg-blue-100 text-blue-800',
            self::ROLE_REFERRING => 'bg-purple-100 text-purple-800',
            self::ROLE_CONSULTING => 'bg-green-100 text-green-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Check if the provider is linked to a consultant or anaesthetist
     */
    public function isLinked(): bool
    {
        return $this->consultant_id !== null || $this->anaesthetist_id !== null;
    }
}




