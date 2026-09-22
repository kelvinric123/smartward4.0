<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What the patient's I/O is held to: the most they may take in over a chart
 * day (fluid restriction) and the least urine expected per hour. Each change
 * is saved as a new plan, so the latest one is in force and the earlier ones
 * show who changed what and when. A plan with neither limit set lifts them.
 */
class FluidBalancePlan extends Model
{
    /** Bounds for the daily intake limit, in mL. */
    public const LIMIT_MIN = 100;
    public const LIMIT_MAX = 10000;

    /** Bounds for the minimum urine output, in mL per hour. */
    public const URINE_MIN = 5;
    public const URINE_MAX = 500;

    /** Common fluid restrictions and urine targets, offered as one-tap choices. */
    public const LIMIT_PRESETS = [800, 1000, 1200, 1500, 2000];
    public const URINE_PRESETS = [20, 30, 40, 50];

    /** Intake at or past this share of the limit shows as near the limit. */
    public const NEAR_LIMIT_PERCENT = 80;

    protected $fillable = [
        'patient_id',
        'ward_id',
        'intake_limit_ml',
        'urine_min_ml_per_hour',
        'notes',
        'consultant_order_id',
        'set_by',
    ];

    protected $casts = [
        'intake_limit_ml' => 'integer',
        'urine_min_ml_per_hour' => 'integer',
        'consultant_order_id' => 'integer',
    ];

    /** The consultant order whose fluid restriction set this plan, if one did. */
    public function consultantOrder(): BelongsTo
    {
        return $this->belongsTo(ConsultantOrder::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    /**
     * The plan in force for this admission. Patient records are reused when a
     * patient is admitted again, so a plan from an earlier stay is ignored.
     */
    public static function currentFor(Patient $patient): ?self
    {
        return static::where('patient_id', $patient->id)
            ->when($patient->admitted_at, fn ($query) => $query->where('created_at', '>=', $patient->admitted_at))
            ->with('setBy:id,name')
            ->orderByDesc('id')
            ->first();
    }

    public function hasLimits(): bool
    {
        return $this->intake_limit_ml !== null || $this->urine_min_ml_per_hour !== null;
    }

    /** "Intake up to 1,500 mL per day, urine at least 30 mL/h". */
    public function summary(): string
    {
        $parts = [];

        if ($this->intake_limit_ml !== null) {
            $parts[] = 'Intake up to ' . number_format($this->intake_limit_ml) . ' mL per day';
        }

        if ($this->urine_min_ml_per_hour !== null) {
            $parts[] = ($parts ? 'urine' : 'Urine') . ' at least ' . $this->urine_min_ml_per_hour . ' mL/h';
        }

        return $parts ? implode(', ', $parts) : 'No limits';
    }

    /** Whether saving these values would change nothing. */
    public function matches(?int $intakeLimit, ?int $urineMin, ?string $notes): bool
    {
        return $this->intake_limit_ml === $intakeLimit
            && $this->urine_min_ml_per_hour === $urineMin
            && (string) $this->notes === (string) $notes;
    }
}
