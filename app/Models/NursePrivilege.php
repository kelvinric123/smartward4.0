<?php

namespace App\Models;

use App\Support\NursePrivilegeCatalogue;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A clinical privilege: a procedure the nurse has been cleared to perform.
 * Most come from the built-in list (NursePrivilegeCatalogue, linked by code);
 * a privilege of the hospital's own has no code.
 */
class NursePrivilege extends Model
{
    public const CATEGORIES = [
        'core' => [
            'label' => 'Core',
            'hint' => 'Everyday procedures a registered nurse may do once assessed as competent',
        ],
        'advanced' => [
            'label' => 'Advanced',
            'hint' => 'Procedures that need extra training and a competency sign-off',
        ],
    ];

    public const STATUSES = [
        'granted' => ['label' => 'Granted', 'hint' => 'May perform it independently'],
        'supervised' => ['label' => 'Supervised', 'hint' => 'May perform it only under supervision'],
        'suspended' => ['label' => 'Suspended', 'hint' => 'Temporarily not allowed'],
        'withdrawn' => ['label' => 'Withdrawn', 'hint' => 'No longer held'],
    ];

    /**
     * Statuses under which the nurse may still perform the procedure.
     */
    public const ACTIVE_STATUSES = ['granted', 'supervised'];

    /**
     * Statuses that must be explained in the notes.
     */
    public const REASON_REQUIRED = ['suspended', 'withdrawn'];

    /**
     * How many years after it is granted a privilege is reviewed, by default.
     */
    public const REVIEW_YEARS = [
        'core' => 3,
        'advanced' => 1,
    ];

    public const DEFAULT_APPROVER = 'Nursing Credentialing and Privileging Committee';

    /**
     * How soon before its review date a privilege is flagged.
     */
    public const REVIEW_WARNING_DAYS = 90;

    protected $fillable = [
        'nurse_id',
        'code',
        'name',
        'category',
        'status',
        'status_changed_at',
        'granted_on',
        'review_on',
        'approved_by',
        'notes',
        'recorded_by',
        'updated_by',
    ];

    protected $casts = [
        'status_changed_at' => 'datetime',
        'granted_on' => 'date',
        'review_on' => 'date',
    ];

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status]['label'] ?? Str::headline($this->status);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Its entry in the built-in list: by code, or for a row saved without
     * one, by name. Null for a privilege of the hospital's own.
     */
    public function catalogueCode(): ?string
    {
        return NursePrivilegeCatalogue::item($this->code)
            ? $this->code
            : NursePrivilegeCatalogue::codeForName((string) $this->name);
    }

    /**
     * Catalogue name => category, keyed in lower case for matching what was typed.
     */
    public static function catalogueIndex(): array
    {
        return collect(NursePrivilegeCatalogue::items())
            ->mapWithKeys(fn (array $item) => [Str::lower($item['name']) => $item['level']])
            ->all();
    }

    /**
     * The catalogue's spelling of a typed name, so "peripheral iv cannulation"
     * is stored the way the list shows it.
     */
    public static function canonicalName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return NursePrivilegeCatalogue::item(NursePrivilegeCatalogue::codeForName($name))['name'] ?? $name;
    }

    /**
     * The review date of a privilege of $category granted on $granted.
     */
    public static function defaultReviewOn(string $category, CarbonInterface $granted): CarbonInterface
    {
        return $granted->copy()->addYearsNoOverflow(self::REVIEW_YEARS[$category] ?? self::REVIEW_YEARS['core']);
    }

    /**
     * Whether the privilege is due for review on $today. Only privileges the
     * nurse still holds are reviewed. "chip" is the short form for list badges.
     *
     * @return array{key: string, label: string, chip: string, days: int}|null
     */
    public function reviewStatus(?CarbonInterface $today = null): ?array
    {
        if (!$this->review_on || !$this->isActive()) {
            return null;
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $days = (int) round($today->diffInDays($this->review_on->copy()->startOfDay(), false));
        $date = $this->review_on->format('j M Y');

        if ($days < 0) {
            return ['key' => 'overdue', 'label' => "Review overdue since {$date}", 'chip' => 'Review overdue', 'days' => $days];
        }

        if ($days <= self::REVIEW_WARNING_DAYS) {
            $chip = match ($days) {
                0 => 'Review due today',
                1 => 'Review due tomorrow',
                default => "Review due in {$days} days",
            };

            return ['key' => 'due', 'label' => "{$chip} ({$date})", 'chip' => $chip, 'days' => $days];
        }

        return ['key' => 'ok', 'label' => "Review by {$date}", 'chip' => "Review by {$date}", 'days' => $days];
    }
}
