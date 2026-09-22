<?php

namespace App\Services\NurseApp;

use App\Http\Controllers\WardDashboardController;
use App\Models\BloodTransfusion;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\Infusion;
use App\Models\MedicationAdministration;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\PatientCareProvider;
use App\Models\PatientMedication;
use App\Models\SugarReading;
use App\Models\VitalSign;
use App\Models\WardNotification;
use App\Services\DischargeSummaryRecords;
use App\Services\EngineInfusionService;
use App\Services\InfusionEngineClient;
use App\Services\NursingPlan\NursingCarePlan;
use App\Services\NursingPlan\ShiftTasks;
use App\Support\FluidBalanceChart;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Everything the nurse app's patient screen shows, as one JSON-ready array:
 * the patient, latest vitals, consultant orders, the I/O chart for one chart
 * day, medication orders, infusions and blood transfusions, pending alerts,
 * and a badge count per tab.
 *
 * It reads the same models and helpers the ward dashboard's Patient Details
 * uses (ConsultantOrder::tabFor, FluidBalanceChart::forPatient,
 * PatientMedication::forPatient, the infusion engine) so the app and the
 * dashboard always agree. Times are sent pre-formatted in the hospital's
 * timezone (plus ISO strings), so the app needs no timezone handling.
 */
class NurseAppPatientBundle
{
    private const INFUSION_STATUS_LABELS = [
        Infusion::STATUS_ALARMING => 'Alarm',
        Infusion::STATUS_RUNNING => 'Running',
        Infusion::STATUS_PAUSED => 'Paused',
        Infusion::STATUS_STOPPED => 'Stopped',
        Infusion::STATUS_PENDING => 'Pending',
        Infusion::STATUS_COMPLETED => 'Completed',
    ];

    private const INFUSION_STATUS_ORDER = ['alarming' => 0, 'running' => 1, 'paused' => 2, 'stopped' => 3, 'pending' => 4];

    private const TRANSFUSION_STATUS_LABELS = [
        BloodTransfusion::STATUS_PENDING => 'Registered',
        BloodTransfusion::STATUS_IN_PROGRESS => 'Running',
        BloodTransfusion::STATUS_COMPLETED => 'Completed',
        BloodTransfusion::STATUS_STOPPED => 'Stopped',
    ];

    private const ALERT_TYPE_LABELS = [
        WardNotification::TYPE_PATIENT_REQUEST => 'Patient call',
        WardNotification::TYPE_EWS => 'EWS alert',
        WardNotification::TYPE_INFUSION => 'Infusion alert',
    ];

    /** How many closed orders / finished items travel with the bundle. */
    private const HISTORY_LIMIT = 15;

    public static function build(Patient $patient, Nurse $nurse, ?string $ioDay = null): array
    {
        $patient->loadMissing(['ward', 'consultant', 'nurse', 'anaesthetist']);
        $infusions = self::infusions($patient);

        $bundle = [
            'patient' => self::patient($patient),
            'vitals' => self::vitals($patient),
            'orders' => self::orders($patient, $nurse),
            'io' => self::io($patient, $ioDay),
            'medications' => self::medications($patient),
            'infusions' => $infusions,
            'transfusions' => self::transfusions($patient),
            'alerts' => self::alerts($patient),
            // Nursing plan: the care plan, and what is due this shift (given
            // the infusions already read, so the engine is asked only once)
            'nursing_plan' => [
                'care_plan' => NursingCarePlan::forPatient($patient),
                'shift' => ShiftTasks::forPatient($patient, $infusions['active']),
            ],
            'generated_at' => now()->toIso8601String(),
            'generated_label' => now()->format('H:i'),
        ];

        $bundle['badges'] = [
            'orders_open' => count($bundle['orders']['open']),
            'orders_stat' => collect($bundle['orders']['open'])->where('urgency', 'stat')->count(),
            'orders_mine' => collect($bundle['orders']['open'])->where('is_mine', true)->count(),
            'meds_overdue' => $bundle['medications']['counts']['overdue'],
            'meds_due_soon' => $bundle['medications']['counts']['due_soon'],
            'io_level' => $bundle['io']['day']['is_current'] ? ($bundle['io']['status']['alerts'][0]['level'] ?? null) : null,
            'infusion_alarms' => collect($bundle['infusions']['active'])
                ->filter(fn(array $infusion) => $infusion['status'] === Infusion::STATUS_ALARMING || $infusion['is_warning'])
                ->count(),
            'alerts_pending' => count($bundle['alerts']['pending']),
            'transfusions_running' => count($bundle['transfusions']['running']),
            'transfusions_pending' => count($bundle['transfusions']['pending']),
            'transfusion_critical' => collect($bundle['transfusions']['exceptions'])->where('level', 'critical')->count(),
            'care_plan_due' => $bundle['nursing_plan']['care_plan']['due_evaluations'],
            'shift_overdue' => $bundle['nursing_plan']['shift']['counts']['overdue'],
        ];

        return $bundle;
    }

