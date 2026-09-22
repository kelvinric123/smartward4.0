<?php

namespace App\Services;

use App\Models\IntegrationSetting;

/**
 * Where each Patient Additional Info field is maintained:
 *  - "adt":    shown read-only in Patient Details; the ADT feed keeps it up to date.
 *  - "manual": staff can edit it in Patient Details > Additional Info.
 *
 * ADT messages are applied the same way in both modes, so a field the feed sends is never
 * silently dropped; in manual mode the most recent change (ADT or staff) is what shows.
 *
 * This is system-wide (not per user) because it decides who owns clinical data.
 * With nothing saved, the defaults reproduce how each field behaved before this setting existed.
 */
class PatientInfoSources
{
    public const KEY = 'patient_info.sources';

    public const ADT = 'adt';
    public const MANUAL = 'manual';

    /**
     * field => [label, default source, whether the ADT feed actually sends it]
     */
    public const FIELDS = [
        'nursing_level' => ['Nursing Level of Care', self::MANUAL, false],
        'diet' => ['Diet', self::ADT, true],
        'fall_risk' => ['Fall Risk Alert', self::ADT, true],
        'isolation' => ['Isolation Precautions', self::MANUAL, true],
        'allergies' => ['Medical Allergies', self::ADT, true],
    ];

    /**
     * Source for every field, saved values over defaults.
     */
    public static function all(): array
    {
        $saved = IntegrationSetting::get(self::KEY, []);
        $saved = is_array($saved) ? $saved : [];

        $sources = [];
        foreach (self::FIELDS as $field => [, $default]) {
            $value = $saved[$field] ?? null;
            $sources[$field] = in_array($value, [self::ADT, self::MANUAL], true) ? $value : $default;
        }

        return $sources;
    }

    public static function isManual(string $field): bool
    {
        return (self::all()[$field] ?? null) === self::MANUAL;
    }

    /**
     * Save the chosen sources; unknown fields and values are ignored.
     */
    public static function save(array $sources): void
    {
        $clean = [];
        foreach (self::FIELDS as $field => [, $default]) {
            $value = $sources[$field] ?? $default;
            $clean[$field] = in_array($value, [self::ADT, self::MANUAL], true) ? $value : $default;
        }

        IntegrationSetting::put(self::KEY, $clean);
    }
}
