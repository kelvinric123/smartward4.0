<?php

namespace App\Services;

use App\Models\LabInvestigation;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Support\OxygenTherapyChart;
use Carbon\CarbonInterface;

/**
 * Sections of a patient's chart that the mobile apps show the way Patient Details
 * does: the Lab Investigations and Oxygen Therapy tabs, as JSON-ready arrays with
 * times pre-formatted in the hospital's timezone.
 *
 * The nurse app reads them from here. The doctor app builds the same shapes in
 * DoctorAppPatientChart, so a section means the same thing in both apps.
 */
class PatientChartSections
{
    // -------------------------------------------------- lab investigations

    /**
     * The Lab Investigations tab: the same list Patient Details shows (unreviewed results
     * first), with each result's flags and when it should be reviewed. Off when the ward
     * system has the tab switched off.
     */
    public static function labs(Patient $patient): array
    {
        $settings = LabInvestigations::settings();
        $counts = ['awaiting_review' => 0, 'overdue' => 0, 'critical' => 0, 'pending' => 0];

        if (!$settings['enabled']) {
            return ['enabled' => false, 'sample' => false, 'items' => [], 'counts' => $counts];
        }

        // Sample results appear the way Patient Details shows them, when switched on
        if ($settings['sample']) {
            LabInvestigations::ensureSamples($patient);
        }

        $now = now();
        $items = LabInvestigation::forPatient($patient, $settings['sample'])
            ->map(fn (LabInvestigation $lab) => self::lab($lab, $now))
            ->values();

        return [
            'enabled' => true,
            'sample' => $settings['sample'],
            'items' => $items->all(),
            'counts' => [
                'awaiting_review' => $items->where('can_review', true)->count(),
                'overdue' => $items->where('review_state', 'overdue')->count(),
                'critical' => $items->filter(fn (array $item) => $item['can_review'] && $item['flag'] === 'critical')->count(),
                'pending' => $items->whereIn('status', ['ordered', 'collected', 'in_progress'])->count(),
            ],
        ];
    }

    private static function lab(LabInvestigation $lab, CarbonInterface $now): array
    {
        $state = $lab->reviewState($now);

        return [
            'id' => $lab->id,
            'test_name' => $lab->test_name,
            'test_code' => $lab->test_code,
            'order_no' => $lab->order_no,
            'category' => $lab->categoryLabel(),
            'specimen' => $lab->specimen,
            'priority' => $lab->priority,
            'priority_label' => $lab->priorityLabel(),
            'status' => $lab->status,
            'status_label' => $lab->statusLabel(),
            'ordered_label' => $lab->ordered_at?->format('d M H:i'),
            'ordered_by' => $lab->ordered_by,
            'collected_label' => $lab->collected_at?->format('d M H:i'),
            'resulted_label' => $lab->resulted_at?->format('d M H:i'),
            'results' => collect($lab->results ?? [])
                ->filter(fn ($row) => is_array($row))
                ->map(function (array $row) {
                    $flag = strtoupper(trim((string) ($row['flag'] ?? '')));

                    return [
                        'name' => (string) ($row['name'] ?? ''),
                        'value' => (string) ($row['value'] ?? ''),
                        'unit' => (string) ($row['unit'] ?? ''),
                        'range' => (string) ($row['range'] ?? ''),
                        'flag' => $flag === 'N' ? '' : $flag,
                        'level' => in_array($flag, LabInvestigation::CRITICAL_FLAGS, true)
                            ? 'critical'
                            : ($flag !== '' && $flag !== 'N' ? 'abnormal' : null),
                    ];
                })
                ->values()
                ->all(),
            'flag' => $lab->resultFlag(),
            'comment' => $lab->comment,
            'review_state' => $state,
            'review_label' => self::reviewLabel($lab, $state),
            'can_review' => $lab->awaitingReview(),
            'sample' => $lab->isSample(),
        ];
    }

