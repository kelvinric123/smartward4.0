<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A medication order for one patient, monitored by interval: the next dose
 * falls due one interval after the last dose recorded (or at the start time
 * before any dose), and the order is overdue once that time has passed.
 */
class PatientMedication extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_COMPLETED = 'completed';

    /** The key of this feature's tab in the per-user Patient Details tab settings. */
    public const SETTINGS_TAB = 'medications';

    /** A scheduled dose shows as "due soon" this long before it falls due. */
    public const DUE_SOON_MINUTES = 30;

    /** Bounds for a custom interval or a PRN minimum gap, in hours. */
    public const MIN_INTERVAL_HOURS = 0.5;
    public const MAX_INTERVAL_HOURS = 168;

    public const ROUTES = [
        'PO' => 'Oral (PO)',
        'IV' => 'Intravenous (IV)',
        'IM' => 'Intramuscular (IM)',
        'SC' => 'Subcutaneous (SC)',
        'SL' => 'Sublingual (SL)',
        'NEB' => 'Nebulised (NEB)',
        'INH' => 'Inhaled (INH)',
        'NG' => 'Via NG tube (NG)',
        'PR' => 'Rectal (PR)',
        'TOP' => 'Topical (TOP)',
    ];

    public const DOSE_UNITS = ['mg', 'g', 'mcg', 'units', 'mL', 'mmol', 'tab', 'cap', 'puff', 'drop', 'sachet'];

    /**
     * Frequency code => label, short form and the hours between doses.
     * STAT is a single dose due straight away. PRN has no schedule, only an
     * optional minimum gap between doses. Custom takes its interval from the
     * order itself.
     */
    public const FREQUENCIES = [
        'stat' => ['label' => 'STAT - single dose, give now', 'short' => 'STAT', 'hours' => null],
        'od' => ['label' => 'OD - once daily (every 24 h)', 'short' => 'OD', 'hours' => 24],
        'on' => ['label' => 'ON - every night (every 24 h)', 'short' => 'ON', 'hours' => 24],
        'bd' => ['label' => 'BD - twice daily (every 12 h)', 'short' => 'BD', 'hours' => 12],
        'tds' => ['label' => 'TDS - three times daily (every 8 h)', 'short' => 'TDS', 'hours' => 8],
        'qid' => ['label' => 'QID - four times daily (every 6 h)', 'short' => 'QID', 'hours' => 6],
        'q4h' => ['label' => 'Q4H - every 4 hours', 'short' => 'Q4H', 'hours' => 4],
        'custom' => ['label' => 'Every ... hours (set the interval)', 'short' => null, 'hours' => null],
        'prn' => ['label' => 'PRN - when required', 'short' => 'PRN', 'hours' => null],
    ];

    protected $fillable = [
        'patient_id',
        'ward_id',
        'medication_id',
        'medication_name',
        'dose_amount',
        'dose_unit',
        'route',
        'frequency',
        'interval_minutes',
        'is_high_alert',
        'instructions',
        'status',
        'start_at',
        'next_due_at',
        'last_given_at',
        'stopped_at',
        'stopped_by',
        'stop_reason',
        'created_by',
    ];

    protected $casts = [
        'dose_amount' => 'decimal:3',
        'interval_minutes' => 'integer',
        'is_high_alert' => 'boolean',
        'start_at' => 'datetime',
        'next_due_at' => 'datetime',
        'last_given_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stoppedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'stopped_by');
    }

    /**
     * Whether a user has medication monitoring switched on. It is one of the
     * per-user Patient Details tabs and is off until switched on in Settings.
     */
    public static function monitoringEnabledFor(?WardDashboardSetting $settings): bool
    {
        return (bool) (($settings?->patient_details_tabs ?? [])[self::SETTINGS_TAB] ?? false);
    }

    /**
     * Minutes between doses for a frequency. Fixed frequencies carry their
     * own; custom and PRN use the hours entered on the order (PRN may have
     * none); STAT has no interval.
     */
    public static function intervalMinutesFor(string $frequency, $hours = null): ?int
    {
        $fixed = self::FREQUENCIES[$frequency]['hours'] ?? null;
        if ($fixed !== null) {
            return (int) round($fixed * 60);
        }

        if (in_array($frequency, ['custom', 'prn'], true) && is_numeric($hours) && $hours > 0) {
            return (int) round($hours * 60);
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isStat(): bool
    {
        return $this->frequency === 'stat';
    }

    public function isPrn(): bool
    {
        return $this->frequency === 'prn';
    }

    /** "1", "2.5", "0.125": the stored decimal without trailing zeros. */
    public static function formatAmount($amount): string
    {
        $formatted = rtrim(rtrim(number_format((float) $amount, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /** "8 h", "30 min", "1.5 h". */
    public static function formatInterval(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        return ($minutes % 60 === 0 ? intdiv($minutes, 60) : round($minutes / 60, 1)) . ' h';
    }

    /** "25m", "1h 05m", "2d 3h". */
    public static function formatDuration(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes < 60) {
            return $minutes . 'm';
        }

        if ($minutes < 1440) {
            return intdiv($minutes, 60) . 'h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) . 'm';
        }

        return intdiv($minutes, 1440) . 'd ' . intdiv($minutes % 1440, 60) . 'h';
    }

    /** Whole minutes from one moment to another (negative when $to is earlier). */
    public static function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return intdiv($to->getTimestamp() - $from->getTimestamp(), 60);
    }

    public function doseLabel(): string
    {
        return self::formatAmount($this->dose_amount) . ' ' . $this->dose_unit;
    }

    public function frequencyShort(): string
    {
        if ($this->frequency === 'custom') {
            $hours = $this->interval_minutes ? round($this->interval_minutes / 60, 1) : '?';

            return 'Q' . $hours . 'H';
        }

        return self::FREQUENCIES[$this->frequency]['short'] ?? strtoupper((string) $this->frequency);
    }

    /** "TDS (every 8 h)", "Every 36 h", "PRN, at least 4 h apart", "STAT (single dose)". */
    public function frequencyLabel(): string
    {
        if ($this->isStat()) {
            return 'STAT (single dose)';
        }

        if ($this->isPrn()) {
            return $this->interval_minutes
                ? 'PRN, at least ' . self::formatInterval($this->interval_minutes) . ' apart'
                : 'PRN (when required)';
        }

        if ($this->frequency === 'custom') {
            return 'Every ' . self::formatInterval((int) $this->interval_minutes);
        }

        return $this->frequencyShort() . ' (every ' . self::formatInterval((int) $this->interval_minutes) . ')';
    }

    public function routeLabel(): string
    {
        return self::ROUTES[$this->route] ?? (string) $this->route;
    }

    /** "1 g IV TDS". */
    public function summary(): string
    {
        return $this->doseLabel() . ' ' . $this->route . ' ' . $this->frequencyShort();
    }

    /**
     * Where the order stands right now:
     * overdue   - the next dose's time has passed (a STAT dose not yet given)
     * due_soon  - due within DUE_SOON_MINUTES
     * scheduled - due later
     * prn       - when required, never overdue
     * stopped / completed - no longer active
     */
    public function dueState(?CarbonInterface $now = null): string
    {
        if ($this->status === self::STATUS_STOPPED) {
            return 'stopped';
        }

        if ($this->status === self::STATUS_COMPLETED) {
            return 'completed';
        }

        if ($this->isPrn()) {
            return 'prn';
        }

        if (!$this->next_due_at) {
            return 'scheduled';
        }

        $now ??= now();
        $secondsToDue = $this->next_due_at->getTimestamp() - $now->getTimestamp();

        if ($secondsToDue < 0) {
            return 'overdue';
        }

        return $secondsToDue <= self::DUE_SOON_MINUTES * 60 ? 'due_soon' : 'scheduled';
    }

    /**
     * The earliest a PRN dose should be given again, when the order sets a
     * minimum gap and a dose has been given.
     */
    public function nextAllowedAt(): ?Carbon
    {
        if (!$this->isPrn() || !$this->interval_minutes || !$this->last_given_at) {
            return null;
        }

        return $this->last_given_at->copy()->addMinutes($this->interval_minutes);
    }

    /** "Overdue 1h 20m", "Due in 25m", "STAT - give now", "When required". */
    public function dueLabel(?CarbonInterface $now = null): string
    {
        $now ??= now();
        $state = $this->dueState($now);

        if ($this->isStat() && in_array($state, ['overdue', 'due_soon'], true)) {
            return 'STAT - give now';
        }

        return match ($state) {
            'stopped' => 'Stopped',
            'completed' => 'Completed',
            'prn' => ($allowed = $this->nextAllowedAt()) && $allowed->greaterThan($now)
                ? 'PRN - not before ' . $allowed->format('H:i')
                : 'PRN - when required',
            'overdue' => 'Overdue ' . self::formatDuration(self::minutesBetween($this->next_due_at, $now)),
            default => $this->next_due_at
                ? 'Due in ' . self::formatDuration(self::minutesBetween($now, $this->next_due_at))
                : 'Scheduled',
        };
    }

    /** Sort key: most urgent first. */
    public static function urgencyRank(string $state): int
    {
        return ['overdue' => 0, 'due_soon' => 1, 'scheduled' => 2, 'prn' => 3][$state] ?? 4;
    }

    /**
     * Document a dose against this order and schedule the next one from it.
     */
    public function record(string $status, CarbonInterface $at, ?string $notes = null, ?int $userId = null): MedicationAdministration
    {
        $administration = $this->administrations()->create([
            'status' => $status,
            'administered_at' => $at,
            'due_at' => $this->isPrn() ? null : $this->next_due_at,
            'dose_amount' => $this->dose_amount,
            'dose_unit' => $this->dose_unit,
            'notes' => $notes,
            'recorded_by' => $userId,
        ]);

        $this->reschedule();

        return $administration;
    }

    /**
     * Work the schedule out again from the doses on record. The next dose is
     * due one interval after the latest dose recorded, whether it was given,
     * held or refused; before any dose it is due at the start time. A STAT
     * order is complete once its dose is documented.
     */
    public function reschedule(): void
    {
        $latest = $this->administrations()
            ->orderByDesc('administered_at')
            ->orderByDesc('id')
            ->first();

        $this->last_given_at = $this->administrations()
            ->where('status', MedicationAdministration::STATUS_GIVEN)
            ->max('administered_at');

        if ($this->isStat() && $this->status !== self::STATUS_STOPPED) {
            $this->status = $latest ? self::STATUS_COMPLETED : self::STATUS_ACTIVE;
        }

        if ($this->status !== self::STATUS_ACTIVE || $this->isPrn()) {
            $this->next_due_at = null;
        } elseif ($latest && $this->interval_minutes) {
            $this->next_due_at = $latest->administered_at->copy()->addMinutes($this->interval_minutes);
        } else {
            $this->next_due_at = $this->start_at;
        }

        $this->save();
    }

    public function stop(string $reason, ?int $userId = null): void
    {
        $this->update([
            'status' => self::STATUS_STOPPED,
            'stopped_at' => now(),
            'stopped_by' => $userId,
            'stop_reason' => $reason,
            'next_due_at' => null,
        ]);
    }

    /**
     * Every order for a patient with its dose history (latest dose first),
     * for the Medications tab.
     */
    public static function forPatient(int $patientId): Collection
    {
        return static::where('patient_id', $patientId)
            ->with([
                'administrations' => fn ($query) => $query
                    ->with('recordedBy:id,name')
                    ->orderByDesc('administered_at')
                    ->orderByDesc('id'),
                'createdBy:id,name',
                'stoppedBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Active orders per patient for the ward dashboard, keyed by patient id:
     * counts of overdue and due-soon doses and the orders, most urgent first.
     */
    public static function alertsForPatients(iterable $patientIds, ?CarbonInterface $now = null): array
    {
        $ids = collect($patientIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $now ??= now();

        return static::whereIn('patient_id', $ids)
            ->where('status', self::STATUS_ACTIVE)
            ->get()
            ->groupBy('patient_id')
            ->map(function (Collection $orders) use ($now) {
                $items = $orders
                    ->map(function (self $order) use ($now) {
                        $state = $order->dueState($now);

                        return [
                            'name' => $order->medication_name,
                            'summary' => $order->summary(),
                            'state' => $state,
                            'label' => $order->dueLabel($now),
                            'due_time' => $order->next_due_at?->format('H:i'),
                            'high_alert' => $order->is_high_alert,
                            'rank' => self::urgencyRank($state),
                            'due_ts' => $order->next_due_at?->getTimestamp() ?? PHP_INT_MAX,
                        ];
                    })
                    ->sort(fn ($a, $b) => [$a['rank'], $a['due_ts']] <=> [$b['rank'], $b['due_ts']])
                    ->values();

                return [
                    'active' => $items->count(),
                    'overdue' => $items->where('state', 'overdue')->count(),
                    'due_soon' => $items->where('state', 'due_soon')->count(),
                    'items' => $items->all(),
                ];
            })
            ->all();
    }
}
