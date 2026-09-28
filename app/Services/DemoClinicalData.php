<?php

namespace App\Services;

use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Patient;
use App\Support\ClinicalIndicatorLibrary;
use App\Support\ClinicalIndicatorReadings;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Demo data for the Integration Demo page: vital signs and clinical indicator
 * entries that follow a chosen clinical course, so the dashboards, trend
 * charts and escalation flags have something realistic to show.
 *
 * A course is a severity over the seeded period, from 0 (well) to 1 (past the
 * escalation levels). Every value is taken from the severity at its own time,
 * so vital signs, hemodynamics and ventilator readings seeded together tell
 * one story. Monitor readings go through ClinicalIndicatorReadings::evaluate()
 * and scored scales are banded by the library, exactly as a nurse's entry is.
 */
final class DemoClinicalData
{
    public const PATTERNS = [
        'stable' => 'Stable: mostly within normal ranges',
        'deteriorating' => 'Deteriorating: drifts to escalation levels',
        'improving' => 'Improving: recovers from escalation levels',
        'random' => 'Random mix: normal, abnormal and escalation values',
    ];

    public const KIND_READINGS = 'readings';
    public const KIND_SCORED = 'scored';
    public const KIND_SCORE = 'score';

    public const NOTE = 'Demo seeded data';

    /** Seeding this scale puts the patient on mechanical ventilation in the vital signs too. */
    public const VENTILATOR = 'VENT';

    /**
     * Each monitor value well and at its worst, for the scales that ship with
     * the library: a shocked, low-output patient and worsening respiratory
     * failure. MAP and CO are worked out from the others, as the monitor
     * does. A value not listed moves from the middle of its normal range to
     * past its escalation level (see courseFor()).
     */
    private const COURSE = [
        'SBP' => [122, 80], 'DBP' => [70, 42], 'CVP' => [6, 17], 'CI' => [3.2, 1.8],
        'EtCO2' => [38, 56], 'FiO2' => [30, 75], 'PEEP' => [5, 14], 'PIP' => [20, 38], 'Vt' => [450, 280],
    ];

    /** Recorded less often than the rest, as cardiac output is: every Nth set of readings. */
    private const EVERY = ['CO' => 3, 'CI' => 3];

    /** Turns cardiac index into cardiac output: an average adult body surface area, in m². */
    private const BODY_SURFACE_AREA = 1.8;

    /** Vital signs well and at their worst, for every course but stable. */
    private const VITALS_COURSE = [
        'pulse_rate' => [82, 132], 'systolic_bp' => [122, 82], 'diastolic_bp' => [72, 46],
        'spo2' => [98, 88], 'respiratory_rate' => [16, 30], 'temperature' => [36.9, 38.8],
    ];

    private const TONE_RANK = [
        ClinicalIndicatorLibrary::TONE_LOW => 0,
        ClinicalIndicatorLibrary::TONE_MODERATE => 1,
        ClinicalIndicatorLibrary::TONE_HIGH => 2,
    ];

    /**
     * How a clinical indicator is seeded: monitor readings, a scale scored item
     * by item, or a total alone (Pain Score). Null for the scales still
     * awaiting their local detail, which have nothing to seed from.
     */
    public static function kind(?array $definition): ?string
    {
        if (ClinicalIndicatorLibrary::takesReadings($definition)) {
            return self::KIND_READINGS;
        }

        if (empty($definition['bands'])) {
            return null;
        }

        if (ClinicalIndicatorLibrary::isScorable($definition)) {
            return self::KIND_SCORED;
        }

        return isset($definition['score_min'], $definition['score_max']) ? self::KIND_SCORE : null;
    }

