<?php

namespace App\Services\NurseScheduling;

use App\Models\Ward;
use App\Models\WardRosterSetting;

/**
 * The weights behind a ward's workload scores, and how the AI shares beds out.
 *
 * Each care factor adds its points to a bed's score, and a nurse's workload is
 * the sum over their beds. The last group tunes the bed assignment itself: how
 * much imbalance, in points, the AI accepts to keep a nurse on yesterday's beds
 * or to keep their beds together, and how far from the shift average a load
 * has to be before it is flagged heavy or light.
 *
 * A ward stores only what a nurse manager changed, under
 * ward_roster_settings.rules.workload; everything else is the default here.
 */
final class WorkloadWeights
{
    public const DEFAULTS = [
        'patient' => 1.0,
        'empty_bed' => 0.2,
        'nursing_level_1' => 0.0,
        'nursing_level_2' => 0.5,
        'nursing_level_3' => 1.0,
        'nursing_level_4' => 2.0,
        'ews_low' => 0.5,
        'ews_medium' => 1.0,
        'ews_high' => 2.0,
        'isolation' => 0.5,
        'fall_risk' => 0.5,
        'infusion' => 0.5,
        'stat_order' => 0.5,
        'new_admission' => 0.5,
        'continuity' => 0.6,
        'neighbour' => 0.3,
        'balance_band' => 25.0,
    ];

    /** The settings panel: group => key => label and hint. */
    public const GROUPS = [
        'Patient' => [
            'patient' => ['label' => 'Each patient', 'hint' => 'Every occupied bed starts with this'],
            'empty_bed' => ['label' => 'Empty bed', 'hint' => 'Kept ready for an admission'],
        ],
        'Nursing level' => [
            'nursing_level_1' => ['label' => 'Level 1', 'hint' => 'On top of the patient points'],
            'nursing_level_2' => ['label' => 'Level 2', 'hint' => 'On top of the patient points'],
            'nursing_level_3' => ['label' => 'Level 3', 'hint' => 'On top of the patient points'],
            'nursing_level_4' => ['label' => 'Level 4', 'hint' => 'On top of the patient points'],
        ],
        'Early warning score' => [
            'ews_low' => ['label' => 'EWS 3 to 4', 'hint' => 'From vitals in the last 24 h'],
            'ews_medium' => ['label' => 'EWS 5 to 6', 'hint' => 'From vitals in the last 24 h'],
            'ews_high' => ['label' => 'EWS 7 or more', 'hint' => 'From vitals in the last 24 h'],
        ],
        'Care needs' => [
            'isolation' => ['label' => 'Isolation', 'hint' => 'Any isolation type'],
            'fall_risk' => ['label' => 'High fall risk', 'hint' => 'High or active fall alert'],
            'infusion' => ['label' => 'Infusion running', 'hint' => 'From the infusion pumps'],
            'stat_order' => ['label' => 'STAT consultant order', 'hint' => 'An open STAT order'],
            'new_admission' => ['label' => 'New admission', 'hint' => 'Admitted in the last 24 h'],
        ],
        'How AI shares the beds' => [
            'continuity' => ['label' => 'Keep yesterday\'s beds', 'hint' => 'Imbalance accepted, in points, to keep a nurse on the beds they had on this shift yesterday'],
            'neighbour' => ['label' => 'Keep beds together', 'hint' => 'Imbalance accepted, in points, to keep a nurse\'s beds next to each other'],
            'balance_band' => ['label' => 'Heavy or light at', 'hint' => 'Percent above or below the shift average', 'unit' => '%'],
        ],
    ];

    public const MAX_POINTS = 10;

    private function __construct(private readonly array $values)
    {
    }

    public static function defaults(): self
    {
        return new self(self::DEFAULTS);
    }

    public static function forWard(Ward $ward): self
    {
        $rules = WardRosterSetting::where('ward_id', $ward->id)->value('rules');
        $stored = is_array($rules) && is_array($rules['workload'] ?? null) ? $rules['workload'] : [];

        $values = self::DEFAULTS;
        foreach ($stored as $key => $value) {
            if (array_key_exists($key, $values) && is_numeric($value)) {
                $values[$key] = (float) $value;
            }
        }

        return new self($values);
    }

    public function get(string $key): float
    {
        return (float) ($this->values[$key] ?? self::DEFAULTS[$key] ?? 0);
    }

    /** Heavy or light threshold as a fraction, e.g. 0.25. */
    public function band(): float
    {
        return $this->get('balance_band') / 100;
    }

    public function isDefault(string $key): bool
    {
        return abs($this->get($key) - self::DEFAULTS[$key]) < 0.0001;
    }

    public function changedCount(): int
    {
        return collect(array_keys(self::DEFAULTS))->reject(fn (string $key) => $this->isDefault($key))->count();
    }

    public static function max(string $key): float
    {
        return $key === 'balance_band' ? 100 : self::MAX_POINTS;
    }

    /** Validation for the settings form, which posts weights[key]. */
    public static function validationRules(): array
    {
        $rules = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $rules['weights.' . $key] = ['required', 'numeric', 'min:0', 'max:' . self::max($key)];
        }

        return $rules;
    }

    /** "0.5", "2", "25": trailing zeros dropped. */
    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