    // ------------------------------------------------------------ patient

    private static function patient(Patient $patient): array
    {
        $attending = $patient->activeCareProviders()
            ->where('role', PatientCareProvider::ROLE_ATTENDING)
            ->first();
        $clinical = app(DischargeSummaryRecords::class)->clinicalAlerts($patient);

        return [
            'id' => $patient->id,
            'name' => $patient->name,
            'alias_name' => $patient->alias_name,
            'mrn' => $patient->mrn,
            'gender' => $patient->gender,
            'age' => $patient->age,
            'bed' => $patient->bed_number,
            'ward_id' => $patient->ward_id,
            'ward' => $patient->ward?->ward_name,
            'status' => $patient->status,
            'status_label' => $patient->statusLabel(),
            'admitted_label' => $patient->admitted_at?->format('d M Y, H:i'),
            'stay_label' => $patient->admitted_at ? $patient->lengthOfStayLabel() : null,
            'expected_discharge_label' => $patient->expected_discharge_at?->format('d M Y, H:i'),
            'consultant' => $attending?->display_name ?? $patient->consultant?->name,
            'anaesthetist' => $patient->anaesthetist?->name,
            'primary_nurse' => $patient->nurse?->name,
            'allergies' => $clinical['allergies']->values()->all(),
            'nbm' => $clinical['nbm'],
            'diet' => $clinical['diet'],
            'diet_orders' => $clinical['dietOrders'],
            'feeding' => $clinical['feeding'],
            'fall_risk' => $clinical['fallRisk'],
            'isolation' => $clinical['isolation'],
            'nursing_level' => $clinical['nursingLevel'],
            'hgt' => $clinical['hgt'],
        ];
    }

    private static function vitals(Patient $patient): array
    {
        $recent = VitalSign::where('patient_id', $patient->id)
            ->with(['operator:id,name', 'recordedBy:id,name'])
            ->orderByDesc('recorded_at')
            ->limit(6)
            ->get();

        $dashboard = app(WardDashboardController::class);
        $rows = $recent->map(function (VitalSign $vital) use ($dashboard) {
            $score = $dashboard->calculateEWS($vital)['score'];

            return [
                'id' => $vital->id,
                'time_label' => $vital->recorded_at?->format('d M H:i'),
                'bp' => $vital->blood_pressure,
                'pulse' => $vital->pulse_rate_display,
                'temperature' => $vital->temperature !== null ? number_format((float) $vital->temperature, 1) : null,
                'spo2' => $vital->spo2_display,
                'respiratory_rate' => $vital->respiratory_rate,
                'oxygen' => $vital->oxygen_delivery ? $vital->oxygenShortLabel() : null,
                'ews' => $score,
                'by' => $vital->operator?->name ?? $vital->recordedBy?->name,
            ];
        })->values();

        $hgt = SugarReading::where('patient_id', $patient->id)->orderByDesc('recorded_at')->first();

        return [
            'latest' => $rows->first(),
            'recent' => $rows->all(),
            'hgt' => $hgt ? [
                'value' => number_format((float) $hgt->value, 1),
                'time_label' => $hgt->recorded_at?->format('d M H:i'),
                'tone' => $hgt->isLow() ? 'critical' : ($hgt->isHigh() ? 'warning' : 'default'),
            ] : null,
        ];
    }