    /** What seeding a clinical indicator records, as the demo form lists it. */
    public static function describe(?array $definition): string
    {
        return match (self::kind($definition)) {
            self::KIND_READINGS => 'Monitor readings: ' . implode(', ', array_column($definition['items'], 'abbr')),
            self::KIND_SCORED => 'Scored item by item, ' . $definition['score_min'] . ' to ' . $definition['score_max'],
            self::KIND_SCORE => 'Scored as a total, ' . $definition['score_min'] . ' to ' . $definition['score_max'],
            default => 'Details still to confirm: nothing to seed yet',
        };
    }

    /** How far through the seeded period a time is: 0 at the start, 1 at the end. */
    public static function progress(CarbonInterface $at, CarbonInterface $from, CarbonInterface $to): float
    {
        $span = $to->getTimestamp() - $from->getTimestamp();

        return $span > 0 ? ($at->getTimestamp() - $from->getTimestamp()) / $span : 1.0;
    }

    /**
     * The severity a course has reached at a point through the seeded period.
     * Deteriorating and improving ease between well and past escalation, so
     * the first and last entries always read as the course says.
     */
    public static function severity(string $pattern, float $progress): float
    {
        $progress = max(0.0, min(1.0, $progress));
        $eased = $progress * $progress * (3 - 2 * $progress);

        $severity = match ($pattern) {
            'deteriorating' => 0.1 + 0.85 * $eased + self::jitter(0.05),
            'improving' => 0.95 - 0.85 * $eased + self::jitter(0.05),
            'random' => self::randomSeverity(),
            default => 0.12 + self::jitter(0.1),
        };

        return max(0.0, min(1.0, $severity));
    }

    /**
     * One set of vital signs at a severity. The stable course keeps to the
     * normal ranges the demo has always seeded. With the ventilator seeded
     * too, the patient is on mechanical ventilation at the same FiO2 course.
     */
    public static function vitalSigns(string $pattern, float $severity, bool $ventilated): array
    {
        if ($pattern === 'stable') {
            $vitals = [
                'systolic_bp' => mt_rand(100, 140),
                'diastolic_bp' => mt_rand(60, 90),
                'pulse_rate' => mt_rand(60, 100),
                'temperature' => mt_rand(365, 375) / 10,
                'spo2' => mt_rand(95, 100),
                'respiratory_rate' => mt_rand(12, 20),
            ];
        } else {
            $vitals = [];
            foreach (self::VITALS_COURSE as $field => [$well, $worst]) {
                $value = $well + ($worst - $well) * $severity + self::jitter(abs($worst - $well) * 0.05);
                $vitals[$field] = $field === 'temperature' ? round($value, 1) : (int) round($value);
            }
            $vitals['spo2'] = min(100, $vitals['spo2']);
            $vitals['diastolic_bp'] = min($vitals['diastolic_bp'], $vitals['systolic_bp'] - 20);
        }

        if ($ventilated) {
            [$well, $worst] = self::COURSE['FiO2'];
            $vitals['oxygen_delivery'] = 'ventilator';
            $vitals['fio2_percent'] = (int) max(21, min(100, round($well + ($worst - $well) * $severity)));
        }

        return $vitals;
    }

    /**
     * Demo entries for one clinical indicator, $perDay a day from $from to
     * $to, each at the severity the course has reached by then, recorded as
     * the patient's ward. Returns how many were saved.
     */
    public static function seedIndicator(
        Patient $patient,
        ClinicalIndicator $indicator,
        CarbonInterface $from,
        CarbonInterface $to,
        int $perDay,
        string $pattern,
        ?int $recordedBy
    ): int {
        $definition = $indicator->definition();
        $kind = self::kind($definition);

        if ($kind === null) {
            return 0;
        }

        $saved = 0;

        foreach (self::timeline($from, $to, $perDay) as $set => $at) {
            $severity = self::severity($pattern, self::progress($at, $from, $to));

            $entry = match ($kind) {
                self::KIND_READINGS => self::readingsEntry($definition, $severity, $set),
                self::KIND_SCORED => self::scoredEntry($definition, self::tone($severity)),
                default => self::scoreEntry($definition, self::tone($severity)),
            };

            if ($entry === null) {
                continue;
            }

            $record = new ClinicalIndicatorScore([
                'patient_id' => $patient->id,
                'clinical_indicator_id' => $indicator->id,
                'ward_id' => $patient->ward_id,
                'score' => $entry['score'],
                'item_scores' => $entry['items'],
                'notes' => self::NOTE,
                'recorded_by' => $recordedBy,
                'recorded_at' => $at,
            ]);
            $record->applyBand($indicator->code)->save();
            $saved++;
        }

        return $saved;
    }

