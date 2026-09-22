<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One measured intake or output on a patient's I/O chart. An entry made in
 * error is struck out with a reason, not deleted: it stays on the chart, but
 * no longer counts towards any total.
 */
class FluidBalanceEntry extends Model
{
    public const DIRECTION_INTAKE = 'intake';
    public const DIRECTION_OUTPUT = 'output';

    public const INTAKE_TYPES = [
        'oral' => 'Oral',
        'iv' => 'IV fluid',
        'tube_feed' => 'Tube feed',
        'blood' => 'Blood product',
        'iv_med' => 'IV medication',
        'other' => 'Other intake',
    ];

    public const OUTPUT_TYPES = [
        'urine' => 'Urine',
        'drain' => 'Drain',
        'vomit' => 'Vomit',
        'ng_aspirate' => 'NG aspirate',
        'stool' => 'Stool',
        'other' => 'Other output',
    ];

    /** Common descriptions offered for each type; anything else can be typed in. */
    public const SUGGESTIONS = [
        'intake' => [
            'oral' => ['Water', 'Milk', 'Tea / coffee', 'Juice', 'Soup', 'Porridge'],
            'iv' => ['0.9% NaCl', 'Dextrose 5%', 'Dextrose saline', "Hartmann's"],
            'tube_feed' => ['NG feed', 'PEG feed', 'Water flush'],
            'blood' => ['Packed red cells', 'Platelets', 'Fresh frozen plasma'],
            'iv_med' => ['IV antibiotic', 'IV flush'],
            'other' => [],
        ],
        'output' => [
            'urine' => ['Voided', 'Catheter', 'Bedpan / urinal'],
            'drain' => ['Chest drain', 'Wound drain', 'Redivac'],
            'vomit' => [],
            'ng_aspirate' => ['Free drainage', 'Aspirated'],
            'stool' => ['Loose stool', 'Stoma'],
            'other' => [],
        ],
    ];

    /** The largest single entry accepted, in mL. */
    public const VOLUME_MAX = 5000;

    /** One-tap volumes on the record form, in mL. */
    public const QUICK_VOLUMES = [50, 100, 150, 200, 250, 300, 500, 1000];

    protected $fillable = [
        'patient_id',
        'ward_id',
        'direction',
        'category',
        'volume_ml',
        'description',
        'recorded_at',
        'recorded_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'volume_ml' => 'integer',
        'recorded_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** Entries that count towards the totals: everything not struck out. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    /** The types that may be recorded in one direction, keyed by code. */
    public static function typesFor(?string $direction): array
    {
        return match ($direction) {
            self::DIRECTION_INTAKE => self::INTAKE_TYPES,
            self::DIRECTION_OUTPUT => self::OUTPUT_TYPES,
            default => [],
        };
    }

    public function isIntake(): bool
    {
        return $this->direction === self::DIRECTION_INTAKE;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function typeLabel(): string
    {
        return self::typesFor($this->direction)[$this->category] ?? ucfirst((string) $this->category);
    }

    /** "Oral - Tea / coffee", or just the type when nothing was added. */
    public function label(): string
    {
        return $this->typeLabel() . ($this->description ? ' - ' . $this->description : '');
    }

    /** "1,250 mL". */
    public static function formatMl(int $ml): string
    {
        return number_format($ml) . ' mL';
    }

    /** "+250 mL", "-400 mL", "0 mL". */
    public static function formatBalance(int $ml): string
    {
        return ($ml > 0 ? '+' : ($ml < 0 ? '-' : '')) . number_format(abs($ml)) . ' mL';
    }
}
