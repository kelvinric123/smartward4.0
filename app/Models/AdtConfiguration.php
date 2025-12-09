<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdtConfiguration extends Model
{
    protected $fillable = [
        'name',
        'listener_host',
        'listener_port',
        'is_active',
        'auto_admit',
        'auto_discharge',
        'auto_transfer',
        'settings',
        'last_message_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_admit' => 'boolean',
        'auto_discharge' => 'boolean',
        'auto_transfer' => 'boolean',
        'settings' => 'array',
        'last_message_at' => 'datetime',
    ];

    /**
     * Get the hospital mappings for this configuration.
     */
    public function hospitalMappings(): HasMany
    {
        return $this->hasMany(AdtHospitalMapping::class);
    }

    /**
     * Get the ward mappings for this configuration.
     */
    public function wardMappings(): HasMany
    {
        return $this->hasMany(AdtWardMapping::class);
    }

    /**
     * Get the bed mappings for this configuration.
     */
    public function bedMappings(): HasMany
    {
        return $this->hasMany(AdtBedMapping::class);
    }

    /**
     * Get the doctor mappings for this configuration.
     */
    public function doctorMappings(): HasMany
    {
        return $this->hasMany(AdtDoctorMapping::class);
    }

    /**
     * Get the message logs for this configuration.
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(AdtMessageLog::class);
    }

    /**
     * Get the active configuration.
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Find hospital by ADT code.
     */
    public function findHospitalByAdtCode(string $code): ?Hospital
    {
        $mapping = $this->hospitalMappings()
            ->where('adt_hospital_code', $code)
            ->where('is_active', true)
            ->first();

        return $mapping?->hospital;
    }

    /**
     * Find ward by ADT code.
     */
    public function findWardByAdtCode(string $code): ?Ward
    {
        $mapping = $this->wardMappings()
            ->where('adt_ward_code', $code)
            ->where('is_active', true)
            ->first();

        return $mapping?->ward;
    }

    /**
     * Find bed by ADT code.
     */
    public function findBedByAdtCode(string $code): ?Bed
    {
        $mapping = $this->bedMappings()
            ->where('adt_bed_code', $code)
            ->where('is_active', true)
            ->first();

        return $mapping?->bed;
    }

    /**
     * Find consultant by ADT doctor code.
     */
    public function findConsultantByAdtCode(string $code, string $type = 'attending'): ?Consultant
    {
        $mapping = $this->doctorMappings()
            ->where('adt_doctor_code', $code)
            ->where('doctor_type', $type)
            ->where('is_active', true)
            ->first();

        return $mapping?->consultant;
    }
}




