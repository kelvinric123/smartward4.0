<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Infusion extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'infusion_pump_id',
        'medication_name',
        'medication_code',
        'drug_concentration',
        'drug_concentration_unit',
        'care_area',
        'total_volume',
        'infused_volume',
        'remaining_volume',
        'flow_rate',
        'dose_rate',
        'dose_unit',
        'duration_minutes',
        'elapsed_minutes',
        'remaining_minutes',
        'status',
        'delivery_mode',
        'syringe_size',
        'syringe_manufacturer',
        'alarm_type',
        'alarm_message',
        'alarm_priority',
        'is_warning',
        'warning_threshold_minutes',
        'started_at',
        'completed_at',
        'last_updated_at',
        'notes',
    ];

    protected $casts = [
        'total_volume' => 'decimal:2',
        'infused_volume' => 'decimal:2',
        'remaining_volume' => 'decimal:2',
        'flow_rate' => 'decimal:2',
        'dose_rate' => 'decimal:4',
        'drug_concentration' => 'decimal:4',
        'syringe_size' => 'decimal:2',
        'is_warning' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_STOPPED = 'stopped';
    const STATUS_ALARMING = 'alarming';

    /**
     * Get the patient for this infusion.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the infusion pump for this infusion.
     */
    public function infusionPump(): BelongsTo
    {
        return $this->belongsTo(InfusionPump::class);
    }

    /**
     * Scope for active infusions.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_RUNNING, self::STATUS_PAUSED, self::STATUS_ALARMING]);
    }

    /**
     * Scope for running infusions.
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    /**
     * Scope for completed infusions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for infusions with warnings.
     */
    public function scopeWithWarnings($query)
    {
        return $query->where('is_warning', true);
    }

    /**
     * Scope for alarming infusions.
     */
    public function scopeAlarming($query)
    {
        return $query->where('status', self::STATUS_ALARMING);
    }

    /**
     * Scope for infusions in a specific ward.
     */
    public function scopeInWard($query, $wardId)
    {
        return $query->whereHas('patient', function ($q) use ($wardId) {
            $q->where('ward_id', $wardId);
        });
    }

    /**
     * Check if infusion is about to finish.
     */
    public function checkWarningStatus(): bool
    {
        if ($this->status !== self::STATUS_RUNNING) {
            return false;
        }

        $threshold = $this->warning_threshold_minutes ?? 15;
        return $this->remaining_minutes !== null && $this->remaining_minutes <= $threshold;
    }

    /**
     * Update warning status based on remaining time.
     */
    public function updateWarningStatus(): void
    {
        $isWarning = $this->checkWarningStatus();
        
        if ($this->is_warning !== $isWarning) {
            $this->update(['is_warning' => $isWarning]);
        }
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentAttribute(): float
    {
        if (!$this->total_volume || $this->total_volume <= 0) {
            return 0;
        }

        return min(100, round(($this->infused_volume / $this->total_volume) * 100, 1));
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_RUNNING => 'green',
            self::STATUS_PAUSED => 'yellow',
            self::STATUS_COMPLETED => 'blue',
            self::STATUS_STOPPED => 'gray',
            self::STATUS_ALARMING => 'red',
            default => 'gray',
        };
    }

    /**
     * Get formatted remaining time.
     */
    public function getFormattedRemainingTimeAttribute(): string
    {
        if ($this->remaining_minutes === null) {
            return '--:--';
        }

        $hours = floor($this->remaining_minutes / 60);
        $mins = $this->remaining_minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    /**
     * Get formatted concentration with unit.
     */
    public function getFormattedConcentrationAttribute(): ?string
    {
        if ($this->drug_concentration === null) {
            return null;
        }

        $unit = $this->drug_concentration_unit ?: 'mg/mL';
        return number_format($this->drug_concentration, 2) . ' ' . $unit;
    }

    /**
     * Get formatted dose rate with unit.
     */
    public function getFormattedDoseRateAttribute(): ?string
    {
        if ($this->dose_rate === null) {
            return null;
        }

        $unit = $this->dose_unit ?: 'units/hr';
        return number_format($this->dose_rate, 2) . ' ' . $unit;
    }

    /**
     * Get formatted syringe info.
     */
    public function getFormattedSyringeAttribute(): ?string
    {
        if ($this->syringe_size === null) {
            return null;
        }

        $info = number_format($this->syringe_size, 0) . ' mL';
        if ($this->syringe_manufacturer) {
            $info .= ' (' . $this->syringe_manufacturer . ')';
        }
        return $info;
    }

    /**
     * Get delivery mode display name.
     */
    public function getDeliveryModeDisplayAttribute(): string
    {
        return match($this->delivery_mode) {
            'continuous' => 'Continuous',
            'bolus' => 'Bolus',
            'intermittent' => 'Intermittent',
            'loading' => 'Loading Dose',
            'tapering' => 'Tapering',
            default => ucfirst($this->delivery_mode ?? 'Standard'),
        };
    }

    /**
     * Get alarm priority display.
     */
    public function getAlarmPriorityDisplayAttribute(): string
    {
        return match($this->alarm_priority) {
            'high' => 'High Priority',
            'medium' => 'Medium Priority',
            'low' => 'Low Priority',
            'technical' => 'Technical',
            default => ucfirst($this->alarm_priority ?? ''),
        };
    }

    /**
     * Get alarm priority color for UI.
     */
    public function getAlarmPriorityColorAttribute(): string
    {
        return match($this->alarm_priority) {
            'high' => 'red',
            'medium' => 'orange',
            'low' => 'yellow',
            'technical' => 'blue',
            default => 'gray',
        };
    }

    /**
     * Check if this is a high-priority alarm.
     */
    public function isHighPriorityAlarm(): bool
    {
        return $this->status === self::STATUS_ALARMING && $this->alarm_priority === 'high';
    }

    /**
     * Get estimated completion time.
     */
    public function getEstimatedCompletionAttribute(): ?\Carbon\Carbon
    {
        if ($this->remaining_minutes === null || $this->status !== self::STATUS_RUNNING) {
            return null;
        }

        return now()->addMinutes($this->remaining_minutes);
    }
}
































