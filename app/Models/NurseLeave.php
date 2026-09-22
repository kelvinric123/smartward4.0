<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Days a nurse is away, inclusive of both dates. The AI Nurse Schedule never
 * rosters a nurse on a leave day.
 */
class NurseLeave extends Model
{
    /** Type => label and the short code shown in the roster grid. */
    public const TYPES = [
        'annual' => ['label' => 'Annual leave', 'code' => 'AL'],
        'medical' => ['label' => 'Medical leave', 'code' => 'MC'],
        'emergency' => ['label' => 'Emergency leave', 'code' => 'EL'],
        'public_holiday' => ['label' => 'Public holiday off', 'code' => 'PH'],
        'training' => ['label' => 'Training / course', 'code' => 'TR'],
        'off_request' => ['label' => 'Requested day off', 'code' => 'RO'],
    ];

    protected $fillable = [
        'nurse_id',
        'type',
        'start_date',
        'end_date',
        'note',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function nurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Leave that covers any part of the period. */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString());
    }

    public function covers(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $this->start_date->toDateString() <= $day && $this->end_date->toDateString() >= $day;
    }

    public function label(): string
    {
        return self::TYPES[$this->type]['label'] ?? ucfirst(str_replace('_', ' ', (string) $this->type));
    }

    public function code(): string
    {
        return self::TYPES[$this->type]['code'] ?? 'LV';
    }

    public function days(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }
}
