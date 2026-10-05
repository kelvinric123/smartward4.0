<?php

namespace App\Models;

use App\Models\Concerns\DescribesOxygen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VitalSign extends Model
{
    use DescribesOxygen;
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'admission_id',
        'gateway_event_id',
        'gateway_id',
        'recorded_by',
        'systolic_bp',
        'diastolic_bp',
        'pulse_rate',
        'pulse_rate_min',
        'pulse_rate_max',
        'temperature',
        'spo2',
        'spo2_min',
        'spo2_max',
        'respiratory_rate',
        'oxygen_delivery',
        'oxygen_flow_rate',
        'fio2_percent',
        'reading_type',
        'notes',
        'recorded_at',
        'operator_id',
        'deleted_by',
    ];

    /**
     * How the patient is receiving oxygen at the time of the reading (value => label).
     * "room_air" means no supplemental oxygen.
     */
    const OXYGEN_ROOM_AIR = 'room_air';

    const OXYGEN_DELIVERY_OPTIONS = [
        'room_air' => 'Room Air',
        'nasal_cannula' => 'Nasal Cannula / Prongs',
        'simple_mask' => 'Simple Face Mask',
        'venturi_mask' => 'Venturi Mask',
        'non_rebreather' => 'Non-Rebreather Mask',
        'high_flow_mask' => 'High Flow Mask',
        'hfnc' => 'High Flow Nasal Cannula (HFNC)',
        'cpap' => 'CPAP',
        'bipap' => 'BiPAP / NIV',
        'tracheostomy' => 'Tracheostomy Mask',
        'ventilator' => 'Mechanical Ventilation',
        'other' => 'Other',
    ];

    /**
     * Short labels for the clinical chart, where a column is only a few characters wide
     */
    const OXYGEN_DELIVERY_SHORT = [
        'room_air' => 'RA',
        'nasal_cannula' => 'NP',
        'simple_mask' => 'FM',
        'venturi_mask' => 'VM',
        'non_rebreather' => 'NRM',
        'high_flow_mask' => 'HFM',
        'hfnc' => 'HFNC',
        'cpap' => 'CPAP',
        'bipap' => 'BIPAP',
        'tracheostomy' => 'TM',
        'ventilator' => 'MV',
        'other' => 'OTH',
    ];

    protected $casts = [
        'systolic_bp' => 'integer',
        'diastolic_bp' => 'integer',
        'pulse_rate' => 'integer',
        'pulse_rate_min' => 'integer',
        'pulse_rate_max' => 'integer',
        'temperature' => 'decimal:1',
        'spo2' => 'integer',
        'spo2_min' => 'integer',
        'spo2_max' => 'integer',
        'respiratory_rate' => 'integer',
        'oxygen_flow_rate' => 'decimal:1',
        'fio2_percent' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'operator_id');
    }

    /**
     * The gateway (cart) that submitted this reading, matched by gateway_id.
     */
    public function gatewayDevice(): BelongsTo
    {
        return $this->belongsTo(QmedGateway::class, 'gateway_id', 'gateway_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get the blood pressure as a formatted string (e.g., "120/80")
     */
    public function getBloodPressureAttribute(): ?string
    {
        if ($this->systolic_bp && $this->diastolic_bp) {
            return "{$this->systolic_bp}/{$this->diastolic_bp}";
        }
        return null;
    }

    /**
     * Format a value as a range ("95-96") when min/max differ, else the single
     * value ("95"), else the point fallback. Returns null when nothing is set.
     */
    private function rangeDisplay($min, $max, $point): ?string
    {
        if ($min !== null && $max !== null) {
            return ((int) $min === (int) $max) ? (string) (int) $min : ((int) $min) . '-' . ((int) $max);
        }
        return $point !== null ? (string) $point : null;
    }

    /**
     * SpO2 for display: "95-96" when a range was captured, else "95".
     */
    public function getSpo2DisplayAttribute(): ?string
    {
        return $this->rangeDisplay($this->spo2_min, $this->spo2_max, $this->spo2);
    }

    /**
     * Pulse rate for display: "80-85" when a range was captured, else "80".
     */
    public function getPulseRateDisplayAttribute(): ?string
    {
        return $this->rangeDisplay($this->pulse_rate_min, $this->pulse_rate_max, $this->pulse_rate);
    }

    /**
     * Entered by a person rather than pushed in by a monitor gateway.
     * Only these can be edited or removed from the patient details vitals tab.
     */
    public function isManualEntry(): bool
    {
        return $this->gateway_id === null && $this->gateway_event_id === null;
    }

    /**
     * Check if this is a full reading (all vital signs recorded)
     */
    public function getIsFullReadingAttribute(): bool
    {
        return $this->reading_type === 'full' || (
            $this->systolic_bp !== null &&
            $this->diastolic_bp !== null &&
            $this->pulse_rate !== null &&
            $this->temperature !== null &&
            $this->spo2 !== null &&
            $this->respiratory_rate !== null
        );
    }

    /**
     * Scope to filter by admission
     */
    public function scopeForAdmission($query, $admissionId)
    {
        return $query->where('admission_id', $admissionId);
    }

    /**
     * Scope to get latest vital signs first
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    /**
     * Scope to search by patient name or MRN
     */
    public function scopeSearchPatient($query, $search)
    {
        return $query->whereHas('patient', function ($q) use ($search) {
            $q->where('name', 'like', '%' . $search . '%')
                ->orWhere('mrn', 'like', '%' . $search . '%');
        });
    }
}





