    // ------------------------------------------------------------- orders

    private static function orders(Patient $patient, Nurse $nurse): array
    {
        $tab = ConsultantOrder::tabFor($patient);
        $slot = fn(?array $slot) => $slot ? [
            'label' => $slot['label'],
            'name' => $slot['name'],
            'time' => $slot['time'],
            'nurse' => $slot['nurse']?->name,
        ] : null;

        return [
            'open' => $tab['open']->map(fn(ConsultantOrder $order) => [
                'id' => $order->id,
                'instruction' => $order->instruction,
                'urgency' => $order->urgency,
                'urgency_label' => $order->urgencyLabel(),
                'ordered_label' => $order->ordered_at?->format('d M H:i'),
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'consultant' => $order->consultant_name,
                'assigned_nurse' => $order->assignedNurse?->name,
                'is_mine' => (int) $order->assigned_nurse_id === (int) $nurse->id,
                'slot_label' => $order->slotLabel(),
                'entered_by' => $order->createdBy?->name,
                'handovers' => $order->handovers->count(),
                'last_handover_label' => ($last = $order->handovers->first())
                    ? $last->created_at->format('d M H:i') . ($last->handedOverBy ? ' by ' . $last->handedOverBy->name : '')
                    : null,
            ])->values()->all(),
            'closed' => $tab['closed']->take(self::HISTORY_LIMIT)->map(fn(ConsultantOrder $order) => [
                'id' => $order->id,
                'instruction' => $order->instruction,
                'urgency' => $order->urgency,
                'urgency_label' => $order->urgencyLabel(),
                'status' => $order->status,
                'status_label' => $order->status === ConsultantOrder::STATUS_DONE ? 'Done' : 'Cancelled',
                'closed_label' => $order->closed_at?->format('d M H:i'),
                'closed_by' => $order->closedBy?->name,
                'outcome_note' => $order->outcome_note,
                'consultant' => $order->consultant_name,
            ])->values()->all(),
            'slots' => [
                'current' => $slot($tab['slots']['current'] ?? null),
                'next' => $slot($tab['slots']['next'] ?? null),
            ],
            'consultants' => $tab['consultants']
                ->map(fn($consultant) => [
                    'id' => $consultant->id,
                    'name' => $consultant->name,
                    'is_patients' => in_array($consultant->id, $tab['patientConsultantIds'], true),
                ])
                ->sortBy(fn(array $c) => [$c['is_patients'] ? 0 : 1, $c['name']])
                ->values()
                ->all(),
            'default_consultant_id' => $tab['defaultConsultantId'],
            'urgencies' => ConsultantOrder::URGENCIES,
        ];
    }

    // ----------------------------------------------------------- I/O chart

