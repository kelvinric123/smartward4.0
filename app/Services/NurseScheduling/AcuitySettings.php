<?php

namespace App\Services\NurseScheduling;

use App\Models\Ward;
use App\Models\WardRosterSetting;

/**
 * How a ward turns patient acuity into nurses: the hours of nursing care a
 * patient at each care level needs in 24 h, and how the day's care is spread
 * over the AM, PM and night shifts. AcuityStaffing does the sums.
 *
 * Also the early warning score system the acuity indicators use. It is the
 * ward's, not each user's ward dashboard setting, so everyone sees the same
 * suggestions, and the ratings, the rating check and the minimum staff set
 * from acuity are all measured on one scale.
 *
 * The ANA paper (2017) gives no hours per care level; these defaults are
 * SmartWard's own starting values for a ward to replace with its own. A ward's
 * settings are stored under ward_roster_settings.rules.acuity; anything not
 * stored there is the default here.
 */
final class AcuitySettings
{
    /** The systems WardDashboardController::calculateEWS scores, as the ward dashboard settings name them. */
    public const EWS_SYSTEMS = [
        'ews_ihh' => 'EWS IHH (IJN Hospital Hijau)',
        'news2' => 'NEWS2 (National Early Warning Score 2)',
        'news' => 'NEWS (National Early Warning Score)',
        'mews' => 'MEWS (Modified Early Warning Score)',
        'pews' => 'PEWS (Pediatric Early Warning Score)',
    ];

    public const DEFAULT_EWS_SYSTEM = 'ews_ihh';

    public const DEFAULTS = [
        'hours_level_1' => 3.0,
        'hours_level_2' => 5.0,
        'hours_level_3' => 7.5,
        'hours_level_4' => 12.0,
        'share_AM' => 40.0,
        'share_PM' => 35.0,
        'share_ON' => 25.0,
    ];

    /** The settings panel: group => key => label and hint. */
    public const GROUPS = [
        'Hours of nursing care per patient in 24 h' => [
            'hours_level_1' => ['label' => 'Level 1 Minimal', 'hint' => 'Hours a day', 'unit' => 'h'],
            'hours_level_2' => ['label' => 'Level 2 Moderate', 'hint' => 'Hours a day', 'unit' => 'h'],
            'hours_level_3' => ['label' => 'Level 3 Maximum', 'hint' => 'Hours a day', 'unit' => 'h'],
            'hours_level_4' => ['label' => 'Level 4 Intensive', 'hint' => '24 h is one nurse for one patient', 'unit' => 'h'],
        ],
        'Share of the day\'s care on each shift' => [
            'share_AM' => ['label' => 'AM', 'hint' => 'Percent of the 24 h care', 'unit' => '%'],
            'share_PM' => ['label' => 'PM', 'hint' => 'Percent of the 24 h care', 'unit' => '%'],
            'share_ON' => ['label' => 'Night (ON)', 'hint' => 'Percent of the 24 h care', 'unit' => '%'],
        ],
    ];

    /** The shares must add up to 100, give or take this. */
    public const SHARE_TOLERANCE = 0.5;

    private function __construct(private readonly array $values, private readonly string $ewsSystem = self::DEFAULT_EWS_SYSTEM)
    {
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    public static function forWard(Ward $ward): self
    {
        $rules = WardRosterSetting::where('ward_id', $ward->id)->value('rules');

        return self::fromRules(is_array($rules) ? $rules : []);
    }

    /** From a ward's stored rules JSON. */
    public static function fromRules(array $rules): self
    {
        $stored = is_array($rules['acuity'] ?? null) ? $rules['acuity'] : [];

        $values = self::DEFAULTS;
        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $values) && is_numeric($value)) {
                $values[$key] = (float) $value;
            }
        }

        $ewsSystem = $stored['ews_system'] ?? null;

        return new self($values, is_string($ewsSystem) && isset(self::EWS_SYSTEMS[$ewsSystem]) ? $ewsSystem : self::DEFAULT_EWS_SYSTEM);
    }

    /** A number setting: the hours or a share. The early warning score system is ewsSystem(). */
    public function get(string $key): float
    {
        return (float) ($this->values[$key] ?? self::DEFAULTS[$key] ?? 0);
    }

    /** The early warning score system the acuity indicators use on this ward. */
    public function ewsSystem(): string
    {
        return $this->ewsSystem;
    }

    public function ewsSystemName(): string
    {
        return self::EWS_SYSTEMS[$this->ewsSystem];
    }

    /** Hours of nursing care in 24 h for a patient at this care level. */
    public function hours(int $level): float
    {
        return $this->get('hours_level_' . $level);
    }

    /** Percent of the day's care that falls on this shift (AM, PM or ON). */
    public function share(string $code): float
    {
        return $this->get('share_' . $code);
    }

    public function isDefault(string $key): bool
    {
        return abs($this->get($key) - self::DEFAULTS[$key]) < 0.0001;
    }

    public function changedCount(): int
    {
        return collect(array_keys(self::DEFAULTS))->reject(fn (string $key) => $this->isDefault($key))->count()
            + ($this->ewsSystem === self::DEFAULT_EWS_SYSTEM ? 0 : 1);
    }

    public function all(): array
    {
        return $this->values;
    }

    public static function min(string $key): float
    {
        return str_starts_with($key, 'hours_') ? 0.5 : 0;
    }

    public static function max(string $key): float
    {
        return str_starts_with($key, 'hours_') ? 24 : 100;
    }

    /**
     * Validation for the settings form, which posts acuity[key] for the numbers
     * and acuity[ews_system] (left out, the ward keeps the system it has).
     */
    public static function validationRules(): array
    {
        $rules = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $rules['acuity.' . $key] = ['required', 'numeric', 'min:' . self::min($key), 'max:' . self::max($key)];
        }
        $rules['acuity.ews_system'] = ['nullable', 'string', 'in:' . implode(',', array_keys(self::EWS_SYSTEMS))];

        return $rules;
    }

    /** The shares' total, for the "must add up to 100" check. */
    public static function shareTotal(array $values): float
    {
        return array_sum(array_map(fn (string $code) => (float) ($values['share_' . $code] ?? 0), WardShifts::CODES));
    }

    /** "7.5", "12", "40": trailing zeros dropped. */
    public static function format(float $value): string
    {
        return WorkloadWeights::format($value);
    }
}
