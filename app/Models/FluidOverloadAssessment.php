<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bedside check for fluid overload: pitting edema (grade and where), the
 * other signs of overload, and the patient's weight. A day-to-day rise in
 * weight is the earliest sign of fluid being retained.
 */
class FluidOverloadAssessment extends Model
{
    /** Pitting edema, graded by how deep the pit is and how long it lasts. */
    public const EDEMA_GRADES = [
        0 => ['short' => 'None', 'label' => 'No edema'],
        1 => ['short' => '1+', 'label' => '2 mm pit, rebounds at once'],
        2 => ['short' => '2+', 'label' => '4 mm pit, gone in 10 to 15 s'],
        3 => ['short' => '3+', 'label' => '6 mm pit, lasts over 1 min'],
        4 => ['short' => '4+', 'label' => '8 mm pit, lasts over 2 min'],
    ];

    public const EDEMA_SITES = [
        'feet_ankles' => 'Feet / ankles',
        'legs' => 'Lower legs',
        'sacral' => 'Sacrum',
        'hands_arms' => 'Hands / arms',
        'face' => 'Face / eyelids',
        'generalised' => 'Generalised',
    ];

    public const SIGNS = [
        'breathless' => 'Shortness of breath',
        'orthopnea' => 'Breathless lying flat',
        'crackles' => 'Crackles on the chest',
        'raised_jvp' => 'Raised JVP / neck veins',
        'ascites' => 'Abdominal swelling',
        'frothy_sputum' => 'Pink frothy sputum',
    ];

    /** Signs that call for the doctor straight away, not at the next review. */
    public const URGENT_SIGNS = ['frothy_sputum'];

    /**
     * Weight gain flagged as fluid being retained: this much, within this many
     * days of an earlier weight.
     */
    public const WEIGHT_GAIN_FLAG_KG = 2.0;
    public const WEIGHT_GAIN_WINDOW_DAYS = 3;

    public const WEIGHT_MIN = 1;
    public const WEIGHT_MAX = 400;

    protected $fillable = [
        'patient_id',
        'ward_id',
        'edema_grade',
        'edema_sites',
        'signs',
        'weight_kg',
        'notes',
        'assessed_at',
        'recorded_by',
    ];

    protected $casts = [
        'edema_grade' => 'integer',
        'edema_sites' => 'array',
        'signs' => 'array',
        'weight_kg' => 'decimal:1',
        'assessed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function hasEdema(): bool
    {
        return $this->edema_grade > 0;
    }

    public function hasOverloadSigns(): bool
    {
        return $this->hasEdema() || !empty($this->signs);
    }

    public function hasUrgentSign(): bool
    {
        return (bool) array_intersect($this->signs ?? [], self::URGENT_SIGNS);
    }

    public function edemaShort(): string
    {
        return self::EDEMA_GRADES[$this->edema_grade]['short'] ?? (string) $this->edema_grade;
    }

    /** "Edema 2+ (feet / ankles, lower legs)", or "No edema". */
    public function edemaSummary(): string
    {
        if (!$this->hasEdema()) {
            return 'No edema';
        }

        $sites = $this->siteLabels();

        return 'Edema ' . $this->edemaShort() . ($sites ? ' (' . strtolower(implode(', ', $sites)) . ')' : '');
    }

    public function siteLabels(): array
    {
        return array_values(array_intersect_key(self::EDEMA_SITES, array_flip($this->edema_sites ?? [])));
    }

    public function signLabels(): array
    {
        return array_values(array_intersect_key(self::SIGNS, array_flip($this->signs ?? [])));
    }

    /** Everything found, for a one-line summary: edema first, then the other signs. */
    public function findings(): array
    {
        return array_merge($this->hasEdema() ? [$this->edemaSummary()] : [], $this->signLabels());
    }
}
