<?php

namespace App\Support;

use App\Models\ClinicalIndicator;
use App\Models\ClinicalIndicatorScore;
use App\Models\Patient;
use App\Models\Ward;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Scales whose items are readings typed in from the bedside monitor, such as
 * the invasive hemodynamic numerics or the ventilator settings, rather than
 * options picked and totalled.
 *
 * A reading item carries its `unit`, the `decimals` it is recorded to, the
 * `limits` a monitor can plausibly show (anything outside is a typo), the
 * adult `normal` range, and optionally an escalation level: `escalate_below`
 * and/or `escalate_above`. Each reading is flagged normal, low or high
 * (outside the normal range) or escalate (past an escalation level).
 *
 * The score is the worst flag rather than a total: 0 when every reading is
 * normal, 1 when one is outside its normal range, 2 when one has reached an
 * escalation level. The scale's bands read that score like any other, so the
 * stored band, monitoring and summaries work unchanged.
 *
 * Items can share a `group` (arterial systolic, diastolic and mean come off
 * one line together), which is entered in full or not at all, and is shown in
 * the definition's `groups` format, such as 120/80 (93). `checks` are the
 * relations the readings must keep, such as diastolic below systolic.
 *
 * On the patient's trend graph an item's `trend` marks the values the ICU
 * steers by: 'line' draws its escalation level as a dashed line, 'band' also
 * shades its normal range. The graph has one chart per key of the definition's
 * `charts` (key => title), and each item is drawn on the one its `chart` names.
 *
 * A definition's `dashboard` names the readings the critical care bed cards
 * show, such as the ventilator's FiO2 and PEEP: see recentForPatients(). Its
 * `notes_example` is the hint shown in the notes field of the readings form.
 */
final class ClinicalIndicatorReadings
{
    /**
     * How far back the critical care bed cards look for readings. A scale not
     * recorded for this long drops off the card rather than showing values
     * that may no longer be current, such as a patient since extubated.
     */
    public const RECENT_HOURS = 12;

    public const FLAG_NORMAL = 'normal';
    public const FLAG_LOW = 'low';
    public const FLAG_HIGH = 'high';
    public const FLAG_ESCALATE_LOW = 'escalate_low';
    public const FLAG_ESCALATE_HIGH = 'escalate_high';

    public const FLAGS = [
        self::FLAG_NORMAL => ['label' => 'Normal', 'grade' => 0, 'tone' => ClinicalIndicatorLibrary::TONE_LOW, 'arrow' => ''],
        self::FLAG_LOW => ['label' => 'Low', 'grade' => 1, 'tone' => ClinicalIndicatorLibrary::TONE_MODERATE, 'arrow' => '↓'],
        self::FLAG_HIGH => ['label' => 'High', 'grade' => 1, 'tone' => ClinicalIndicatorLibrary::TONE_MODERATE, 'arrow' => '↑'],
        self::FLAG_ESCALATE_LOW => ['label' => 'Low - escalate', 'grade' => 2, 'tone' => ClinicalIndicatorLibrary::TONE_HIGH, 'arrow' => '↓↓'],
        self::FLAG_ESCALATE_HIGH => ['label' => 'High - escalate', 'grade' => 2, 'tone' => ClinicalIndicatorLibrary::TONE_HIGH, 'arrow' => '↑↑'],
    ];

    /**
     * Where one reading sits against its item's normal range and escalation levels.
     */
    public static function flag(array $item, float $value): string
    {
        if (isset($item['escalate_below']) && $value < $item['escalate_below']) {
            return self::FLAG_ESCALATE_LOW;
        }
        if (isset($item['escalate_above']) && $value > $item['escalate_above']) {
            return self::FLAG_ESCALATE_HIGH;
        }
        if ($value < $item['normal'][0]) {
            return self::FLAG_LOW;
        }
        if ($value > $item['normal'][1]) {
            return self::FLAG_HIGH;
        }

        return self::FLAG_NORMAL;
    }