    private static function io(Patient $patient, ?string $day): array
    {
        $chart = FluidBalanceChart::forPatient($patient, $day);
        $totals = $chart['totals'];
        $plan = $chart['current_plan'];
        $ml = fn(int $value) => FluidBalanceEntry::formatMl($value);

        $byType = [];
        foreach (['intake', 'output'] as $direction) {
            foreach ($totals['by_type'][$direction] ?? [] as $category => $volume) {
                $byType[] = [
                    'direction' => $direction,
                    'category' => $category,
                    'label' => FluidBalanceEntry::typesFor($direction)[$category] ?? ucfirst($category),
                    'ml' => (int) $volume,
                    'ml_label' => $ml((int) $volume),
                ];
            }
        }

        return [
            'day' => [
                'key' => $chart['day']['key'],
                'label' => $chart['day']['start']->format('D d M'),
                'range' => $chart['day']['start']->format('d M H:i') . ' - ' . $chart['day']['end']->format('d M H:i'),
                'is_current' => $chart['day']['is_current'],
                'previous' => $chart['day']['previous'],
                'next' => $chart['day']['next'],
            ],
            'totals' => [
                'intake' => $totals['intake'],
                'output' => $totals['output'],
                'balance' => $totals['balance'],
                'urine' => $totals['urine'],
                'intake_label' => $ml($totals['intake']),
                'output_label' => $ml($totals['output']),
                'balance_label' => FluidBalanceEntry::formatBalance($totals['balance']),
                'urine_label' => $ml($totals['urine']),
                'by_type' => $byType,
            ],
            'status' => [
                'alerts' => $chart['status']['alerts'],
                'limit' => $chart['status']['limit'],
                'urine' => $chart['status']['urine'],
            ],
            'plan' => $plan ? [
                'summary' => $plan->summary(),
                'intake_limit_ml' => $plan->intake_limit_ml,
                'urine_min_ml_per_hour' => $plan->urine_min_ml_per_hour,
                'notes' => $plan->notes,
                'set_label' => $plan->created_at->format('d M H:i') . ($plan->setBy ? ' by ' . $plan->setBy->name : ''),
            ] : null,
            'shifts' => collect($chart['shifts'])->map(fn(array $shift) => [
                'code' => $shift['code'],
                'name' => $shift['name'],
                'time' => $shift['time'],
                'current' => $shift['current'],
                'intake_label' => $ml($shift['intake']),
                'output_label' => $ml($shift['output']),
                'balance_label' => FluidBalanceEntry::formatBalance($shift['balance']),
                'entries' => collect($shift['rows'])->map(fn(array $row) => self::ioEntry($row['entry'], $row['running']))->values()->all(),
            ])->values()->all(),
            'latest_assessment' => $chart['latest_assessment'] ? self::assessment($chart['latest_assessment']) : null,
            'weight' => $chart['weight'] ? [
                'kg' => $chart['weight']['kg'],
                'time_label' => $chart['weight']['at']->format('d M H:i'),
                'change' => $chart['weight']['change'],
                'gain' => $chart['weight']['gain'],
            ] : null,
            'days' => collect($chart['days']['rows'] ?? [])->map(fn(array $row) => [
                'key' => $row['key'],
                'label' => $row['start']->format('D d M'),
                'is_current' => $row['is_current'],
                'entries' => $row['entries'],
                'intake_label' => $ml($row['intake']),
                'output_label' => $ml($row['output']),
                'balance' => $row['balance'],
                'balance_label' => FluidBalanceEntry::formatBalance($row['balance']),
                'over' => $row['over'],
            ])->values()->all(),
            'stay_balance_label' => isset($chart['days']['stay_balance']) && $chart['days']['stay_balance'] !== null
                ? FluidBalanceEntry::formatBalance($chart['days']['stay_balance'])
                : null,
            'options' => [
                'intake_types' => FluidBalanceEntry::INTAKE_TYPES,
                'output_types' => FluidBalanceEntry::OUTPUT_TYPES,
                'suggestions' => FluidBalanceEntry::SUGGESTIONS,
                'quick_volumes' => FluidBalanceEntry::QUICK_VOLUMES,
                'volume_max' => FluidBalanceEntry::VOLUME_MAX,
                'limit_presets' => FluidBalancePlan::LIMIT_PRESETS,
                'urine_presets' => FluidBalancePlan::URINE_PRESETS,
                'limit_min' => FluidBalancePlan::LIMIT_MIN,
                'limit_max' => FluidBalancePlan::LIMIT_MAX,
                'urine_min' => FluidBalancePlan::URINE_MIN,
                'urine_max' => FluidBalancePlan::URINE_MAX,
                'edema_grades' => collect(FluidOverloadAssessment::EDEMA_GRADES)
                    ->map(fn(array $grade, int $value) => ['value' => $value, 'short' => $grade['short'], 'label' => $grade['label']])
                    ->values()->all(),
                'edema_sites' => FluidOverloadAssessment::EDEMA_SITES,
                'signs' => FluidOverloadAssessment::SIGNS,
            ],
        ];
    }

