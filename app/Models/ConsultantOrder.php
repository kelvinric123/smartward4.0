<?php

namespace App\Models;

use App\Services\ShiftHandover;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order a consultant gave for a patient, for the ward nurses to carry out.
 *
 * While open it sits with one roster slot (shift_date + shift_code) and the
 * nurse the roster puts on the patient's bed for that slot. Passing it to the
 * next shift moves both and logs a handover.
 */
class ConsultantOrder extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    /** Urgency => label, most urgent first. */
    public const URGENCIES = [
        'stat' => 'STAT',
        'urgent' => 'Urgent',
        'routine' => 'Routine',
    ];

    protected $fillable = [
        'patient_id',
        'ward_id',
        'consultant_id',
        'consultant_name',
        'instruction',
        'urgency',
        'ordered_at',
        'assigned_nurse_id',
        'shift_date',
        'shift_code',
        'status',
        'closed_at',
        'closed_by',
        'outcome_note',
        'created_by',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'shift_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(Consultant::class);
    }

    public function assignedNurse(): BelongsTo
    {
        return $this->belongsTo(Nurse::class, 'assigned_nurse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Most recent first. */
    public function handovers(): HasMany
    {
        return $this->hasMany(ConsultantOrderHandover::class)->latest()->latest('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function urgencyLabel(): string
    {
        return self::URGENCIES[$this->urgency] ?? ucfirst((string) $this->urgency);
    }

    /** Whether the order sits with this roster slot (see ShiftHandover). */
    public function isInSlot(?array $slot): bool
    {
        return $slot !== null
            && $this->shift_code === $slot['code']
            && $this->shift_date?->toDateString() === $slot['date'];
    }

    /** "AM", "ON yesterday", "PM 19 Sep"; null before it has a slot. */
    public function slotLabel(?CarbonInterface $now = null): ?string
    {
        return $this->shift_code
            ? ShiftHandover::label($this->shift_code, $this->shift_date?->toDateString(), $now)
            : null;
    }

    /**
     * What the Consultant Orders tab shows for a patient: open orders in the
     * order they were assigned, closed ones most recent first, the roster
     * slots on now and next, and the consultants to pick from with the
     * patient's own listed first.
     */
    public static function tabFor(Patient $patient): array
    {
        $orders = static::where('patient_id', $patient->id)
            ->with([
                'assignedNurse:id,name',
                'createdBy:id,name',
                'closedBy:id,name',
                'handovers.fromNurse:id,name',
                'handovers.toNurse:id,name',
                'handovers.handedOverBy:id,name',
            ])
            ->get();

        $urgencyRank = array_flip(array_keys(self::URGENCIES));

        $open = $orders->where('status', self::STATUS_OPEN)
            ->sort(fn (self $a, self $b) => [$a->ordered_at->getTimestamp(), $urgencyRank[$a->urgency] ?? 9, $a->id]
                <=> [$b->ordered_at->getTimestamp(), $urgencyRank[$b->urgency] ?? 9, $b->id])
            ->values();

        $closed = $orders->where('status', '!=', self::STATUS_OPEN)
            ->sortByDesc(fn (self $order) => $order->closed_at?->getTimestamp() ?? 0)
            ->values();

        // Attending first, then consulting and referring, then the one on the patient record
        $patientConsultantIds = $patient->activeCareProviders()
            ->whereNotNull('consultant_id')
            ->orderByRaw("FIELD(role, 'attending', 'consulting', 'referring')")
            ->pluck('consultant_id')
            ->push($patient->consultant_id)
            ->filter()
            ->unique()
            ->values();

        return [
            'open' => $open,
            'closed' => $closed,
            'slots' => ShiftHandover::slotsFor($patient),
            'consultants' => Consultant::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'patientConsultantIds' => $patientConsultantIds->all(),
            'defaultConsultantId' => $patientConsultantIds->first(),
        ];
    }
}