    /**
     * Validate readings posted by item index, flag each one and grade them.
     *
     * @return array{error: ?string, score: ?int, items: array<int, array<string, mixed>>}
     */
    public static function evaluate(array $definition, array $posted): array
    {
        $values = [];

        foreach ($definition['items'] as $index => $item) {
            $raw = $posted[$index] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            if (!is_numeric($raw)) {
                return self::error('Enter ' . $item['name'] . ' as a number.');
            }

            $decimals = $item['decimals'] ?? 0;
            $value = round((float) $raw, $decimals);
            [$min, $max] = $item['limits'];

            if ($value < $min || $value > $max) {
                return self::error($item['name'] . ' of ' . self::format($value, $decimals) . ' ' . $item['unit']
                    . ' is outside what a monitor shows (' . self::format($min, $decimals) . ' to '
                    . self::format($max, $decimals) . '). Check the reading.');
            }

            $values[$item['abbr']] = ['item' => $item, 'value' => $decimals === 0 ? (int) $value : $value];
        }

        if ($values === []) {
            return self::error('Enter at least one reading before saving.');
        }

        foreach (self::groupMembers($definition) as $group => $abbrs) {
            $entered = count(array_intersect($abbrs, array_keys($values)));

            if ($entered > 0 && $entered < count($abbrs)) {
                $label = $definition['groups'][$group]['label'] ?? $group;

                return self::error('Enter ' . self::listing($abbrs, 'and') . ' together for ' . lcfirst($label) . '.');
            }
        }

        foreach ($definition['checks'] ?? [] as [$left, $operator, $right, $message]) {
            if (!isset($values[$left], $values[$right])) {
                continue;
            }

            $holds = $operator === '<'
                ? $values[$left]['value'] < $values[$right]['value']
                : $values[$left]['value'] > $values[$right]['value'];

            if (!$holds) {
                return self::error($message);
            }
        }

        $grade = 0;
        $entries = [];

        foreach ($values as $abbr => ['item' => $item, 'value' => $value]) {
            $flag = self::flag($item, (float) $value);
            $grade = max($grade, self::FLAGS[$flag]['grade']);

            $entries[] = [
                'name' => $item['name'],
                'abbr' => $abbr,
                'value' => $value,
                'unit' => $item['unit'],
                'flag' => $flag,
                'label' => self::FLAGS[$flag]['label'],
            ];
        }

        return ['error' => null, 'score' => $grade, 'items' => $entries];
    }

    /**
     * The item's ranges as readable text, e.g. "Normal 90 to 140 mmHg.
     * Escalate below 90 or above 180", for the reference panel. Compact, for
     * a caption beside an input that already shows the unit, it reads
     * "Normal 90–140 · Escalate <90 or >180".
     */
    public static function rangeText(array $item, bool $compact = false): string
    {
        $decimals = $item['decimals'] ?? 0;
        [$low, $high] = [self::format($item['normal'][0], $decimals), self::format($item['normal'][1], $decimals)];

        $escalate = array_filter([
            isset($item['escalate_below']) ? ($compact ? '<' : 'below ') . self::format($item['escalate_below'], $decimals) : null,
            isset($item['escalate_above']) ? ($compact ? '>' : 'above ') . self::format($item['escalate_above'], $decimals) : null,
        ]);

        if ($compact) {
            return 'Normal ' . $low . '–' . $high . ($escalate ? ' · Escalate ' . implode(' or ', $escalate) : '');
        }

        $text = 'Normal ' . $low . ' to ' . $high . ' ' . $item['unit'];

        return $escalate ? $text . '. Escalate ' . implode(' or ', $escalate) : $text;
    }

    /**
     * Recorded readings as one line, e.g. "ABP 85/42 (56) mmHg ↓↓ · CVP 14 mmHg ↑".
     * Given the scale's definition, a complete group is shown in its format;
     * without it every reading is listed on its own.
     */
    public static function summary(array $entries, ?array $definition = null): string
    {
        return collect(self::parts($entries, $definition))
            ->map(fn (array $part) => trim($part['label'] . ' ' . $part['text'] . ' ' . $part['unit'] . ' ' . self::FLAGS[$part['flag']]['arrow']))
            ->implode(' · ');
    }

