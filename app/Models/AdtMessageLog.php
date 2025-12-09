<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdtMessageLog extends Model
{
    protected $fillable = [
        'adt_configuration_id',
        'message_type',
        'event_type',
        'event_description',
        'message_control_id',
        'sending_application',
        'sending_facility',
        'patient_id',
        'patient_name',
        'patient_mrn',
        'visit_number',
        'assigned_location',
        'source_ip',
        'raw_message',
        'parsed_data',
        'status',
        'error_message',
        'action_taken',
        'patient_id_ref',
        'bed_id_ref',
        'message_datetime',
        'processing_time_ms',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'action_taken' => 'array',
        'message_datetime' => 'datetime',
    ];

    /**
     * ADT Event Types
     */
    public const EVENT_TYPES = [
        'A01' => 'Admit/Visit Notification',
        'A02' => 'Transfer a Patient',
        'A03' => 'Discharge/End Visit',
        'A04' => 'Register a Patient',
        'A05' => 'Pre-Admit a Patient',
        'A06' => 'Change Outpatient to Inpatient',
        'A07' => 'Change Inpatient to Outpatient',
        'A08' => 'Update Patient Information',
        'A09' => 'Patient Departing - Tracking',
        'A10' => 'Patient Arriving - Tracking',
        'A11' => 'Cancel Admit/Visit Notification',
        'A12' => 'Cancel Transfer',
        'A13' => 'Cancel Discharge/End Visit',
        'A14' => 'Pending Admit',
        'A15' => 'Pending Transfer',
        'A16' => 'Pending Discharge',
        'A17' => 'Swap Patients',
        'A18' => 'Merge Patient Information',
        'A19' => 'Patient Query',
        'A20' => 'Bed Status Update',
        'A21' => 'Patient Goes on a Leave of Absence',
        'A22' => 'Patient Returns from a Leave of Absence',
        'A23' => 'Delete a Patient Record',
        'A24' => 'Link Patient Information',
        'A25' => 'Cancel Pending Discharge',
        'A26' => 'Cancel Pending Transfer',
        'A27' => 'Cancel Pending Admit',
        'A28' => 'Add Person Information',
        'A29' => 'Delete Person Information',
        'A30' => 'Merge Person Information',
        'A31' => 'Update Person Information',
    ];

    /**
     * Get the ADT configuration.
     */
    public function adtConfiguration(): BelongsTo
    {
        return $this->belongsTo(AdtConfiguration::class);
    }

    /**
     * Get the referenced patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id_ref');
    }

    /**
     * Get the referenced bed.
     */
    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'bed_id_ref');
    }

    /**
     * Get the event description.
     */
    public function getEventDescriptionAttribute($value): string
    {
        if ($value) {
            return $value;
        }
        return self::EVENT_TYPES[$this->event_type] ?? 'Unknown Event';
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'received' => 'blue',
            'processed' => 'green',
            'failed' => 'red',
            'ignored' => 'gray',
            'unmapped' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by event type.
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}