    /**
     * Times $perDay a day, evenly spaced back from just before $to to $from
     * and each but the latest nudged a little, oldest first. The latest is
     * minutes old, so the dashboards read the course as current.
     *
     * @return array<int, Carbon>
     */
    public static function timeline(CarbonInterface $from, CarbonInterface $to, int $perDay): array
    {
        $interval = intdiv(1440, max(1, min(24, $perDay)));
        $nudge = intdiv($interval, 10);
        $times = [];

        for ($at = Carbon::instance($to)->subMinutes(mt_rand(2, 10)); $at->gte($from); $at = $at->copy()->subMinutes($interval)) {
            $nudged = $times === [] ? $at->copy() : $at->copy()->addMinutes(mt_rand(-$nudge, $nudge));
            $times[] = $nudged->lt($from) ? Carbon::instance($from) : $nudged;
        }

        // A patient admitted minutes ago still gets one entry
        if ($times === [] && $from->lte($to)) {
            $times[] = Carbon::instance($to);
        }

        return array_reverse($times);
    }

    /**
     * A set of monitor readings, checked and flagged as a nurse's entry is.
     * The rare set the checks refuse is drawn again.
     */
    private static function readingsEntry(array $definition, float $severity, int $set): ?array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $evaluated = ClinicalIndicatorReadings::evaluate($definition, self::readings($definition, $severity, $set));