    private static function ioEntry(FluidBalanceEntry $entry, ?int $running): array
    {
        return [
            'id' => $entry->id,
            'time_label' => $entry->recorded_at->format('H:i'),
            'direction' => $entry->direction,
            'category' => $entry->category,
            'type_label' => $entry->typeLabel(),
            'description' => $entry->description,
            'label' => $entry->label(),
            'volume' => (int) $entry->volume_ml,
            'volume_label' => FluidBalanceEntry::formatMl((int) $entry->volume_ml),
            'running_label' => $running !== null ? FluidBalanceEntry::formatBalance($running) : null,
            'by' => $entry->recordedBy?->name,
            // Added automatically from a blood unit, a dose or a pump (FluidBalanceLinks)
            'auto_label' => method_exists($entry, 'isAuto') && $entry->isAuto() ? $entry->sourceLabel() : null,
            'voided' => $entry->isVoided(),
            'void_reason' => $entry->void_reason,
            'voided_by' => $entry->voidedBy?->name,
        ];
    }

    private static function assessment(FluidOverloadAssessment $assessment): array
    {
        return [
            'time_label' => $assessment->assessed_at->format('d M H:i'),
            'edema' => $assessment->edemaSummary(),
            'signs' => $assessment->signLabels(),
            'weight_kg' => $assessment->weight_kg !== null ? (float) $assessment->weight_kg : null,
            'urgent' => $assessment->hasUrgentSign(),
            'concern' => $assessment->hasOverloadSigns() || $assessment->hasEdema(),
            'notes' => $assessment->notes,
            'by' => $assessment->recordedBy?->name,
        ];
    }

    // --------------------------------------------------------- medications

    private static function medications(Patient $patient): array
    {
        $now = now();
        $orders = PatientMedication::forPatient($patient->id);
        $active = $orders->where('status', PatientMedication::STATUS_ACTIVE);
        $rank = ['overdue' => 0, 'due_soon' => 1, 'scheduled' => 2, 'prn' => 3];

        $map = fn(PatientMedication $order) => [
            'id' => $order->id,
            'name' => $order->medication_name,
            'summary' => $order->summary(),
            'dose_label' => $order->doseLabel(),
            'route_label' => $order->routeLabel(),
            'frequency_label' => $order->frequencyLabel(),
            'is_high_alert' => (bool) $order->is_high_alert,
            'instructions' => $order->instructions,
            'status' => $order->status,
            'due_state' => $order->dueState($now),
            'due_label' => $order->dueLabel($now),
            'next_due_label' => $order->next_due_at ? self::when($order->next_due_at) : null,
            'next_allowed_label' => $order->isPrn() && $order->nextAllowedAt() && $order->nextAllowedAt()->isFuture()
                ? self::when($order->nextAllowedAt())
                : null,
            'last_given_label' => $order->last_given_at ? self::when($order->last_given_at) : null,
            'stopped_label' => $order->stopped_at ? self::when($order->stopped_at) : null,
            'stop_reason' => $order->stop_reason,
            'recent_doses' => $order->administrations->take(4)->map(fn(MedicationAdministration $dose) => [
                'status' => $dose->status,
                'status_label' => $dose->statusLabel(),
                'time_label' => self::when($dose->administered_at),
                'by' => $dose->recordedBy?->name,
                'notes' => $dose->notes,
            ])->values()->all(),
        ];

        return [
            'active' => $active
                ->sortBy(fn(PatientMedication $order) => [$rank[$order->dueState($now)] ?? 9, $order->next_due_at?->getTimestamp() ?? PHP_INT_MAX])
                ->map($map)->values()->all(),
            'closed' => $orders->where('status', '!=', PatientMedication::STATUS_ACTIVE)
                ->take(self::HISTORY_LIMIT)->map($map)->values()->all(),
            'counts' => [
                'active' => $active->count(),
                'overdue' => $active->filter(fn(PatientMedication $order) => $order->dueState($now) === 'overdue')->count(),
                'due_soon' => $active->filter(fn(PatientMedication $order) => $order->dueState($now) === 'due_soon')->count(),
            ],
            'statuses' => MedicationAdministration::STATUSES,
        ];
    }

    // ----------------------------------------------------------- infusions