    /** "Review overdue since 05 Oct 14:00", "Reviewed 05 Oct 15:12 by Dr. Tan", ... */
    private static function reviewLabel(LabInvestigation $lab, string $state): ?string
    {
        $due = $lab->reviewDueAt()?->format('d M H:i');

        return match ($state) {
            'overdue' => 'Review overdue since ' . $due,
            'due_soon' => 'Review due soon, by ' . $due,
            'due' => 'Review by ' . $due,
            'awaiting_result' => 'Awaiting result',
            'result_late' => 'Result late, expected by ' . $due,
            'reviewed' => 'Reviewed ' . $lab->reviewed_at->format('d M H:i') . ($lab->reviewed_by_name ? ' by ' . $lab->reviewed_by_name : ''),
            default => null,
        };
    }

    // ------------------------------------------------------------ oxygen

    /**
     * The Oxygen Therapy tab: the oxygen now (changed on the ward or recorded with vital
     * signs), the SpO2 target, the latest SpO2 against it, every setting this admission
     * (newest first, struck-out changes included), and the progression chart.
     */
    public static function oxygen(Patient $patient): array
    {
        $oxygen = OxygenTherapyChart::forPatient($patient);
        $current = $oxygen['current'];
        $target = $current['target'] ?? null;
        $latest = $oxygen['latest_spo2'];
        $state = self::freshSpo2State($oxygen);
        $since = $oxygen['on_oxygen_since'];
        $preset = $target
            ? collect(OxygenTherapyChange::TARGET_PRESETS)->first(fn (array $p) => [$p['min'], $p['max']] === $target)
            : null;

        return [
            'current' => $current ? [
                'device' => $current['delivery'],
                'label' => $current['label'],
                'abbr' => $current['abbr'],
                'short' => $current['short'],
                'settings' => $current['settings'],
                'on_oxygen' => $current['on_oxygen'],
                'since_label' => $current['at']->format('d M H:i'),
                'duration_label' => OxygenTherapyChart::durationLabel($current['minutes']),
                'source' => $current['source'],
                'by' => $current['by'],
                'notes' => $current['notes'],
            ] : null,
            'target' => $target ? [
                'min' => $target[0],
                'max' => $target[1],
                'label' => OxygenTherapyChange::targetLabel($target),
                'note' => $preset['label'] ?? null,
            ] : null,
            'latest_spo2' => $latest ? [
                'value' => $latest['spo2'],
                'time_label' => $latest['at']->format('d M H:i'),
                'on' => $latest['setting']['short'] ?? null,
                'state' => $latest['stale'] ? null : $latest['state'],
                'stale' => $latest['stale'],
            ] : null,
            // Below the target is the one to act on; above it on oxygen is room to wean
            'alert' => $state === 'below' ? 'critical' : ($state === 'above' ? 'warning' : null),
            'on_oxygen_since_label' => $since?->format('d M H:i'),
            'on_oxygen_duration_label' => $since ? OxygenTherapyChart::durationLabel((int) round(abs($since->diffInMinutes(now())))) : null,
            // Newest first, struck-out changes included
            'history' => array_map(fn (array $entry) => [
                'key' => $entry['key'],
                // A change made on the Oxygen Therapy tab can be struck out; a vital signs reading cannot
                'change_id' => $entry['source'] === 'therapy' ? $entry['record']->id : null,
                'time_label' => $entry['at']->format('d M H:i'),
                'device' => $entry['delivery'],
                'label' => $entry['label'],
                'short' => $entry['short'],
                'settings' => $entry['on_oxygen'] ? $entry['settings'] : null,
                'on_oxygen' => $entry['on_oxygen'],
                'target_label' => OxygenTherapyChange::targetLabel($entry['target']),
                'duration_label' => $entry['voided'] ? null
                    : OxygenTherapyChart::durationLabel($entry['minutes']) . ($entry['until'] ? '' : ' so far'),
                'current' => $current !== null && $entry['key'] === $current['key'],
                'source' => $entry['source'],
                'by' => $entry['by'],
                'notes' => $entry['notes'],
                'spo2' => $entry['spo2'],
                'voided' => $entry['voided'],
                'void_reason' => $entry['voided'] ? $entry['record']->void_reason : null,
            ], $oxygen['history']),
        ];
    }

    /** How the latest SpO2 reads against the target, unless it was taken before the oxygen last changed. */
    private static function freshSpo2State(array $oxygen): ?string
    {
        $latest = $oxygen['latest_spo2'];

        return $latest && !$latest['stale'] ? $latest['state'] : null;
    }
}
