<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdtHospitalMapping extends Model
{
    protected $fillable = [
        'adt_configuration_id',
        'adt_hospital_code',
        'adt_hospital_name',
        'hospital_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the ADT configuration.
     */
    public function adtConfiguration(): BelongsTo
    {
        return $this->belongsTo(AdtConfiguration::class);
    }

    /**
     * Get the local hospital.
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}






