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
        'device_id',
        'medication_name',
        'flow_rate',
        'total_volume',
        'infused_volume',
        'remaining_volume',
        'pump_status',
        'alarm_type',
        'alarm_message',
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
}
