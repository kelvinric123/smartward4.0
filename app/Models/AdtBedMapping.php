<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdtBedMapping extends Model
{
    protected $fillable = [
        'adt_configuration_id',
        'adt_bed_code',
        'adt_bed_name',
        'bed_id',
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
     * Get the local bed.
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }
}



