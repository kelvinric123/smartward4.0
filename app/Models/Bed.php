<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bed extends Model
{
    use HasFactory;

    /** A bed out of service: nobody can be admitted to, prebooked into or transferred to it. */
    public const STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'ward_id',
        'section',
        'bed_number',
        'bed_id',
        'bed_display_name',
        'status',
        'nurse_id',
        'anaesthetist_id',
        'patient_id',
        'is_active',
        'cplus_bed_id',
        'cplus_room_no',
        'cplus_status',
        'cplus_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cplus_synced_at' => 'datetime',
    ];

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }

    public function anaesthetist()
    {
        return $this->belongsTo(Anaesthetist::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultants()
    {
        return $this->belongsToMany(Consultant::class, 'bed_consultant');
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    /**
     * The patient in this bed or booked into it: admitted, pending discharge,
     * or prebooked (including a prebook waiting for a discharge), in that
     * order. Read from the patients table, which is what the bed boxes go by,
     * rather than this bed's own patient_id.
     */
    public function occupant(): ?Patient
    {
        $statuses = [
            Patient::STATUS_ADMITTED,
            Patient::STATUS_PENDING_DISCHARGE,
            Patient::STATUS_PREBOOK,
            Patient::STATUS_PREBOOK_PENDING,
        ];

        return Patient::where('ward_id', $this->ward_id)
            ->where('bed_number', $this->bed_number)
            ->where('is_active', true)
            ->whereIn('status', $statuses)
            ->orderByRaw('FIELD(status, ' . implode(', ', array_fill(0, count($statuses), '?')) . ')', $statuses)
            ->first();
    }

    /** Whether the bed in a ward with this number is under maintenance. */
    public static function isUnderMaintenanceAt($wardId, ?string $bedNumber): bool
    {
        return $bedNumber !== null && static::where('ward_id', $wardId)
            ->where('bed_number', $bedNumber)
            ->where('status', self::STATUS_MAINTENANCE)
            ->exists();
    }
}
