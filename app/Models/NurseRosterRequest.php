<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A leave or shift-swap request made from the nurse app.
 *
 * Leave goes straight to the nurse manager. A swap first goes to the colleague
 * asked to take the shift; once they accept, it goes to the nurse manager.
 * Approving it on the AI Nurse Schedule books the leave, or swaps the two
 * nurses' shifts that day (roster and beds). See App\Services\NurseScheduling\RosterRequests.
 */
class NurseRosterRequest extends Model
{
    public const TYPE_LEAVE = 'leave';
    public const TYPE_SWAP = 'swap';

    public const STATUS_AWAITING_COLLEAGUE = 'awaiting_colleague';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    /** Still waiting for someone: the colleague or the nurse manager. */
    public const OPEN_STATUSES = [self::STATUS_AWAITING_COLLEAGUE, self::STATUS_PENDING];

    public const STATUS_LABELS = [
        self::STATUS_AWAITING_COLLEAGUE => 'Waiting for colleague',
        self::STATUS_PENDING => 'Waiting for approval',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_DECLINED => 'Declined',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'ward_id',
        'nurse_id',
        'type',
        'leave_type',
        'start_date',
        'end_date',
        'shift_date',
        'shift',
        'colleague_id',
        'colleague_shift',
        'colleague_responded_at',
        'note',
        'status',
        'decided_by',
        'decided_by_name',
        'decided_at',
        'decision_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'shift_date' => 'date',
        'colleague_responded_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    public function colleague(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'colleague_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isSwap(): bool
    {
        return $this->type === self::TYPE_SWAP;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * "Annual leave, 12 Oct to 14 Oct", or "Swap PM on Mon 13 Oct with Mei Ling (her AM)".
     */
    public function summary(): string
    {
        if (!$this->isSwap()) {
            $label = NurseLeave::TYPES[$this->leave_type]['label'] ?? 'Leave';
            $from = $this->start_date?->format('D j M');

            return $label . ', ' . $from . ($this->end_date && !$this->end_date->equalTo($this->start_date) ? ' to ' . $this->end_date->format('D j M') : '');
        }

        return ($this->colleague_shift ? 'Swap ' : 'Give away ') . $this->shift . ' on ' . $this->shift_date?->format('D j M')
            . ' with ' . ($this->colleague?->name ?? 'a colleague')
            . ($this->colleague_shift ? ' (for their ' . $this->colleague_shift . ')' : '');
    }
}
