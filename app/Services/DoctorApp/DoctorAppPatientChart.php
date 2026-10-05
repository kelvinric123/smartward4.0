<?php

namespace App\Services\DoctorApp;

use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\LabInvestigation;
use App\Models\MedicationAdministration;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMedication;
use App\Services\FluidBalanceLinks;
use App\Services\LabInvestigations;
use App\Support\FluidBalanceChart;
use App\Support\OxygenTherapyChart;
use Carbon\CarbonInterface;

/**
 * The doctor app's patient chart as one JSON-ready array: the I/O chart for
 * one chart day, the medication orders with their recent doses, the
 * consultant orders, the oxygen therapy and the lab investigations. It reads
 * the same helpers the ward dashboard uses (FluidBalanceChart::forPatient,
 * PatientMedication::forPatient, ConsultantOrder::tabFor,
 * OxygenTherapyChart::forPatient, LabInvestigation::forPatient), so the
 * consultant sees what the ward sees. Times go out pre-formatted in the
 * hospital's timezone.
 */
class DoctorAppPatientChart
{
    /** How many stopped medications and closed orders travel with the chart. */
    private const HISTORY_LIMIT = 15;

    /** How many recent doses each medication order shows. */
    private const DOSES_PER_ORDER = 5;

    public const ORDER_STATUS_LABELS = [
        ConsultantOrder::STATUS_OPEN => 'Open',
        ConsultantOrder::STATUS_DONE => 'Done',
        ConsultantOrder::STATUS_CANCELLED => 'Cancelled',
    ];

    public static function build(Patient $patient, Consultant $consultant, ?string $ioDay = null): array
    {
        $patient->loadMissing(['ward', 'consultant']);

        $chart = [
            'patient' => self::patient($patient),
            'io' => self::io(FluidBalanceChart::forPatient($patient, $ioDay)),
            'medications' => self::medications($patient),
            'orders' => self::orders($patient, $consultant),
            'oxygen' => self::oxygen($patient),
            'labs' => self::labs($patient),
            'generated_label' => now()->format('H:i'),
        ];

        $oxygenNow = $chart['oxygen']['current'];
        $chart['badges'] = [
            'io_level' => $chart['io']['day']['is_current'] ? ($chart['io']['alerts'][0]['level'] ?? null) : null,
            'meds_overdue' => $chart['medications']['counts']['overdue'],
            'orders_open' => count($chart['orders']['open']),
            'oxygen_short' => $oxygenNow && $oxygenNow['on_oxygen'] ? $oxygenNow['short'] : null,
            'oxygen_level' => $chart['oxygen']['alert'],
            'labs_review' => $chart['labs']['counts']['awaiting_review'],
            'labs_overdue' => $chart['labs']['counts']['overdue'],
        ];

        return $chart;
    }

    /**
     * The patient's allergies as the ward lists them: the allergen, its severity when one
     * was recorded (optional; the ADT feed sends it in AL1-4) and whether it is resolved.
     * Active ones first, the most severe first among them.
     */
    public static function allergies(Patient $patient): array
    {
        $rank = ['Severe' => 0, 'Moderate' => 1, 'Mild' => 2];

        return collect($patient->allergies ?? [])
            ->map(function ($allergy) {
                $raw = trim(is_array($allergy) ? (string) ($allergy['allergen'] ?? $allergy['allergen_code'] ?? '') : (string) $allergy);
                // Older ADT entries carry "code^description"
                $name = str_contains($raw, '^') ? (trim(explode('^', $raw)[1] ?? '') ?: $raw) : $raw;
                $severity = Patient::allergySeverity($allergy);

                return [
                    'name' => $name,
                    'severity' => $severity,
                    'resolved' => is_array($allergy) && ($allergy['status'] ?? null) === 'Resolved',
                    'label' => $name . ($severity ? ' (' . $severity . ')' : ''),
                ];
            })
            ->reject(fn (array $allergy) => $allergy['name'] === '')
            ->sortBy(fn (array $allergy) => [$allergy['resolved'] ? 1 : 0, $rank[$allergy['severity']] ?? 3])
            ->values()
            ->all();
    }

    /**
     * The oxygen now, for the dashboard's bed card: null when none is recorded this admission.
     */
    public static function oxygenBrief(Patient $patient): ?array
    {
        $oxygen = OxygenTherapyChart::forPatient($patient);
        $current = $oxygen['current'];
        if (!$current) {
            return null;
        }

        return [
            'on_oxygen' => $current['on_oxygen'],
            'short' => $current['short'],
            'label' => $current['label'],
            'settings' => $current['settings'],
            'since_label' => $current['at']->format('d M H:i'),
            'target_label' => OxygenTherapyChange::targetLabel($current['target']),
            'spo2_state' => self::freshSpo2State($oxygen),
        ];
    }