            if ($evaluated['error'] === null) {
                return ['score' => $evaluated['score'], 'items' => $evaluated['items']];
            }
        }

        return null;
    }

    /**
     * One set of readings at a severity, posted by item index as the readings
     * form posts them, with '' for a value not recorded this time. $set counts
     * the sets so far, for the values recorded only every so often.
     */
    private static function readings(array $definition, float $severity, int $set): array
    {
        $values = [];

        foreach ($definition['items'] as $item) {
            $abbr = $item['abbr'];

            if ($set % (self::EVERY[$abbr] ?? 1) !== 0) {
                continue;
            }

            [$well, $worst] = self::COURSE[$abbr] ?? self::courseFor($item);
            $values[$abbr] = $well + ($worst - $well) * $severity + self::jitter(abs($worst - $well) * 0.04);
        }

        // Worked out from the others, as the monitor does
        if (isset($values['SBP'], $values['DBP'])) {
            $values['DBP'] = min($values['DBP'], $values['SBP'] - 20);

            if (array_key_exists('MAP', $values)) {
                $values['MAP'] = $values['DBP'] + ($values['SBP'] - $values['DBP']) / 3;
            }
        }
        if (isset($values['CI']) && array_key_exists('CO', $values)) {
            $values['CO'] = $values['CI'] * self::BODY_SURFACE_AREA;
        }

        return array_map(function (array $item) use ($values) {
            if (!isset($values[$item['abbr']])) {
                return '';
            }

            [$min, $max] = $item['limits'];

            return round(max($min, min($max, $values[$item['abbr']])), $item['decimals'] ?? 0);
        }, $definition['items']);
    }

    /**
     * A value COURSE does not list: from the middle of its normal range to
     * just past its escalation level, or well outside the range without one.
     */
    private static function courseFor(array $item): array
    {
        [$low, $high] = $item['normal'];
        $margin = ($high - $low) * 0.15;

        $worst = match (true) {
            isset($item['escalate_below']) && !isset($item['escalate_above']) => $item['escalate_below'] - $margin,
            isset($item['escalate_above']) => $item['escalate_above'] + $margin,
            default => $high + ($high - $low) * 0.5,
        };

        return [($low + $high) / 2, $worst];
    }

    /**
     * Options for every item of a scale scored item by item, totalling inside
     * a band of the given tone: start anywhere, then change one item at a time
     * towards it. Works whichever way the scale runs (a low GCS is bad, a low
     * Morse good) because it aims at the band, not at high or low options.
     *
     * @return array{score: int, items: array<int, array<string, mixed>>}
     */
    private static function scoredEntry(array $definition, string $tone): array
    {
        $items = array_values($definition['items']);
        $band = self::bandOfTone($definition, $tone);
        $floor = $band['min'] ?? $definition['score_min'];
        $ceiling = $band['max'] ?? $definition['score_max'];

        $picks = array_map(fn (array $item) => array_rand($item['options']), $items);

        for ($step = 0; $step < 100; $step++) {
            $total = self::total($items, $picks);

            if ($total >= $floor && $total <= $ceiling) {
                break;
            }

            $target = mt_rand($floor, $ceiling);
            $i = array_rand($items);
            $others = $total - $items[$i]['options'][$picks[$i]]['value'];

            $picks[$i] = collect($items[$i]['options'])
                ->sortBy(fn (array $option) => abs($others + $option['value'] - $target))
                ->keys()
                ->first();
        }

        $entries = [];
        foreach ($items as $i => $item) {
            $option = $item['options'][$picks[$i]];
            // As storeClinicalIndicatorScore records an item
            $entry = ['name' => $item['name'], 'label' => $option['label'], 'value' => $option['value']];
            if (!empty($item['abbr'])) {
                $entry['abbr'] = $item['abbr'];
            }
            $entries[] = $entry;
        }

        return ['score' => self::total($items, $picks), 'items' => $entries];
    }

    /** The total of the option picked for each item. */
    private static function total(array $items, array $picks): int
    {
        $total = 0;

        foreach ($items as $i => $item) {
            $total += $item['options'][$picks[$i]]['value'];
        }

        return $total;
    }

    /** A total inside a band of the given tone, for a scale recorded as a total alone. */
    private static function scoreEntry(array $definition, string $tone): array
    {
        $band = self::bandOfTone($definition, $tone);

        return [
            'score' => mt_rand($band['min'] ?? $definition['score_min'], $band['max'] ?? $definition['score_max']),
            'items' => null,
        ];
    }

    /** One of the scale's bands in the given tone, or the nearest tone it has. */
    private static function bandOfTone(array $definition, string $tone): array
    {
        $bands = collect($definition['bands']);
        $distance = fn (array $band) => abs(self::TONE_RANK[$band['tone']] - self::TONE_RANK[$tone]);
        $nearest = $bands->min($distance);

        return $bands->filter(fn (array $band) => $distance($band) === $nearest)->random();
    }

    /** The band tone an entry at this severity should land in. */
    private static function tone(float $severity): string
    {
        return match (true) {
            $severity >= 0.7 => ClinicalIndicatorLibrary::TONE_HIGH,
            $severity >= 0.4 => ClinicalIndicatorLibrary::TONE_MODERATE,
            default => ClinicalIndicatorLibrary::TONE_LOW,
        };
    }

    /** Mostly well, a fair share abnormal and now and then at escalation. */
    private static function randomSeverity(): float
    {
        $roll = mt_rand(1, 100);

        return match (true) {
            $roll <= 60 => self::between(0.0, 0.3),
            $roll <= 90 => self::between(0.4, 0.65),
            default => self::between(0.75, 1.0),
        };
    }

    private static function jitter(float $spread): float
    {
        return self::between(-$spread, $spread);
    }

    private static function between(float $low, float $high): float
    {
        return $low + mt_rand() / mt_getrandmax() * ($high - $low);
    }
}
