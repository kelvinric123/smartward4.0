<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SugarReading extends Model
{
    protected $fillable = [
        'patient_id',
        'value',
        'frequency',
        'notes',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'value' => 'decimal:1',
        'recorded_at' => 'datetime',
    ];

    /**
     * Frequency options for HGT monitoring
     */
    public const FREQUENCY_BD = 'bd';      // Twice daily
    public const FREQUENCY_TDS = 'tds';    // Three times daily
    public const FREQUENCY_QID = 'qid';    // Four times daily
    public const FREQUENCY_PID = 'pid';    // As needed (pro re nata)

    public static function getFrequencyOptions(): array
    {
        return [
            self::FREQUENCY_BD => 'BD (Twice Daily)',
            self::FREQUENCY_TDS => 'TDS (Three Times Daily)',
            self::FREQUENCY_QID => 'QID (Four Times Daily)',
            self::FREQUENCY_PID => 'PRN (As Needed)',
        ];
    }

    public static function getFrequencyLabel(string $frequency): string
    {
        return self::getFrequencyOptions()[$frequency] ?? $frequency;
    }

    /**
     * Get the patient that owns the reading
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the user who recorded this reading
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Check if the value is in normal range (4.0 - 7.0 mmol/L fasting)
     */
    public function isNormal(): bool
    {
        return $this->value >= 4.0 && $this->value <= 7.0;
    }

    /**
     * Check if the value is low (hypoglycemia < 4.0 mmol/L)
     */
    public function isLow(): bool
    {
        return $this->value < 4.0;
    }

    /**
     * Check if the value is high (hyperglycemia > 11.0 mmol/L)
     */
    public function isHigh(): bool
    {
        return $this->value > 11.0;
    }

    /**
     * Get status color for display
     */
    public function getStatusColor(): string
    {
        if ($this->isLow()) {
            return 'red';
        }
        if ($this->isHigh()) {
            return 'orange';
        }
        return 'green';
    }

    /**
     * Scope to get latest readings first
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }
}
