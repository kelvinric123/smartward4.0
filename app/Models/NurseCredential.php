<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A licence or certificate on a nurse's credentialing file.
 */
class NurseCredential extends Model
{
    /**
     * What a credential can be: key => [full label, short badge].
     */
    public const TYPES = [
        'apc' => ['label' => 'Annual Practising Certificate (APC)', 'short' => 'APC'],
        'registration' => ['label' => 'Professional registration', 'short' => 'Registration'],
        'qualification' => ['label' => 'Academic qualification', 'short' => 'Qualification'],
        'post_basic' => ['label' => 'Post-basic / specialty', 'short' => 'Post-basic'],
        'life_support' => ['label' => 'Life support (BLS, ACLS...)', 'short' => 'Life support'],
        'competency' => ['label' => 'Competency / training', 'short' => 'Competency'],
        'other' => ['label' => 'Other', 'short' => 'Other'],
    ];

    /**
     * Types that always run out, so an expiry date is required.
     */
    public const EXPIRING_TYPES = ['apc', 'life_support'];

    /**
     * Title suggestions offered per type (the APC's come from apcTitle()).
     */
    public const TITLE_SUGGESTIONS = [
        'registration' => [
            'Registered Nurse',
            'Registered Midwife',
            'Registered Community Nurse',
        ],
        'qualification' => [
            'Diploma in Nursing',
            'Bachelor of Nursing',
            'Bachelor of Nursing Science',
            'Master of Nursing',
        ],
        'post_basic' => [
            'Post Basic Critical Care Nursing',
            'Post Basic Perioperative Care',
            'Post Basic Anaesthetic Nursing',
            'Post Basic Emergency Nursing',
            'Post Basic Renal Nursing',
            'Post Basic Paediatric Nursing',
            'Post Basic Neonatal Nursing',
            'Post Basic Midwifery',
            'Post Basic Oncology Nursing',
            'Post Basic Orthopaedic Nursing',
            'Post Basic Ophthalmic Nursing',
            'Post Basic Mental Health Nursing',
            'Post Basic Infection Control',
            'Post Basic Palliative Care',
            'Post Basic Cardiothoracic Nursing',
            'Post Basic Gerontology Nursing',
        ],
        'life_support' => [
            'Basic Life Support (BLS)',
            'Advanced Cardiac Life Support (ACLS)',
            'Paediatric Advanced Life Support (PALS)',
            'Neonatal Resuscitation Program (NRP)',
        ],
        'competency' => [
            'IV cannulation',
            'Blood transfusion',
            'Chemotherapy administration',
            'Infection control',
            'Medication safety',
        ],
    ];

    public const NURSING_BOARD = 'Lembaga Jururawat Malaysia (LJM)';

    /**
     * How soon before its expiry date a credential is flagged.
     */
    public const EXPIRY_WARNING_DAYS = 90;

    protected $fillable = [
        'nurse_id',
        'type',
        'title',
        'reference_number',
        'issuing_body',
        'issued_on',
        'expires_on',
        'verified_at',
        'verified_by',
        'notes',
        'recorded_by',
        'updated_by',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'expires_on' => 'date',
        'verified_at' => 'datetime',
    ];

    public function nurse()
    {
        return $this->belongsTo(Nurse::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? Str::headline($this->type);
    }

    public function typeShort(): string
    {
        return self::TYPES[$this->type]['short'] ?? Str::headline($this->type);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public static function apcTitle(int $year): string
    {
        return "Annual Practising Certificate {$year}";
    }

    /**
     * Where the credential stands on $today: expired, expiring (within
     * EXPIRY_WARNING_DAYS), valid, or no_expiry. It is valid through the
     * whole of its expiry date. "chip" is the short form for list badges.
     *
     * @return array{key: string, label: string, chip: string, days: int|null}
     */
    public function expiryStatus(?CarbonInterface $today = null): array
    {
        if (!$this->expires_on) {
            return ['key' => 'no_expiry', 'label' => 'No expiry', 'chip' => 'No expiry', 'days' => null];
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $days = (int) round($today->diffInDays($this->expires_on->copy()->startOfDay(), false));
        $date = $this->expires_on->format('j M Y');

        if ($days < 0) {
            return ['key' => 'expired', 'label' => "Expired {$date}", 'chip' => 'Expired', 'days' => $days];
        }

        if ($days <= self::EXPIRY_WARNING_DAYS) {
            $chip = match ($days) {
                0 => 'Expires today',
                1 => 'Expires tomorrow',
                default => "Expires in {$days} days",
            };

            return ['key' => 'expiring', 'label' => "{$chip} ({$date})", 'chip' => $chip, 'days' => $days];
        }

        return ['key' => 'valid', 'label' => "Valid until {$date}", 'chip' => 'Valid', 'days' => $days];
    }
}
