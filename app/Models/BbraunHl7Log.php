<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BbraunHl7Log extends Model
{
    use HasFactory;

    protected $table = 'bbraun_hl7_logs';

    protected $fillable = [
        'message_control_id',
        'message_type',
        'event_type',
        'sending_application',
        'sending_facility',
        'patient_mrn',
        'patient_name',
        'ward',
        'room',
        'bed',
        'device_id',
        'device_uuid',
        'pump_model',
        'medication_name',
        'drug_concentration',
        'flow_rate',
        'total_volume',
        'infused_volume',
        'remaining_volume',
        'remaining_minutes',
        'dose_rate',
        'dose_unit',
        'syringe_size',
        'delivery_mode',
        'pump_status',
        'alarm_type',
        'alarm_message',
        'alarm_priority',
        'alarm_state',
        'power_status',
        'battery_percent',
        'battery_minutes_remaining',
        'wifi_strength',
        'device_ip',
        'raw_message',
        'parsed_data',
        'source_ip',
        'status',
        'error_message',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'flow_rate' => 'decimal:2',
        'total_volume' => 'decimal:2',
        'infused_volume' => 'decimal:2',
        'remaining_volume' => 'decimal:2',
        'drug_concentration' => 'decimal:4',
        'dose_rate' => 'decimal:4',
        'syringe_size' => 'decimal:2',
        'remaining_minutes' => 'integer',
        'battery_percent' => 'integer',
        'battery_minutes_remaining' => 'integer',
        'wifi_strength' => 'integer',
    ];

    /**
     * Get the message type description.
     */
    public function getMessageTypeDescriptionAttribute(): string
    {
        $types = [
            'ORU' => 'Observation Result (Pump Status)',
            'ORM' => 'Order Message (New Infusion)',
            'ADT' => 'Patient Information Update',
            'RAS' => 'Pharmacy/Treatment Administration',
            'RDE' => 'Pharmacy/Treatment Encoded Order',
            'RGV' => 'Pharmacy/Treatment Give',
        ];

        $msgType = explode('^', $this->message_type)[0] ?? $this->message_type;
        return $types[$msgType] ?? $this->message_type ?? 'Unknown';
    }

    /**
     * Get formatted pump status with color class.
     */
    public function getPumpStatusColorAttribute(): string
    {
        return match ($this->pump_status) {
            'running' => 'bg-green-100 text-green-700',
            'paused' => 'bg-yellow-100 text-yellow-700',
            'stopped' => 'bg-gray-100 text-gray-700',
            'completed' => 'bg-blue-100 text-blue-700',
            'alarming' => 'bg-red-100 text-red-700',
            'pending' => 'bg-gray-100 text-gray-600',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Get status color class.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'received' => 'bg-green-100 text-green-700',
            'processed' => 'bg-blue-100 text-blue-700',
            'error' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Scope for filtering by message type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('message_type', 'like', $type . '%');
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by pump status.
     */
    public function scopeWithPumpStatus($query, string $pumpStatus)
    {
        return $query->where('pump_status', $pumpStatus);
    }

    /**
     * Scope for alarm messages.
     */
    public function scopeAlarms($query)
    {
        return $query->where('alarm_state', 'active')
            ->orWhereNotNull('alarm_message');
    }

    /**
     * Scope for power/battery messages.
     */
    public function scopePowerStatus($query)
    {
        return $query->whereNotNull('power_status')
            ->orWhereNotNull('battery_percent');
    }

    /**
     * Get alarm priority color.
     */
    public function getAlarmPriorityColorAttribute(): string
    {
        return match ($this->alarm_priority) {
            'high' => 'bg-red-100 text-red-700',
            'medium' => 'bg-orange-100 text-orange-700',
            'low' => 'bg-yellow-100 text-yellow-700',
            'technical' => 'bg-blue-100 text-blue-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Get power status color.
     */
    public function getPowerStatusColorAttribute(): string
    {
        if ($this->power_status === 'mains') {
            return 'bg-green-100 text-green-700';
        }

        if ($this->battery_percent !== null) {
            if ($this->battery_percent >= 50) {
                return 'bg-green-100 text-green-700';
            } elseif ($this->battery_percent >= 20) {
                return 'bg-yellow-100 text-yellow-700';
            } else {
                return 'bg-red-100 text-red-700';
            }
        }

        return 'bg-gray-100 text-gray-600';
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

        return sprintf('%d:%02d', $hours, $mins);
    }

    /**
     * Get formatted battery time.
     */
    public function getFormattedBatteryTimeAttribute(): string
    {
        if ($this->battery_minutes_remaining === null) {
            return '--:--';
        }

        $hours = floor($this->battery_minutes_remaining / 60);
        $mins = $this->battery_minutes_remaining % 60;

        return sprintf('%d:%02d', $hours, $mins);
    }

    /**
     * Check if this is an alarm message.
     */
    public function isAlarmMessage(): bool
    {
        return $this->alarm_state === 'active' || !empty($this->alarm_message);
    }

    /**
     * Check if pump is on battery.
     */
    public function isOnBattery(): bool
    {
        return $this->power_status === 'battery';
    }

    /**
     * Get the infusion data from parsed_data.
     */
    public function getInfusionDataAttribute(): array
    {
        return $this->parsed_data['infusion_data'] ?? [];
    }
}
