<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dose documented against a medication order: given, or not given
 * (held / refused) with a reason. Either way it answers the dose that was
 * due, so the next one is scheduled from it.
 */
class MedicationAdministration extends Model
{
    public const STATUS_GIVEN = 'given';
    public const STATUS_HELD = 'held';
    public const STATUS_REFUSED = 'refused';

    public const STATUSES = [
        self::STATUS_GIVEN => 'Given',
        self::STATUS_HELD => 'Held',
        self::STATUS_REFUSED => 'Refused',
    ];

    protected $fillable = [
        'patient_medication_id',
        'status',
        'administered_at',
        'due_at',
        'dose_amount',
        'dose_unit',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'due_at' => 'datetime',
        'dose_amount' => 'decimal:3',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PatientMedication::class, 'patient_medication_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_GIVEN => 'bg-emerald-100 text-emerald-800',
            self::STATUS_HELD => 'bg-amber-100 text-amber-800',
            self::STATUS_REFUSED => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /**
     * Minutes after the due time this was recorded, or null when it was on
     * time (or nothing was due, as with PRN doses).
     */
    public function minutesLate(): ?int
    {
        if (!$this->due_at || !$this->administered_at) {
            return null;
        }

        $late = intdiv($this->administered_at->getTimestamp() - $this->due_at->getTimestamp(), 60);

        return $late > 0 ? $late : null;
    }
}
