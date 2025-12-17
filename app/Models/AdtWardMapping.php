<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdtWardMapping extends Model
{
    protected $fillable = [
        'adt_configuration_id',
        'adt_ward_code',
        'adt_ward_name',
        'ward_id',
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
     * Get the local ward.
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}











