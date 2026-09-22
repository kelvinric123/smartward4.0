<?php

namespace App\Services\DoctorApp;

use App\Models\Consultant;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMedication;
use App\Services\FluidBalanceLinks;
use App\Support\FluidBalanceChart;

/**
 * The doctor app's patient chart as one JSON-ready array: the I/O chart for
 * one chart day, the medication orders with their recent doses, and the
 * consultant orders. It reads the same helpers the ward dashboard uses
 * (FluidBalanceChart::forPatient, PatientMedication::forPatient,
 * ConsultantOrder::tabFor), so the consultant sees what the ward sees.
 * Times go out pre-formatted in the hospital's timezone.
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
            'generated_label' => now()->format('H:i'),
        ];

        $chart['badges'] = [
            'io_level' => $chart['io']['day']['is_current'] ? ($chart['io']['alerts'][0]['level'] ?? null) : null,
            'meds_overdue' => $chart['medications']['counts']['overdue'],
            'orders_open' => count($chart['orders']['open']),
        ];

        return $chart;
    }

    private static function patient(Patient $patient): array
    {
        $attending = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();

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
            'allergies' => array_values((array) ($patient->allergies ?? [])),
        ];
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