    /**
     * Current infusions as the ward dashboard's infusion panel shows them:
     * live from the Qmed Infusion Engine when it is in use (the local table
     * holds only snapshots there), otherwise from the local table.
     */
    private static function infusions(Patient $patient): array
    {
        $source = 'local';
        $error = null;
        $rows = null;

        if (InfusionEngineClient::engineModeActive()) {
            try {
                $rows = EngineInfusionService::make()->infusionsForPatient((int) $patient->id);
                $source = 'engine';
            } catch (\Throwable $e) {
                Log::warning('Nurse app: infusion engine unreachable, using local data: ' . $e->getMessage());
                $error = 'The infusion engine could not be reached, so this is the last saved data.';
            }
        }

        if ($rows === null) {
            $rows = Infusion::with('infusionPump')
                ->where('patient_id', $patient->id)
                ->latest('last_updated_at')
                ->get();
        } else {
            // The engine only knows each pump's latest state; finished ones live locally
            $rows = $rows->concat(Infusion::with('infusionPump')
                ->where('patient_id', $patient->id)
                ->where('status', Infusion::STATUS_COMPLETED)
                ->latest('completed_at')
                ->get());
        }

        $active = $rows->reject(fn(Infusion $infusion) => $infusion->status === Infusion::STATUS_COMPLETED)
            ->sortBy(fn(Infusion $infusion) => self::INFUSION_STATUS_ORDER[$infusion->status] ?? 9)
            ->values();

        $admittedAt = $patient->admitted_at;
        $completed = $rows->filter(fn(Infusion $infusion) => $infusion->status === Infusion::STATUS_COMPLETED)
            ->filter(function (Infusion $infusion) use ($admittedAt) {
                $when = $infusion->completed_at ?? $infusion->last_updated_at;

                return !$admittedAt || $when === null || $when->gte($admittedAt);
            })
            ->sortByDesc(fn(Infusion $infusion) => ($infusion->completed_at ?? $infusion->last_updated_at)?->getTimestamp() ?? 0)
            ->take(self::HISTORY_LIMIT)
            ->values();

        return [
            'source' => $source,
            'error' => $error,
            'active' => $active->map(fn(Infusion $infusion) => self::infusion($infusion))->all(),
            'completed' => $completed->map(fn(Infusion $infusion) => self::infusion($infusion))->all(),
        ];
    }

    private static function infusion(Infusion $infusion): array
    {
        $pump = $infusion->infusionPump;
        $number = fn($value, int $decimals = 1) => $value !== null ? round((float) $value, $decimals) : null;

        return [
            'key' => ($infusion->exists ? 'i' . $infusion->id : 'p' . ($infusion->infusion_pump_id ?? spl_object_id($infusion))),
            'medication' => $infusion->medication_name ?: 'Unknown medication',
            'concentration' => $infusion->formatted_concentration,
            'pump' => $pump?->device_name ?: ($pump?->serial_no ?: $pump?->device_id),
            'status' => $infusion->status,
            'status_label' => self::INFUSION_STATUS_LABELS[$infusion->status] ?? ucfirst((string) $infusion->status),
            'is_warning' => (bool) $infusion->is_warning,
            'alarm_message' => $infusion->alarm_message,
            'alarm_priority' => $infusion->alarm_priority ? $infusion->alarm_priority_display : null,
            'flow_rate' => $number($infusion->flow_rate),
            'dose_rate' => $infusion->formatted_dose_rate,
            'infused_volume' => $number($infusion->infused_volume),
            'total_volume' => $number($infusion->total_volume),
            'remaining_volume' => $number($infusion->remaining_volume),
            'progress_percent' => $infusion->progress_percent,
            'remaining_time' => $infusion->status === Infusion::STATUS_COMPLETED ? null : $infusion->formatted_remaining_time,
            'ends_label' => $infusion->estimated_completion?->format('H:i'),
            'started_label' => $infusion->started_at ? self::when($infusion->started_at) : null,
            'updated_label' => $infusion->last_updated_at ? self::when($infusion->last_updated_at) : null,
            'completed_label' => $infusion->completed_at ? self::when($infusion->completed_at) : null,
            'live' => !$infusion->exists,
        ];
    }

