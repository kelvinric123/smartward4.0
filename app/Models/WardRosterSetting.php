<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ward's staffing rules for the AI Nurse Schedule. Only what a nurse
 * manager has changed is stored; everything else comes from
 * App\Services\NurseScheduling\RosterRules defaults.
 */
class WardRosterSetting extends Model
{
    protected $fillable = [
        'ward_id',
        'rules',
    ];

    protected $casts = [
        'rules' => 'array',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }
}