    /**
     * The recorded readings ready to show, grouped where the definition says
     * so: label, value text, unit and the worst flag among the part's readings.
     *
     * @return array<int, array{label: string, text: string, unit: string, flag: string}>
     */
    public static function parts(array $entries, ?array $definition = null): array
    {
        $byAbbr = collect($entries)->keyBy('abbr');
        $members = $definition ? self::groupMembers($definition) : [];
        $shown = [];
        $parts = [];

        foreach ($entries as $entry) {
            if (isset($shown[$entry['abbr']])) {
                continue;
            }

            $group = collect($members)->search(fn (array $abbrs) => in_array($entry['abbr'], $abbrs, true));
            $format = $group !== false ? ($definition['groups'][$group]['format'] ?? null) : null;

            if ($format && $byAbbr->has($members[$group])) {
                $text = $format;
                $flag = self::FLAG_NORMAL;
                foreach ($members[$group] as $abbr) {
                    $member = $byAbbr[$abbr];
                    $text = str_replace('{' . $abbr . '}', self::display($member, $definition), $text);
                    $flag = self::worse($flag, $member['flag']);
                    $shown[$abbr] = true;
                }
                $parts[] = ['label' => $group, 'text' => $text, 'unit' => $entry['unit'], 'flag' => $flag];

                continue;
            }

            $shown[$entry['abbr']] = true;
            $parts[] = [
                'label' => $entry['abbr'],
                'text' => self::display($entry, $definition),
                'unit' => $entry['unit'],
                'flag' => $entry['flag'] ?? self::FLAG_NORMAL,
            ];
        }

        return $parts;
    }

    /**
     * A number to the item's decimals, dropping trailing zeros but keeping
     * one decimal where the item has them (2.50 -> 2.5, 4 -> 4.0).
     */
    public static function format(float|int $value, int $decimals): string
    {
        if ($decimals === 0) {
            return (string) (int) round($value);
        }

        $text = number_format((float) $value, $decimals, '.', '');

        return preg_replace('/(\.\d*?[1-9])0+$|(\.0)0+$/', '$1$2', $text);
    }

    /**
     * A stored reading: whole numbers as recorded, decimals as format() shows them.
     * The JSON column turns 5.0 back into 5, so where the item is known use display().
     */
    public static function formatStored(float|int|string $value): string
    {
        return is_int($value) ? (string) $value : self::format((float) $value, 2);
    }

    /**
     * A recorded reading to its item's decimals when the definition is at hand
     * (CO 5 reads 5.0), otherwise as stored.
     */
    public static function display(array $entry, ?array $definition = null): string
    {
        foreach ($definition['items'] ?? [] as $item) {
            if ($item['abbr'] === $entry['abbr']) {
                return self::format((float) $entry['value'], $item['decimals'] ?? 0);
            }
        }

        return self::formatStored($entry['value']);
    }

    /**
     * Abbreviations of the items in each group, keyed by group.
     *
     * @return array<string, array<int, string>>
     */
    public static function groupMembers(array $definition): array
    {
        $groups = [];

        foreach ($definition['items'] ?? [] as $item) {
            if (!empty($item['group'])) {
                $groups[$item['group']][] = $item['abbr'];
            }
        }

        return $groups;
    }

    /**
     * What the critical care bed cards show of each patient's readings. For
     * every scale bound to the patient's ward type whose definition names
     * `dashboard` readings: the latest value of each parameter recorded in the
     * last RECENT_HOURS of this admission, as the patient's tiles take them,
     * and the worst flag among them. Keyed by patient id, then scale code;
     * patients with nothing recent are left out.
     *
     * With $everyScale, every monitor-readings scale the ward records counts,
     * not only those on the bed cards: the Command Center's view, which
     * watches hemodynamics for escalation too.
     *
     * @param  iterable<Patient>  $patients
     * @return array<int, array<string, array<string, mixed>>>
     */
    public static function recentForPatients(iterable $patients, ?CarbonInterface $now = null, bool $everyScale = false): array
    {
        $patients = collect($patients)->filter(fn (Patient $patient) => $patient->ward_id);

        if ($patients->isEmpty()) {
            return [];
        }

        // Looked up per ward rather than per patient: a dashboard is one ward.
        $indicatorsByWard = Ward::with('wardType.clinicalIndicators')
            ->whereIn('id', $patients->pluck('ward_id')->unique())
            ->get()
            ->mapWithKeys(fn (Ward $ward) => [$ward->id => self::readingIndicators($ward, $everyScale)]);

        $indicatorIds = $indicatorsByWard->collapse()->pluck('id')->unique();

        if ($indicatorIds->isEmpty()) {
            return [];
        }

        $records = ClinicalIndicatorScore::query()
            ->whereIn('patient_id', $patients->pluck('id'))
            ->whereIn('clinical_indicator_id', $indicatorIds)
            ->where('recorded_at', '>=', Carbon::instance($now ?? now())->subHours(self::RECENT_HOURS))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get(['patient_id', 'clinical_indicator_id', 'item_scores', 'recorded_at'])
            ->groupBy(fn (ClinicalIndicatorScore $record) => $record->patient_id . ':' . $record->clinical_indicator_id);

        $recent = [];

        foreach ($patients as $patient) {
            foreach ($indicatorsByWard->get($patient->ward_id, collect()) as $indicator) {
                // This admission only, as on the patient's trend
                $history = $records->get($patient->id . ':' . $indicator->id, collect())
                    ->filter(fn (ClinicalIndicatorScore $record) => !$patient->admitted_at || $record->recorded_at->gte($patient->admitted_at));

                $set = $history->isNotEmpty() ? self::latestSet($indicator, $history) : null;

                if ($set && $set['readings']) {
                    $recent[$patient->id][$indicator->code] = $set;
                }
            }
        }

        return $recent;
    }

