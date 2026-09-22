<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How far one care plan goal was met in one shift.
 */
class NursingCarePlanEvaluation extends Model
{
    public const OUTCOME_MET = 'met';
    public const OUTCOME_PARTLY_MET = 'partly_met';
    public const OUTCOME_NOT_MET = 'not_met';

    public const OUTCOMES = [
        self::OUTCOME_MET => 'Met',
        self::OUTCOME_PARTLY_MET => 'Partly met',
        self::OUTCOME_NOT_MET => 'Not met',
    ];

    protected $fillable = [
        'nursing_care_plan_item_id',
        'patient_id',
        'outcome',
        'note',
        'shift_date',
        'shift_code',
        'evaluated_at',
        'evaluated_by',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'evaluated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(NursingCarePlanItem::class, 'nursing_care_plan_item_id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function outcomeLabel(): string
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst(str_replace('_', ' ', (string) $this->outcome));
    }
}
