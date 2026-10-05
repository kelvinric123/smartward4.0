<?php

namespace App\Support;

/**
 * A clinical indicator recorded as a screen rather than a total: questions
 * asked in order, some only after a given answer to an earlier one, with the
 * most serious answer setting the result. This is how the C-SSRS screener
 * triages suicide risk (see the CSSRS entry in ClinicalIndicatorLibrary).
 *
 * Each option's value is the level of risk that answer points to (0 none),
 * and the scale's bands name each level and the response it calls for. An
 * item with `asked_when => [abbr, label]` is asked only after that answer to
 * the earlier item; otherwise it is recorded as not asked, whatever was posted.
 */
final class ClinicalIndicatorScreen
{
    public const NOT_ASKED = 'Not asked';

    /**
     * Whether an item is asked, given the answers before it (option labels
     * by item abbr).
     */
    public static function isAsked(array $item, array $answers): bool
    {
        if (empty($item['asked_when'])) {
            return true;
        }

        [$abbr, $label] = $item['asked_when'];

        return ($answers[$abbr] ?? null) === $label;
    }

    /**
     * Check the answers posted for a screen (option values by item index) and
     * work out its result: the highest level any answer points to. Every
     * question asked must be answered; answers to questions not asked are
     * dropped, so the record shows the screen as it was actually put.
     *
     * Each item is recorded with its name, abbr, the answer's label and value,
     * and whether it was asked, which is what marks the record as a screen.
     *
     * @return array{score: ?int, items: array<int, array<string, mixed>>, error: ?string}
     */
    public static function evaluate(array $definition, array $posted): array
    {
        $answers = [];
        $items = [];
        $score = 0;

        foreach (array_values($definition['items']) as $index => $item) {
            $entry = ['name' => $item['name'], 'abbr' => $item['abbr']];

            if (!self::isAsked($item, $answers)) {
                $items[] = $entry + ['label' => self::NOT_ASKED, 'value' => null, 'asked' => false];
                continue;
            }

            $value = $posted[$index] ?? null;
            if ($value === null || $value === '') {
                return self::refused('Answer ' . $item['abbr'] . ' (' . $item['name'] . ') before saving.');
            }

            $option = collect($item['options'])->first(fn (array $option) => (string) $option['value'] === (string) $value);
            if ($option === null) {
                return self::refused('That is not a valid answer for ' . $item['name'] . '.');
            }

            $answers[$item['abbr']] = $option['label'];
            $entry += ['label' => $option['label'], 'value' => $option['value'], 'asked' => true];
            if (isset($option['short'])) {
                $entry['short'] = $option['short'];
            }
            $items[] = $entry;
            $score = max($score, $option['value']);
        }

        return ['score' => $score, 'items' => $items, 'error' => null];
    }

    /**
     * The answers that point to any risk, by question, such as "Yes to Q1,
     * Q2, Q6 (within the past 3 months)". Null when none does.
     */
    public static function summary(array $items): ?string
    {
        $positive = collect($items)
            ->filter(fn (array $entry) => ($entry['value'] ?? 0) > 0)
            ->map(fn (array $entry) => $entry['abbr'] . (isset($entry['short']) ? ' (' . $entry['short'] . ')' : ''));

        return $positive->isEmpty() ? null : 'Yes to ' . $positive->implode(', ');
    }

    private static function refused(string $error): array
    {
        return ['score' => null, 'items' => [], 'error' => $error];
    }
}
