<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shift handover of a patient from one nurse to the next (nurse_app).
 *
 * Each handover is addressed to one shift instance on the ward roster:
 * `to_shift` (shift code) + `to_shift_date` (the date that shift starts).
 */
class NurseHandover extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_RECEIVED = 'received';

    const CONDITION_STATUSES = ['stable', 'improving', 'deteriorating', 'critical'];

    protected $fillable = [
        'patient_id',
        'ward_id',
        'bed_id',
        'from_nurse_id',
        'to_nurse_id',
        'from_shift',
        'to_shift',
        'to_shift_date',
        'condition_status',
        'patient_condition',
        'nursing_plan',
        'ews',
        'status',
        'received_by_nurse_id',
        'received_at',
    ];

    protected $casts = [
        'ews' => 'integer',
        'to_shift_date' => 'date',
        'received_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function fromNurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'from_nurse_id');
    }

    public function toNurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'to_nurse_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'received_by_nurse_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