    // ---------------------------------------------------- blood transfusion

    /**
     * The Blood Transfusion tab as the ward dashboard shows it: units running
     * (time-based monitoring), units in their pre-start bedside checks (an
     * ordered stepper), finished units, and every open unit's problems, worst
     * first. The rules themselves live on BloodTransfusion - the app only
     * shows what they allow.
     */
    private static function transfusions(Patient $patient): array
    {
        // Every unit still open, however old, plus this stay's finished ones
        $units = BloodTransfusion::with(['checkedBy:id,name', 'createdBy:id,name'])
            ->where('patient_id', $patient->id)
            ->where(fn($query) => $query
                ->whereIn('status', [BloodTransfusion::STATUS_PENDING, BloodTransfusion::STATUS_IN_PROGRESS])
                ->when($patient->admitted_at, fn($q) => $q->orWhere('created_at', '>=', $patient->admitted_at), fn($q) => $q->orWhereNotNull('id')))
            ->orderByDesc('id')
            ->get();

        $levelOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        $exceptions = $units->reject(fn(BloodTransfusion $unit) => $unit->isFinished())
            ->flatMap(fn(BloodTransfusion $unit) => collect($unit->exceptions())
                ->map(fn(array $exception) => $exception + ['unit' => $unit->unit_number, 'unit_id' => $unit->id]))
            ->sortBy(fn(array $exception) => $levelOrder[$exception['level']] ?? 9)
            ->values();

        return [
            'running' => $units->filter(fn(BloodTransfusion $unit) => $unit->isRunning())
                ->map(fn(BloodTransfusion $unit) => self::transfusion($unit))->values()->all(),
            'pending' => $units->filter(fn(BloodTransfusion $unit) => $unit->isPending())
                ->map(fn(BloodTransfusion $unit) => self::transfusion($unit))->values()->all(),
            // Latest finished first, whenever they were registered
            'finished' => $units->filter(fn(BloodTransfusion $unit) => $unit->isFinished())
                ->sortByDesc(fn(BloodTransfusion $unit) => ($unit->completed_at ?? $unit->updated_at)?->getTimestamp() ?? 0)
                ->take(self::HISTORY_LIMIT)
                ->map(fn(BloodTransfusion $unit) => self::transfusion($unit))->values()->all(),
            'exceptions' => $exceptions->all(),
            'options' => [
                'product_types' => BloodTransfusion::PRODUCT_TYPES,
                'presets' => BloodTransfusion::PRODUCT_PRESETS,
                'blood_groups' => BloodTransfusion::BLOOD_GROUPS,
                'volume_step' => BloodTransfusion::VOLUME_STEP,
                'volume_min' => BloodTransfusion::VOLUME_MIN,
                'volume_max' => BloodTransfusion::VOLUME_MAX,
                'minutes_step' => BloodTransfusion::MINUTES_STEP,
                'minutes_min' => BloodTransfusion::MINUTES_MIN,
                'minutes_max' => BloodTransfusion::MAX_RUNNING_MINUTES,
                'patient_blood_group' => $units->pluck('patient_blood_group')->filter()->first(),
            ],
        ];
    }

