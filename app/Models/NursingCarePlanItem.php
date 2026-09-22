<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One nursing diagnosis (problem) in a patient's nursing care plan, with the
 * goal to reach and the interventions that get there. Evaluated each shift
 * (NursingCarePlanEvaluation) until resolved or discontinued.
 */
class NursingCarePlanItem extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_DISCONTINUED = 'discontinued';

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_DISCONTINUED => 'Discontinued',
    ];

    protected $fillable = [
        'patient_id',
        'ward_id',
        'template_key',
        'category',
        'diagnosis',
        'related_to',
        'goal',
        'interventions',
        'status',
        'started_at',
        'resolved_at',
        'resolved_by',
        'resolve_note',
        'created_by',
    ];

    protected $casts = [
        'interventions' => 'array',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(NursingCarePlanEvaluation::class)
            ->orderByDesc('evaluated_at')
            ->orderByDesc('id');
    }

    public function latestEvaluation(): HasOne
    {
        return $this->hasOne(NursingCarePlanEvaluation::class)->latestOfMany('evaluated_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }
}
