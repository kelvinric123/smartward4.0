<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloodTransfusion extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_STOPPED = 'stopped';

    /**
     * A unit must be completed within four hours of leaving controlled
     * storage. Anything still running past that is flagged.
     */
    public const MAX_RUNNING_MINUTES = 240;

    /**
     * A unit checked at the bedside but not started within this long is
     * treated as a delay worth flagging.
     */
    public const START_DELAY_MINUTES = 30;

    public const PRODUCT_TYPES = [
        'Packed Red Cells',
        'Whole Blood',
        'Platelets',
        'Fresh Frozen Plasma',
        'Cryoprecipitate',
    ];

    /**
     * Sensible starting figures per product, and the duration window each one
     * normally falls in. Selecting a product fills these in; the nurse can
     * still change them, and anything outside the window is flagged rather
     * than blocked, because the prescription wins over a default.
     */
    public const PRODUCT_PRESETS = [
        'Packed Red Cells' => [
            'volume_ml' => 300, 'minutes' => 120, 'min_minutes' => 60, 'max_minutes' => 240,
            'note' => 'Usually given over 1.5 to 3 hours, and always within 4.',
        ],
        'Whole Blood' => [
            'volume_ml' => 450, 'minutes' => 180, 'min_minutes' => 90, 'max_minutes' => 240,
            'note' => 'Larger volume, usually 2 to 4 hours.',
        ],
        'Platelets' => [
            'volume_ml' => 250, 'minutes' => 30, 'min_minutes' => 15, 'max_minutes' => 60,
            'note' => 'Given quickly, usually about 30 minutes.',
        ],
        'Fresh Frozen Plasma' => [
            'volume_ml' => 250, 'minutes' => 30, 'min_minutes' => 15, 'max_minutes' => 60,
            'note' => 'Given quickly once thawed, usually about 30 minutes.',
        ],
        'Cryoprecipitate' => [
            'volume_ml' => 200, 'minutes' => 30, 'min_minutes' => 15, 'max_minutes' => 60,
            'note' => 'Given quickly, usually about 30 minutes.',
        ],
    ];

    /** Step sizes for the plus and minus controls on the form. */
    public const VOLUME_STEP = 25;
    public const VOLUME_MIN = 50;
    public const VOLUME_MAX = 2000;
    public const MINUTES_STEP = 15;
    public const MINUTES_MIN = 15;

    /** Products the red cell ABO/Rh rule below applies to. */
    public const RED_CELL_PRODUCTS = ['Packed Red Cells', 'Whole Blood'];

    public const BLOOD_GROUPS = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];

    /** Recipient group => donor groups acceptable for red cells. */
    public const RED_CELL_COMPATIBILITY = [
        'O-' => ['O-'],
        'O+' => ['O-', 'O+'],
        'A-' => ['O-', 'A-'],
        'A+' => ['O-', 'O+', 'A-', 'A+'],
        'B-' => ['O-', 'B-'],
        'B+' => ['O-', 'O+', 'B-', 'B+'],
        'AB-' => ['O-', 'A-', 'B-', 'AB-'],
        'AB+' => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
    ];

    protected $fillable = [
        'patient_id', 'ward_id', 'unit_number', 'product_type', 'unit_blood_group',
        'patient_blood_group', 'crossmatch_reference', 'unit_expires_at', 'volume_ml',
        'prescribed_minutes', 'check_crossmatch', 'check_product', 'check_expiry',
        'check_identity', 'checked_by', 'checked_at', 'status', 'started_at',
        'completed_at', 'stop_reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'unit_expires_at' => 'datetime',
        'checked_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'check_crossmatch' => 'boolean',
        'check_product' => 'boolean',
        'check_expiry' => 'boolean',
        'check_identity' => 'boolean',
        'volume_ml' => 'integer',
        'prescribed_minutes' => 'integer',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function checkedBy()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_STOPPED], true);
    }

    // ---------------------------------------------------------------- timing

    /**
     * The prescribed rate, from volume over the planned duration. This is what
     * the unit should be running at; the app has no pump feed to measure the
     * actual rate against.
     */
    public function rateMlPerHour(): ?float
    {
        if (!$this->volume_ml || !$this->prescribed_minutes) {
            return null;
        }

        return round($this->volume_ml / ($this->prescribed_minutes / 60), 1);
    }

    public function predictedEndAt(): ?Carbon
    {
        if (!$this->started_at || !$this->prescribed_minutes) {
            return null;
        }

        return $this->started_at->copy()->addMinutes($this->prescribed_minutes);
    }

    /** When the four hour limit is reached for this unit. */
    public function expiresRunningAt(): ?Carbon
    {
        return $this->started_at?->copy()->addMinutes(self::MAX_RUNNING_MINUTES);
    }

    public function elapsedMinutes(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();

        return max(0, $this->started_at->diffInMinutes($end));
    }

    public function remainingMinutes(): ?int
    {
        $end = $this->predictedEndAt();

        if (!$end || $this->isFinished()) {
            return null;
        }

        return now()->greaterThan($end) ? 0 : now()->diffInMinutes($end);
    }

    public function progressPercent(): ?int
    {
        $elapsed = $this->elapsedMinutes();

        if ($elapsed === null || !$this->prescribed_minutes) {
            return null;
        }

        return (int) min(100, round($elapsed / $this->prescribed_minutes * 100));
    }

    // ------------------------------------------------------------- checklist

    /**
     * The four pre-start checks, each with whether it has been confirmed and
     * anything the record itself contradicts.
     */
    public function checklist(): array
    {
        return [
            [
                'key' => 'check_crossmatch',
                'label' => 'Crossmatch confirmed',
                'detail' => $this->crossmatch_reference
                    ? 'Reference ' . $this->crossmatch_reference
                    : 'No crossmatch reference recorded',
                'done' => $this->check_crossmatch,
                'problem' => $this->crossmatch_reference ? null : 'No crossmatch reference on this unit.',
            ],
            [
                'key' => 'check_product',
                'label' => 'Product type verified',
                'detail' => $this->product_type ?: 'Not recorded',
                'done' => $this->check_product,
                'problem' => null,
            ],
            [
                'key' => 'check_expiry',
                'label' => 'Expiry checked',
                'detail' => $this->unit_expires_at
                    ? 'Expires ' . $this->unit_expires_at->format('d M Y H:i')
                    : 'No expiry recorded',
                'done' => $this->check_expiry,
                'problem' => $this->unitExpired() ? 'This unit has passed its expiry.' : null,
            ],
            [
                'key' => 'check_identity',
                'label' => 'Patient and unit match',
                'detail' => $this->groupSummary(),
                'done' => $this->check_identity,
                'problem' => $this->compatibility() === 'incompatible'
                    ? 'Unit group is not compatible with the patient group.'
                    : null,
            ],
        ];
    }

    /**
     * The checklist as an ordered stepper: a step only unlocks once every step
     * before it is confirmed, and only the most recently confirmed step can be
     * undone, so the sequence cannot be worked around by clicking about.
     */
    public function checklistSteps(): array
    {
        $steps = $this->checklist();
        $lastDone = -1;

        foreach ($steps as $index => $step) {
            if ($step['done']) {
                $lastDone = $index;
            }
        }

        foreach ($steps as $index => &$step) {
            $step['number'] = $index + 1;
            $step['unlocked'] = collect(array_slice($steps, 0, $index))->every(fn ($s) => $s['done']);
            $step['isNext'] = $step['unlocked'] && !$step['done'];
            $step['canUndo'] = $step['done'] && $index === $lastDone;
        }

        return $steps;
    }

    public function completedStepCount(): int
    {
        return collect($this->checklist())->where('done', true)->count();
    }

    public function checksComplete(): bool
    {
        return $this->check_crossmatch && $this->check_product
            && $this->check_expiry && $this->check_identity;
    }

    /** The usual duration window for this product, or null if unknown. */
    public function durationWindow(): ?array
    {
        $preset = self::PRODUCT_PRESETS[$this->product_type] ?? null;

        return $preset ? ['min' => $preset['min_minutes'], 'max' => $preset['max_minutes']] : null;
    }

    public function durationFits(): bool
    {
        $window = $this->durationWindow();

        if (!$window || !$this->prescribed_minutes) {
            return true;
        }

        return $this->prescribed_minutes >= $window['min']
            && $this->prescribed_minutes <= $window['max'];
    }

    public function unitExpired(): bool
    {
        return $this->unit_expires_at !== null && $this->unit_expires_at->isPast();
    }

    public function groupSummary(): string
    {
        if (!$this->unit_blood_group || !$this->patient_blood_group) {
            return 'Groups not both recorded';
        }

        return 'Unit ' . $this->unit_blood_group . ' to patient ' . $this->patient_blood_group;
    }

    /**
     * 'compatible', 'incompatible', or 'manual' when the rule encoded here
     * does not settle it. Plasma, platelets and cryoprecipitate follow
     * different rules from red cells, so they are never asserted as compatible
     * by this app; they are left to the bedside check.
     */
    public function compatibility(): string
    {
        if (!$this->unit_blood_group || !$this->patient_blood_group) {
            return 'manual';
        }

        if (!in_array($this->product_type, self::RED_CELL_PRODUCTS, true)) {
            return 'manual';
        }

        $acceptable = self::RED_CELL_COMPATIBILITY[$this->patient_blood_group] ?? null;

        if ($acceptable === null) {
            return 'manual';
        }

        return in_array($this->unit_blood_group, $acceptable, true) ? 'compatible' : 'incompatible';
    }

    // ------------------------------------------------------------ exceptions

    /**
     * Everything worth flagging on this episode, worst first.
     *
     * @return array<int, array{level: string, title: string, detail: string}>
     */
    public function exceptions(): array
    {
        $exceptions = [];

        if ($this->compatibility() === 'incompatible') {
            $exceptions[] = [
                'level' => 'critical',
                'title' => 'Blood group mismatch',
                'detail' => $this->groupSummary() . ' is not a compatible red cell pairing. Do not proceed.',
            ];
        }

        if ($this->unitExpired()) {
            $exceptions[] = [
                'level' => 'critical',
                'title' => 'Unit past expiry',
                'detail' => 'Expired ' . $this->unit_expires_at->format('d M Y H:i') . '.',
            ];
        }

        if ($this->isRunning() && $this->started_at
            && $this->started_at->diffInMinutes(now()) > self::MAX_RUNNING_MINUTES) {
            $exceptions[] = [
                'level' => 'critical',
                'title' => 'Running beyond four hours',
                'detail' => 'Started ' . $this->started_at->format('d M H:i')
                    . '. A unit should be completed within four hours of starting.',
            ];
        }

        if (!$this->isFinished() && !$this->checksComplete()) {
            $missing = collect($this->checklist())
                ->reject(fn ($check) => $check['done'])
                ->pluck('label')
                ->implode(', ');

            $exceptions[] = [
                'level' => $this->isRunning() ? 'critical' : 'warning',
                'title' => $this->isRunning() ? 'Running with incomplete checks' : 'Checks incomplete',
                'detail' => 'Outstanding: ' . $missing . '.',
            ];
        }

        if ($this->isRunning() && ($end = $this->predictedEndAt()) && now()->greaterThan($end)) {
            $exceptions[] = [
                'level' => 'warning',
                'title' => 'Past predicted end time',
                'detail' => 'Expected to finish ' . $end->format('d M H:i')
                    . ', ' . $end->diffInMinutes(now()) . ' min ago.',
            ];
        }

        if ($this->isPending() && $this->checked_at
            && $this->checked_at->diffInMinutes(now()) > self::START_DELAY_MINUTES) {
            $exceptions[] = [
                'level' => 'warning',
                'title' => 'Checked but not started',
                'detail' => 'Bedside check completed ' . $this->checked_at->diffForHumans()
                    . ' and the unit has not been started.',
            ];
        }

        if (!$this->isFinished() && !$this->durationFits()) {
            $window = $this->durationWindow();
            $exceptions[] = [
                'level' => 'warning',
                'title' => 'Duration outside the usual window',
                'detail' => $this->prescribed_minutes . ' min planned for ' . $this->product_type
                    . ', which is normally given over ' . $window['min'] . ' to ' . $window['max'] . ' min.',
            ];
        }

        if ($this->compatibility() === 'manual' && !$this->isFinished()) {
            $exceptions[] = [
                'level' => 'info',
                'title' => 'Group match needs manual confirmation',
                'detail' => in_array($this->product_type, self::RED_CELL_PRODUCTS, true)
                    ? 'Both blood groups are not recorded, so the app cannot check the pairing.'
                    : $this->product_type . ' does not follow the red cell rule. Confirm compatibility at the bedside.',
            ];
        }

        return $exceptions;
    }

    public function hasCriticalException(): bool
    {
        foreach ($this->exceptions() as $exception) {
            if ($exception['level'] === 'critical') {
                return true;
            }
        }

        return false;
    }

    /** A unit may only be started once every check passes and nothing critical is open. */
    public function canStart(): bool
    {
        return $this->isPending() && $this->checksComplete() && !$this->hasCriticalException();
    }
}