    private static function patient(Patient $patient): array
    {
        $attending = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();
        $allergies = self::allergies($patient);

        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'mrn' => $patient->mrn,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'bed' => $patient->bed_number,
            'ward' => $patient->ward?->ward_name,
            'consultant' => $attending?->display_name ?? $patient->consultant?->name,
            'admitted_label' => $patient->admitted_at?->format('d M Y, H:i'),
            'vip' => $patient->vipStatusLabel(),
            // The active ones as text, "Penicillin (Severe)", which older app versions print as they are
            'allergies' => array_values(array_column(array_filter($allergies, fn (array $allergy) => !$allergy['resolved']), 'label')),
            'allergy_list' => $allergies,
        ];
    }

    // ------------------------------------------------------------ oxygen

    /**
     * The Oxygen Therapy tab, read-only: the oxygen now (changed on the ward or recorded
     * with vital signs), the SpO2 target, the latest SpO2 against it, every setting this
     * admission, and the progression chart. A consultant changes oxygen by writing an order.
     */
    private static function oxygen(Patient $patient): array
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
            // Times are the hospital's wall clock in "UTC" milliseconds, as on the ward dashboard
            'chart' => $oxygen['chart'],
        ];
    }

    /** How the latest SpO2 reads against the target, unless it was taken before the oxygen last changed. */
    private static function freshSpo2State(array $oxygen): ?string
    {
        $latest = $oxygen['latest_spo2'];

        return $latest && !$latest['stale'] ? $latest['state'] : null;
    }

    // -------------------------------------------------- lab investigations

    /**
     * The Lab Investigations tab: the same list Patient Details shows (unreviewed results
     * first), with each result's flags and when it should be reviewed. Off when the ward
     * system has the tab switched off.
     */
    private static function labs(Patient $patient): array
    {
        $settings = LabInvestigations::settings();
        $counts = ['awaiting_review' => 0, 'overdue' => 0, 'critical' => 0, 'pending' => 0];

        if (!$settings['enabled']) {
            return ['enabled' => false, 'items' => [], 'counts' => $counts];
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

    // --------------------------------------------------------------- I/O

    private static function io(array $chart): array
    {
        $day = $chart['day'];
        $plan = $chart['plan'];
        $status = $chart['status'];
        $latest = $chart['latest_assessment'];

        $byType = [];
        foreach ($chart['totals']['by_type'] as $direction => $types) {
            foreach ($types as $type => $volume) {
                $byType[] = [
                    'direction' => $direction,
                    'type' => $type,
                    'label' => FluidBalanceEntry::typesFor($direction)[$type] ?? ucfirst($type),
                    'volume' => $volume,
                ];
            }
        }

        return [
            'day' => [
                'key' => $day['key'],
                'label' => ($day['is_current'] ? 'Today' : $day['start']->format('D j M'))
                    . ', ' . $day['start']->format('H:i') . ' to ' . $day['end']->format('H:i'),
                'is_current' => $day['is_current'],
                'previous' => $day['previous'],
                'next' => $day['next'],
            ],
            'totals' => [
                'intake' => $chart['totals']['intake'],
                'output' => $chart['totals']['output'],
                'balance' => $chart['totals']['balance'],
                'urine' => $chart['totals']['urine'],
            ],
            'by_type' => $byType,
            'plan' => $plan && ($plan->hasLimits() || $plan->notes) ? [
                'intake_limit_ml' => $plan->intake_limit_ml,
                'urine_min_ml_per_hour' => $plan->urine_min_ml_per_hour,
                'summary' => $plan->summary(),
                'notes' => $plan->notes,
                'set_label' => 'Set ' . $plan->created_at->format('j M H:i') . ($plan->setBy ? ' by ' . $plan->setBy->name : ''),
                'from_order' => $plan->consultant_order_id !== null,
            ] : null,
            'limit' => $status['limit'],
            'urine' => $status['urine'],
            'alerts' => array_map(fn (array $alert) => [
                'level' => $alert['level'],
                'title' => $alert['title'],
                'detail' => $alert['detail'],
            ], $status['alerts']),
            // Newest first
            'entries' => $chart['entries']->reverse()->map(fn (FluidBalanceEntry $entry) => [
                'id' => $entry->id,
                'time_label' => $entry->recorded_at->format('H:i'),
                'direction' => $entry->direction,
                'type_label' => $entry->typeLabel(),
                'description' => $entry->description,
                'volume_ml' => $entry->volume_ml,
                'auto' => $entry->sourceLabel(),
                'by' => $entry->recordedBy?->name,
                'voided' => $entry->isVoided(),
                'void_reason' => $entry->void_reason,
            ])->values()->all(),
            'overload' => $latest ? [
                'time_label' => $latest->assessed_at->format('d M H:i'),
                'edema' => $latest->edemaSummary(),
                'signs' => $latest->signLabels(),
                'urgent' => $latest->hasUrgentSign(),
                'weight_kg' => $latest->weight_kg !== null ? (float) $latest->weight_kg : null,
                'by' => $latest->recordedBy?->name,
            ] : null,
            'weight' => $chart['weight'] ? [
                'kg' => $chart['weight']['kg'],
                'change' => $chart['weight']['change'],
                'at_label' => $chart['weight']['at']->format('d M'),
                'gain' => $chart['weight']['gain'],
            ] : null,
            'days' => array_map(fn (array $row) => [
                'key' => $row['key'],
                'label' => $row['is_current'] ? 'Today' : $row['start']->format('D j M'),
                'intake' => $row['intake'],
                'output' => $row['output'],
                'balance' => $row['balance'],
                'limit' => $row['limit'],
                'over' => $row['over'],
            ], $chart['days']['rows']),
            'stay_balance' => $chart['days']['stay_balance'],
        ];
    }

    // ------------------------------------------------------- medications

    private static function medications(Patient $patient): array
    {
        $now = now();
        $orders = PatientMedication::forPatient($patient->id);

        $active = $orders
            ->filter(fn (PatientMedication $order) => $order->isActive())
            ->map(function (PatientMedication $order) use ($now) {
                $state = $order->dueState($now);

                return [
                    'id' => $order->id,
                    'name' => $order->medication_name,
                    'summary' => $order->summary(),
                    'dose' => $order->doseLabel(),
                    'route' => $order->routeLabel(),
                    'frequency' => $order->frequencyLabel(),
                    'state' => $state,
                    'due_label' => $order->dueLabel($now),
                    'high_alert' => (bool) $order->is_high_alert,
                    'instructions' => $order->instructions,
                    'io_volume_ml' => FluidBalanceLinks::doseVolume($order),
                    'last_given_label' => $order->last_given_at?->format('d M H:i'),
                    'doses' => self::doses($order),
                    'rank' => PatientMedication::urgencyRank($state),
                    'due_ts' => $order->next_due_at?->getTimestamp() ?? PHP_INT_MAX,
                ];
            })
            ->sort(fn (array $a, array $b) => [$a['rank'], $a['due_ts']] <=> [$b['rank'], $b['due_ts']])
            ->map(fn (array $item) => array_diff_key($item, ['rank' => true, 'due_ts' => true]))
            ->values();

        $inactive = $orders
            ->reject(fn (PatientMedication $order) => $order->isActive())
            ->take(self::HISTORY_LIMIT)
            ->map(fn (PatientMedication $order) => [
                'id' => $order->id,
                'name' => $order->medication_name,
                'summary' => $order->summary(),
                'status' => $order->status,
                'status_label' => $order->status === PatientMedication::STATUS_STOPPED ? 'Stopped' : 'Completed',
                'closed_label' => ($order->stopped_at ?? $order->updated_at)?->format('d M H:i'),
                'stop_reason' => $order->stop_reason,
                'doses' => self::doses($order),
            ])
            ->values();

        return [
            'active' => $active->all(),
            'inactive' => $inactive->all(),
            'counts' => [
                'active' => $active->count(),
                'overdue' => $active->where('state', 'overdue')->count(),
                'due_soon' => $active->where('state', 'due_soon')->count(),
            ],
        ];
    }

    private static function doses(PatientMedication $order): array
    {
        return $order->administrations
            ->take(self::DOSES_PER_ORDER)
            ->map(fn (MedicationAdministration $dose) => [
                'status' => $dose->status,
                'status_label' => $dose->statusLabel(),
                'time_label' => $dose->administered_at?->format('d M H:i'),
                'by' => $dose->recordedBy?->name,
                'notes' => $dose->notes,
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------- consultant orders

    private static function orders(Patient $patient, Consultant $consultant): array
    {
        $tab = ConsultantOrder::tabFor($patient);
        $payload = fn (ConsultantOrder $order) => [
            'id' => $order->id,
            'instruction' => $order->instruction,
            'urgency' => $order->urgency,
            'urgency_label' => $order->urgencyLabel(),
            'ordered_label' => $order->ordered_at?->format('d M H:i'),
            'consultant' => $order->consultant_name,
            'is_mine' => (int) $order->consultant_id === (int) $consultant->id,
            'assigned_nurse' => $order->assignedNurse?->name,
            'fluid_restriction' => FluidBalanceLinks::restrictionSummary($order),
            'status' => $order->status,
            'status_label' => self::ORDER_STATUS_LABELS[$order->status] ?? ucfirst((string) $order->status),
            'closed_label' => $order->closed_at?->format('d M H:i'),
            'closed_by' => $order->closedBy?->name,
            'outcome_note' => $order->outcome_note,
            'can_cancel' => $order->isOpen() && (int) $order->consultant_id === (int) $consultant->id,
        ];

        return [
            'open' => $tab['open']->map($payload)->values()->all(),
            'closed' => $tab['closed']->take(self::HISTORY_LIMIT)->map($payload)->values()->all(),
        ];
    }
}
