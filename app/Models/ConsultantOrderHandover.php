<?php

namespace App\Models;

use App\Services\ShiftHandover;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pass of a consultant order from one roster slot, and its nurse, to the
 * next. Kept so it stays clear who held an order during each shift.
 */
class ConsultantOrderHandover extends Model
{
    protected $fillable = [
        'consultant_order_id',
        'from_nurse_id',
        'from_shift_date',
        'from_shift_code',
        'to_nurse_id',
        'to_shift_date',
        'to_shift_code',
        'note',
        'handed_over_by',
    ];

    protected $casts = [
        'from_shift_date' => 'date',
        'to_shift_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ConsultantOrder::class, 'consultant_order_id');
    }

    public function fromNurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'from_nurse_id');
    }

    public function toNurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'to_nurse_id');
    }

    public function handedOverBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_over_by');
    }

    public function fromLabel(?CarbonInterface $now = null): ?string
    {
        return $this->from_shift_code
            ? ShiftHandover::label($this->from_shift_code, $this->from_shift_date?->toDateString(), $now)
            : null;
    }

    public function toLabel(?CarbonInterface $now = null): string
    {
        return ShiftHandover::label($this->to_shift_code, $this->to_shift_date?->toDateString(), $now);
    }
}
