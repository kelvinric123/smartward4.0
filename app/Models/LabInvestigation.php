<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One lab investigation (a test or panel) of an order placed in the HIS: what was ordered,
 * by whom and how urgently, its results, and when the ward should review them.
 *
 * Rows come from the HIS feed (POST /api/his/lab-investigations) or, when switched on in
 * Settings > Patient Additional Info, from demo data (source 'sample').
 */
class LabInvestigation extends Model
{
    public const SOURCE_HIS = 'his';
    public const SOURCE_SAMPLE = 'sample';

    public const CATEGORIES = [
        'haematology' => 'Haematology',
        'biochemistry' => 'Biochemistry',
        'microbiology' => 'Microbiology',
        'coagulation' => 'Coagulation',
        'blood_gas' => 'Blood Gas',
        'serology' => 'Serology / Immunology',
        'urinalysis' => 'Urinalysis',
        'blood_bank' => 'Blood Bank',
        'other' => 'Other',
    ];

    /** Priority => [label, review window in minutes after the result (or order) when the HIS sends no review time]. */
    public const PRIORITIES = [
        'stat' => ['STAT', 60],
        'urgent' => ['Urgent', 240],
        'routine' => ['Routine', 1440],
    ];

    public const STATUSES = [
        'ordered' => 'Ordered',
        'collected' => 'Specimen collected',
        'in_progress' => 'In progress',
        'resulted' => 'Resulted',
        'cancelled' => 'Cancelled',
    ];

    /** Result flags (HL7 OBX-8 style) that make a result critical rather than just abnormal. */
    public const CRITICAL_FLAGS = ['HH', 'LL', 'AA', 'C'];

    protected $fillable = [
        'patient_id',
        'source',
        'order_no',
        'test_code',
        'test_name',
        'category',
        'specimen',
        'priority',
        'ordered_by',
        'ordered_at',
        'status',
        'collected_at',
        'resulted_at',
        'results',
        'comment',
        'review_due_at',
        'reviewed_at',
        'reviewed_by',
        'reviewed_by_name',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'collected_at' => 'datetime',
        'resulted_at' => 'datetime',
        'review_due_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'results' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * What Patient Details shows for a patient: HIS rows always, sample rows only while
     * sample data is switched on. Unreviewed results first, then newest orders.
     */
    public static function forPatient(Patient $patient, bool $includeSample): Collection
    {
        return static::query()
            ->where('patient_id', $patient->id)
            ->when(! $includeSample, fn (Builder $query) => $query->where('source', self::SOURCE_HIS))
            ->orderByDesc('ordered_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (self $lab) => $lab->reviewState() === 'overdue' ? 0 : ($lab->awaitingReview() ? 1 : 2))
            ->values();
    }

    public function isSample(): bool
    {
        return $this->source === self::SOURCE_SAMPLE;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority][0] ?? ucfirst((string) $this->priority);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isResulted(): bool
    {
        return $this->status === 'resulted';
    }

    public function awaitingReview(): bool
    {
        return $this->isResulted() && ! $this->reviewed_at;
    }

    /** When the result should be reviewed: as sent by the HIS, else the priority's window after the result (or order). */
    public function reviewDueAt(): ?CarbonInterface
    {
        if ($this->status === 'cancelled') {
            return null;
        }

        if ($this->review_due_at) {
            return $this->review_due_at;
        }

        $minutes = self::PRIORITIES[$this->priority][1] ?? self::PRIORITIES['routine'][1];

        return ($this->resulted_at ?? $this->ordered_at)?->copy()->addMinutes($minutes);
    }

    /**
     * reviewed | overdue | due_soon (within the hour) | due | awaiting_result | result_late | none
     */
    public function reviewState(?CarbonInterface $now = null): string
    {
        $now ??= now();
        $due = $this->reviewDueAt();

        if ($this->reviewed_at) {
            return 'reviewed';
        }
        if (! $due) {
            return 'none';
        }
        if (! $this->isResulted()) {
            return $due->lessThan($now) ? 'result_late' : 'awaiting_result';
        }
        if ($due->lessThan($now)) {
            return 'overdue';
        }

        return $due->lessThanOrEqualTo($now->copy()->addHour()) ? 'due_soon' : 'due';
    }

    /** 'critical' when any result carries a critical flag, 'abnormal' for any other flag, else null. */
    public function resultFlag(): ?string
    {
        $flags = collect($this->results ?? [])
            ->map(fn ($row) => strtoupper(trim((string) ($row['flag'] ?? ''))))
            ->filter(fn ($flag) => $flag !== '' && $flag !== 'N');

        if ($flags->isEmpty()) {
            return null;
        }

        return $flags->intersect(self::CRITICAL_FLAGS)->isNotEmpty() ? 'critical' : 'abnormal';
    }
}