    /**
     * The latest value of each parameter across records given newest first,
     * in the definition's order. The headline is the `dashboard` readings
     * where they were recorded, otherwise the first two that were.
     */
    private static function latestSet(ClinicalIndicator $indicator, Collection $history): array
    {
        $definition = $indicator->definition();
        $latest = [];

        foreach ($history as $record) {
            foreach ($record->item_scores ?? [] as $entry) {
                $latest[$entry['abbr']] ??= $entry + ['at' => $record->recorded_at];
            }
        }

        $readings = [];
        $worst = self::FLAG_NORMAL;

        foreach ($definition['items'] as $item) {
            if (!isset($latest[$item['abbr']])) {
                continue;
            }

            $entry = $latest[$item['abbr']];
            $flag = $entry['flag'] ?? self::FLAG_NORMAL;
            $worst = self::worse($worst, $flag);

            $readings[] = [
                'abbr' => $item['abbr'],
                'name' => $item['name'],
                'text' => self::display($entry, $definition),
                'unit' => $item['unit'],
                'flag' => $flag,
                'at' => $entry['at'],
            ];
        }

        $byAbbr = collect($readings)->keyBy('abbr');
        $headline = collect($definition['dashboard'] ?? [])
            ->map(fn (string $abbr) => $byAbbr->get($abbr))
            ->filter()
            ->values()
            ->all();

        return [
            'indicator_id' => $indicator->id,
            'code' => $indicator->code,
            'name' => $indicator->name,
            'category' => $indicator->category(),
            'readings' => $readings,
            'headline' => $headline ?: array_slice($readings, 0, 2),
            'flag' => $worst,
            'status' => ClinicalIndicatorLibrary::bandFor($indicator->code, self::FLAGS[$worst]['grade'])['label'] ?? null,
            'at' => $history->first()->recorded_at,
        ];
    }

    /**
     * The active monitor-readings scales bound to the ward's ward type: those
     * shown on the bed cards, or with $everyScale all of them.
     */
    private static function readingIndicators(Ward $ward, bool $everyScale): Collection
    {
        $wardType = $ward->wardType;

        if (!$wardType || !$wardType->is_active) {
            return collect();
        }

        return $wardType->clinicalIndicators
            ->filter(function (ClinicalIndicator $indicator) use ($everyScale) {
                $definition = $indicator->definition();

                return $indicator->is_active
                    && ($everyScale || !empty($definition['dashboard']))
                    && ClinicalIndicatorLibrary::takesReadings($definition);
            })
            ->values();
    }

    private static function worse(string $flag, string $other): string
    {
        return self::FLAGS[$other]['grade'] > self::FLAGS[$flag]['grade'] ? $other : $flag;
    }

    private static function listing(array $words, string $last): string
    {
        return count($words) > 1
            ? implode(', ', array_slice($words, 0, -1)) . ' ' . $last . ' ' . end($words)
            : implode('', $words);
    }

    private static function error(string $message): array
    {
        return ['error' => $message, 'score' => null, 'items' => []];
    }
}
