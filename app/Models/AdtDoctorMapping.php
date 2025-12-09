<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdtDoctorMapping extends Model
{
    protected $fillable = [
        'adt_configuration_id',
        'adt_doctor_code',
        'adt_doctor_name',
        'doctor_type',
        'consultant_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Doctor types
     */
    public const TYPES = [
        'attending' => 'Attending Doctor',
        'referring' => 'Referring Doctor',
        'consulting' => 'Consulting Doctor',
        'admitting' => 'Admitting Doctor',
    ];

    /**
     * Get the ADT configuration.
     */
    public function adtConfiguration(): BelongsTo
    {
        return $this->belongsTo(AdtConfiguration::class);
    }

    /**
     * Get the consultant.
     */
    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }

    /**
     * Find consultant by ADT doctor code.
     */
    public static function findConsultantByCode(string $code, string $type = 'attending', ?int $configId = null): ?Consultant
    {
        $query = static::where('adt_doctor_code', $code)
            ->where('doctor_type', $type)
            ->where('is_active', true);

        if ($configId) {
            $query->where('adt_configuration_id', $configId);
        }

        $mapping = $query->first();

        return $mapping?->consultant;
    }
}