    private static function transfusion(BloodTransfusion $unit): array
    {
        $end = $unit->predictedEndAt();
        $limit = $unit->expiresRunningAt();
        $done = $unit->completedStepCount();

        return [
            'id' => $unit->id,
            'unit_number' => $unit->unit_number,
            'product' => $unit->product_type,
            'unit_group' => $unit->unit_blood_group,
            'patient_group' => $unit->patient_blood_group,
            'groups' => $unit->groupSummary(),
            'compatibility' => $unit->compatibility(),
            'crossmatch_reference' => $unit->crossmatch_reference,
            'expires_label' => $unit->unit_expires_at?->format('d M Y H:i'),
            'expired' => $unit->unitExpired(),
            'volume_ml' => $unit->volume_ml,
            'prescribed_minutes' => $unit->prescribed_minutes,
            'rate' => $unit->rateMlPerHour(),
            'notes' => $unit->notes,
            'status' => $unit->status,
            'status_label' => self::TRANSFUSION_STATUS_LABELS[$unit->status] ?? ucfirst((string) $unit->status),
            'registered_label' => $unit->created_at ? self::when($unit->created_at) : null,
            'registered_by' => $unit->createdBy?->name,

            // Pre-start: the ordered checklist and whether the unit may start
            'steps' => collect($unit->checklistSteps())->map(fn(array $step) => [
                'key' => $step['key'],
                'number' => $step['number'],
                'label' => $step['label'],
                'detail' => $step['detail'],
                'done' => (bool) $step['done'],
                'problem' => $step['problem'],
                'is_next' => $step['isNext'],
                'can_undo' => $step['canUndo'],
            ])->values()->all(),
            'steps_done' => $done,
            'checks_complete' => $unit->checksComplete(),
            'can_start' => $unit->canStart(),
            'start_note' => $unit->isPending()
                ? ($unit->canStart()
                    ? 'All four steps confirmed. Ready to start.'
                    : (!$unit->checksComplete()
                        ? (4 - $done) . ' ' . (4 - $done === 1 ? 'step' : 'steps') . ' left before this unit can start.'
                        : 'Resolve the flagged problems before starting.'))
                : null,
            'last_action_label' => $unit->checked_at
                ? $unit->checked_at->format('H:i') . ($unit->checkedBy ? ' by ' . $unit->checkedBy->name : '')
                : null,

            // Running: times for live monitoring (ISO, so the app can tick them)
            'started_at' => $unit->started_at?->toIso8601String(),
            'started_label' => $unit->started_at ? self::when($unit->started_at) : null,
            'end_at' => $end?->toIso8601String(),
            'end_label' => $end?->format('d M H:i'),
            'limit_at' => $limit?->toIso8601String(),
            'limit_label' => $limit?->format('H:i'),

            // Finished
            'completed_label' => $unit->completed_at ? self::when($unit->completed_at) : null,
            'took_minutes' => $unit->isFinished() ? (int) $unit->elapsedMinutes() : null,
            'stop_reason' => $unit->stop_reason,
        ];
    }

    // -------------------------------------------------------------- alerts

    private static function alerts(Patient $patient): array
    {
        $map = function (WardNotification $alert) {
            $minutes = (int) max(0, floor((now()->getTimestamp() - $alert->created_at->getTimestamp()) / 60));

            return [
                'id' => $alert->id,
                'type' => $alert->type,
                'type_label' => self::ALERT_TYPE_LABELS[$alert->type] ?? 'Alert',
                'category' => $alert->category,
                'severity' => $alert->severity,
                'severity_label' => $alert->severity_label,
                'message' => $alert->message,
                'ews_score' => $alert->ews_score,
                'time_label' => self::when($alert->created_at),
                'minutes_ago' => $minutes,
                'responded_label' => $alert->responded_at ? self::when($alert->responded_at) : null,
                'responded_by' => $alert->responded_at ? ($alert->responder?->name ?? 'Cleared automatically') : null,
            ];
        };

        return [
            'pending' => WardNotification::where('patient_id', $patient->id)
                ->where('status', WardNotification::STATUS_PENDING)
                ->orderByDesc('created_at')
                ->get()
                ->map($map)
                ->values()
                ->all(),
            'recent' => WardNotification::with('responder:id,name')
                ->where('patient_id', $patient->id)
                ->where('status', WardNotification::STATUS_RESPONDED)
                ->orderByDesc('responded_at')
                ->limit(5)
                ->get()
                ->map($map)
                ->values()
                ->all(),
        ];
    }

    // ------------------------------------------------------------- helpers

    /** "14:05" today, "Yest 14:05", or "21 Sep 14:05". */
    public static function when(CarbonInterface $at): string
    {
        if ($at->isToday()) {
            return $at->format('H:i');
        }

        return ($at->isYesterday() ? 'Yest' : $at->format('d M')) . ' ' . $at->format('H:i');
    }
}
