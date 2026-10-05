<?php

namespace App\Models;

use App\Models\Concerns\DescribesOxygen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a patient's oxygen, made on the Oxygen Therapy tab of Patient Details:
 * the device, its flow rate and/or FiO2, and the SpO2 range aimed for, in force from
 * started_at until the next change. Changing to room air is a change like any other.
 * A change made in error is struck out (voided) rather than deleted.
 *
 * Vital sign readings record oxygen in the same columns; App\Support\OxygenTherapyChart
 * reads the two together.
 */
class OxygenTherapyChange extends Model
{
    use DescribesOxygen;

    /** Flow rates accepted, in L/min (high-flow nasal oxygen goes up to about 60). */
    public const FLOW_MIN = 0.1;
    public const FLOW_MAX = 80;

    /** FiO2 accepted, in %: room air is 21. */
    public const FIO2_MIN = 21;
    public const FIO2_MAX = 100;

    /** SpO2 target limits accepted, in %. */
    public const TARGET_MIN = 70;
    public const TARGET_MAX = 100;

    /**
     * SpO2 targets in common use (BTS guideline for oxygen use in adults):
     * most patients, and those at risk of hypercapnic respiratory failure.
     */
    public const TARGET_PRESETS = [
        ['min' => 94, 'max' => 98, 'label' => 'Most patients'],
        ['min' => 88, 'max' => 92, 'label' => 'Risk of hypercapnia, e.g. COPD'],
    ];

    /**
     * Quick picks on the change form for each device (flow in L/min, FiO2 in %),
     * and what to record for it.
     */
    public const DEVICE_GUIDE = [
        'nasal_cannula' => ['flow' => [1, 2, 3, 4], 'fio2' => [], 'hint' => 'Usually 1 to 4 L/min, at most 6.'],
        'simple_mask' => ['flow' => [5, 6, 8, 10], 'fio2' => [], 'hint' => '5 to 10 L/min. Below 5 L/min the patient can rebreathe CO₂.'],
        'venturi_mask' => ['flow' => [], 'fio2' => [24, 28, 31, 35, 40, 60], 'hint' => 'Record the FiO₂ of the valve fitted: 24, 28, 31, 35, 40 or 60%.'],
        'non_rebreather' => ['flow' => [10, 12, 15], 'fio2' => [], 'hint' => '10 to 15 L/min, enough to keep the reservoir bag inflated.'],
        'high_flow_mask' => ['flow' => [10, 12, 15], 'fio2' => [], 'hint' => 'Record the flow rate, and the FiO₂ if the device sets one.'],
        'hfnc' => ['flow' => [30, 40, 50, 60], 'fio2' => [30, 40, 50, 60], 'hint' => 'Record both the flow rate and the FiO₂ set.'],
        'cpap' => ['flow' => [], 'fio2' => [30, 40, 50, 60], 'hint' => 'Record the FiO₂ set on the device.'],
        'bipap' => ['flow' => [], 'fio2' => [30, 40, 50, 60], 'hint' => 'Record the FiO₂ set, or the oxygen bled in (L/min).'],
        'tracheostomy' => ['flow' => [2, 4, 6, 8], 'fio2' => [28, 35, 40], 'hint' => 'Record the flow rate or the FiO₂ of the humidified oxygen.'],
        'ventilator' => ['flow' => [], 'fio2' => [30, 40, 50, 60, 80, 100], 'hint' => 'Record the FiO₂ set on the ventilator.'],
        'other' => ['flow' => [], 'fio2' => [], 'hint' => 'Record the flow rate or FiO₂, and name the device in the notes.'],
    ];

    protected $fillable = [
        'patient_id',
        'ward_id',
        'oxygen_delivery',
        'oxygen_flow_rate',
        'fio2_percent',
        'target_spo2_min',
        'target_spo2_max',
        'notes',
        'started_at',
        'recorded_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'oxygen_flow_rate' => 'decimal:1',
        'fio2_percent' => 'integer',
        'target_spo2_min' => 'integer',
        'target_spo2_max' => 'integer',
        'started_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * The SpO2 target as [min, max], or null when none was set.
     */
    public function target(): ?array
    {
        return $this->target_spo2_min !== null && $this->target_spo2_max !== null
            ? [$this->target_spo2_min, $this->target_spo2_max]
            : null;
    }

    /**
     * An SpO2 target for display, e.g. "94–98%".
     */
    public static function targetLabel(?array $target): ?string
    {
        return $target ? $target[0] . '–' . $target[1] . '%' : null;
    }
}
