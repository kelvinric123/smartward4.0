<?php

namespace App\Services\NursingPlan;

use App\Http\Controllers\WardDashboardController;
use App\Models\BloodTransfusion;
use App\Models\ConsultantOrder;
use App\Models\FluidBalanceEntry;
use App\Models\Infusion;
use App\Models\NursingCarePlanItem;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientMovement;
use App\Models\ShiftSetting;
use App\Models\SugarReading;
use App\Models\VitalSign;
use App\Services\ShiftHandover;
use App\Support\ClinicalIndicatorMonitoring;
use App\Support\FluidBalanceChart;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * What is due for one patient in the shift on now, gathered from what
 * SmartWard already records - nothing here is entered by hand:
 *
 *   - medication doses falling due before the shift ends (and overdue ones),
 *   - open consultant orders,
 *   - assessment scales due (ward type monitoring),
 *   - HGT by its ordered frequency,
 *   - the fluid plan and I/O flags,
 *   - blood units running or waiting for their checks,
 *   - infusions ending this shift, pump alarms,
 *   - trips out of the ward booked for this shift, a planned discharge,
 *   - care plan items not yet evaluated this shift,
 *   - the latest vital signs and their EWS.
 *
 * Tasks with a time are listed in time order; the rest apply to the whole
 * shift. Each carries the nurse app tab that deals with it.
 */
class ShiftTasks
{
    /** HGT frequency => minutes between readings (PRN has none). */
    private const HGT_INTERVALS = [
        SugarReading::FREQUENCY_BD => 720,
        SugarReading::FREQUENCY_TDS => 480,
        SugarReading::FREQUENCY_QID => 360,
    ];

    private const MAX_TASKS = 40;

    /**
     * @param  array|null  $infusions  current infusions as the nurse app bundle maps them
     *                                 (live from the engine); read from the local table if null
     */
    public static function forPatient(Patient $patient, ?array $infusions = null, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        [$start, $end, $shift] = self::window($patient, $now);
        $slot = ShiftHandover::slotsFor($patient, $now)['current'] ?? null;

        $tasks = collect()
            ->concat(self::medications($patient, $now, $end))
            ->concat(self::orders($patient, $now, $end))
            ->concat(self::assessments($patient, $now, $end))
            ->concat(self::hgt($patient, $now, $end))
            ->concat(self::fluid($patient, $now))
            ->concat(self::transfusions($patient, $now, $end))
            ->concat(self::infusions($patient, $infusions, $now, $end))
            ->concat(self::movements($patient, $start, $end))
            ->concat(self::carePlan($patient, $now))
            ->concat(self::vitals($patient, $now));

        if ($patient->expected_discharge_at && $patient->expected_discharge_at->betweenIncluded($start, $end)) {
            $tasks->push(self::task($patient->expected_discharge_at, $now, 'discharge', 'Planned discharge', 'Discharge summary, medicines to take home, follow-up', 'good', null));
        }

        $timed = $tasks->filter(fn(array $task) => $task['at'] !== null)
            ->sortBy(fn(array $task) => $task['at']->getTimestamp())
            ->values();
        $standing = $tasks->filter(fn(array $task) => $task['at'] === null)
            ->sortBy(fn(array $task) => ['critical' => 0, 'warning' => 1, 'default' => 2, 'good' => 3, 'muted' => 4][$task['tone']] ?? 5)
            ->values();

        $strip = fn(array $task) => collect($task)->except('at')->all();

        return [
            'shift' => [
                'code' => $shift?->shift_code,
                'name' => $shift?->shift_name ?? 'Next 8 hours',
                'label' => $slot['label'] ?? null,
                'time' => $start->format('H:i') . ' - ' . $end->format('H:i'),
                'nurse' => $slot['nurse']?->name ?? null,
            ],
            'timed' => $timed->take(self::MAX_TASKS)->map($strip)->all(),
            'standing' => $standing->take(self::MAX_TASKS)->map($strip)->all(),
            'counts' => [
                'total' => $timed->count() + $standing->count(),
                'overdue' => $tasks->where('overdue', true)->count(),
            ],
        ];
    }

    /**
     * The shift on now for the patient's ward, as [start, end, ShiftSetting].
     * Between shifts (a gap in the ward's times) the next eight hours stand in.
     */
    public static function window(Patient $patient, Carbon $now): array
    {
        $shift = ShiftHandover::shifts($patient->ward_id)
            ->first(fn(ShiftSetting $s) => $s->isTimeInShift($now->format('H:i:s')));

        if (!$shift) {
            return [$now->copy(), $now->copy()->addHours(8), null];
        }

        $start = $now->copy()->setTimeFromTimeString((string) $shift->start_time);
        if ($start->greaterThan($now)) {
            $start->subDay(); // a night shift that began yesterday
        }
        $end = $start->copy()->setTimeFromTimeString((string) $shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end, $shift];
    }

    // --------------------------------------------------------------- sources

    private static function medications(Patient $patient, Carbon $now, Carbon $end): array
    {
        $tasks = [];

        $orders = PatientMedication::where('patient_id', $patient->id)
            ->where('status', PatientMedication::STATUS_ACTIVE)
            ->get();

        foreach ($orders as $order) {
            /** @var PatientMedication $order */
            if ($order->isPrn() || !$order->next_due_at) {
                continue;
            }

            $what = $order->medication_name . ' ' . $order->summary();
            $flag = $order->is_high_alert ? 'High alert' : null;
            $due = $order->next_due_at->copy();

            if ($due->lessThan($end)) {
                $tasks[] = self::task($due, $now, 'medication', $what, $order->instructions, $due->lessThan($now) ? 'critical' : 'default', 'meds', $flag);
            }

            // Further doses of a regular order still inside this shift
            if (!$order->isStat() && $order->interval_minutes) {
                $next = $due->copy()->addMinutes($order->interval_minutes);
                while ($next->lessThan($end) && count($tasks) < self::MAX_TASKS) {
                    if ($next->greaterThan($now)) {
                        $tasks[] = self::task($next->copy(), $now, 'medication', $what, null, 'default', 'meds', $flag);
                    }
                    $next->addMinutes($order->interval_minutes);
                }
            }
        }

        return $tasks;
    }

    private static function orders(Patient $patient, Carbon $now, Carbon $end): array
    {
        return ConsultantOrder::where('patient_id', $patient->id)
            ->open()
            ->with('assignedNurse:id,name')
            ->orderBy('ordered_at')
            ->get()
            ->map(function (ConsultantOrder $order) use ($now, $end) {
                // An order timed later this shift is a timed task; the rest stand for the shift
                $at = $order->ordered_at && $order->ordered_at->greaterThan($now) && $order->ordered_at->lessThan($end)
                    ? $order->ordered_at
                    : null;

                return self::task(
                    $at,
                    $now,
                    'order',
                    Str::limit($order->instruction, 110),
                    trim(($order->consultant_name ?? 'Consultant') . ($order->assignedNurse ? ' · with ' . $order->assignedNurse->name : '')),
                    match ($order->urgency) {
                        'stat' => 'critical',
                        'urgent' => 'warning',
                        default => 'default',
                    },
                    'orders',
                    $order->urgencyLabel()
                );
            })
            ->all();
    }

    private static function assessments(Patient $patient, Carbon $now, Carbon $end): array
    {
        $items = ClinicalIndicatorMonitoring::forPatients([$patient], $now)[$patient->id]['items'] ?? [];

        return collect($items)
            ->filter(fn(array $item) => $item['due_at']->lessThan($end))
            ->map(fn(array $item) => self::task(
                $item['due_at'],
                $now,
                'assessment',
                $item['name'] . ' reassessment',
                ucfirst($item['history']) . ' · every ' . $item['interval'],
                $item['state'] === ClinicalIndicatorMonitoring::STATE_OVERDUE ? 'critical' : ($item['state'] === ClinicalIndicatorMonitoring::STATE_DUE ? 'warning' : 'default'),
                null,
                $item['code']
            ))
            ->values()
            ->all();
    }

    private static function hgt(Patient $patient, Carbon $now, Carbon $end): array
    {
        if (!$patient->hgt_enabled) {
            return [];
        }

        $frequency = SugarReading::getFrequencyLabel((string) $patient->hgt_frequency);
        $last = SugarReading::where('patient_id', $patient->id)->orderByDesc('recorded_at')->first();
        $interval = self::HGT_INTERVALS[$patient->hgt_frequency] ?? null;
        $detail = $frequency . ($last ? ' · last ' . number_format((float) $last->value, 1) . ' mmol/L at ' . NursingCarePlan::when($last->recorded_at) : ' · none yet');

        if (!$interval) {
            return [self::task(null, $now, 'hgt', 'Blood glucose (HGT) as needed', $detail, 'default', null, 'HGT')];
        }

        $due = $last ? $last->recorded_at->copy()->addMinutes($interval) : $now->copy();

        return $due->lessThan($end)
            ? [self::task($due, $now, 'hgt', 'Blood glucose (HGT)', $detail, $due->lessThan($now) ? 'warning' : 'default', null, 'HGT')]
            : [];
    }

    private static function fluid(Patient $patient, Carbon $now): array
    {
        $status = FluidBalanceChart::alertsForPatients([$patient], $now)[$patient->id] ?? null;

        if (!$status) {
            return [];
        }

        $tasks = [];

        if ($status['limit']) {
            $limit = $status['limit'];
            $tasks[] = self::task(null, $now, 'fluid',
                'Fluid limit ' . FluidBalanceEntry::formatMl((int) $limit['limit']) . ' today',
                FluidBalanceEntry::formatMl((int) $limit['taken']) . ' taken in'
                    . ($limit['state'] === 'over'
                        ? ', ' . FluidBalanceEntry::formatMl((int) $limit['over_by']) . ' over'
                        : ', ' . FluidBalanceEntry::formatMl((int) $limit['remaining']) . ' left'),
                $limit['state'] === 'over' ? 'critical' : ($limit['state'] === 'near' ? 'warning' : 'default'),
                'io');
        }

        foreach ($status['alerts'] as $alert) {
            if (str_contains($alert['title'], 'intake limit')) {
                continue; // already the fluid limit line above
            }
            $tasks[] = self::task(null, $now, 'fluid', $alert['title'], $alert['detail'], $alert['level'] === 'critical' ? 'critical' : 'warning', 'io');
        }

        if (!$tasks) {
            $tasks[] = self::task(null, $now, 'fluid', 'Intake / output chart',
                'In ' . FluidBalanceEntry::formatMl((int) $status['intake']) . ' · out ' . FluidBalanceEntry::formatMl((int) $status['output'])
                    . ' · ' . FluidBalanceEntry::formatBalance((int) $status['balance']),
                'default', 'io');
        }

        return $tasks;
    }

    private static function transfusions(Patient $patient, Carbon $now, Carbon $end): array
    {
        return BloodTransfusion::where('patient_id', $patient->id)
            ->whereIn('status', [BloodTransfusion::STATUS_PENDING, BloodTransfusion::STATUS_IN_PROGRESS])
            ->orderBy('id')
            ->get()
            ->map(function (BloodTransfusion $unit) use ($now, $end) {
                if ($unit->isRunning()) {
                    $finish = $unit->predictedEndAt();

                    return self::task(
                        $finish && $finish->lessThan($end) ? $finish : null,
                        $now,
                        'transfusion',
                        'Blood unit ' . $unit->unit_number . ($finish ? ' due to finish' : ' running'),
                        $unit->product_type . ' · observations per protocol · 4 h limit ' . $unit->expiresRunningAt()?->format('H:i'),
                        $unit->hasCriticalException() ? 'critical' : 'warning',
                        'transfusion',
                        'Running'
                    );
                }

                // Checks done but something critical open (mismatch, expired, ...) is a hold, not a to-do
                $hold = collect($unit->exceptions())->firstWhere('level', 'critical');

                return self::task(null, $now, 'transfusion',
                    match (true) {
                        $unit->canStart() => 'Unit ' . $unit->unit_number . ' ready to start',
                        $hold !== null => 'Unit ' . $unit->unit_number . ' on hold: ' . $hold['title'],
                        default => 'Bedside checks for unit ' . $unit->unit_number,
                    },
                    $unit->product_type . ' · ' . $unit->completedStepCount() . ' of 4 checks done',
                    $hold ? 'critical' : 'default',
                    'transfusion',
                    'Pre-start');
            })
            ->all();
    }

    private static function infusions(Patient $patient, ?array $infusions, Carbon $now, Carbon $end): array
    {
        $rows = $infusions ?? Infusion::with('infusionPump')
            ->where('patient_id', $patient->id)
            ->active()
            ->get()
            ->map(fn(Infusion $infusion) => [
                'medication' => $infusion->medication_name ?: 'Infusion',
                'status' => $infusion->status,
                'is_warning' => (bool) $infusion->is_warning,
                'alarm_message' => $infusion->alarm_message,
                'ends_at' => $infusion->estimated_completion,
            ])
            ->all();

        $tasks = [];

        foreach ($rows as $row) {
            if (($row['status'] ?? null) === Infusion::STATUS_ALARMING) {
                $tasks[] = self::task(null, $now, 'infusion', 'Pump alarm: ' . $row['medication'], $row['alarm_message'] ?? null, 'critical', 'infusion');
                continue;
            }

            $endsAt = $row['ends_at'] ?? null;
            if (!$endsAt && !empty($row['ends_label'])) {
                // The app bundle carries a clock time for today
                $endsAt = $now->copy()->setTimeFromTimeString($row['ends_label']);
                if ($endsAt->lessThan($now->copy()->subMinutes(5))) {
                    $endsAt->addDay();
                }
            }

            if ($endsAt && $endsAt->lessThan($end)) {
                $tasks[] = self::task($endsAt, $now, 'infusion', $row['medication'] . ' ends', 'Prepare the next bag or flush the line', ($row['is_warning'] ?? false) ? 'warning' : 'default', 'infusion');
            }
        }

        return $tasks;
    }

    private static function movements(Patient $patient, Carbon $start, Carbon $end): array
    {
        return PatientMovement::where('patient_id', $patient->id)
            ->where(fn($query) => $query
                ->where(fn($q) => $q->where('status', 'scheduled')->whereBetween('scheduled_at', [$start, $end]))
                ->orWhere('status', 'sent'))
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn(PatientMovement $movement) => $movement->status === 'sent'
                ? self::task(null, now(), 'movement', 'Out of the ward: ' . $movement->location,
                    'Since ' . $movement->sent_at?->format('H:i'), 'warning', null)
                : self::task($movement->scheduled_at, now(), 'movement', 'To ' . $movement->location
                    . ($movement->location_type ? ' (' . $movement->location_type . ')' : ''), $movement->notes, 'default', null))
            ->all();
    }

    private static function carePlan(Patient $patient, Carbon $now): array
    {
        $slot = NursingCarePlan::currentSlot($patient, $now);

        return NursingCarePlanItem::with('evaluations')
            ->where('patient_id', $patient->id)
            ->where('status', NursingCarePlanItem::STATUS_ACTIVE)
            ->orderBy('id')
            ->get()
            ->reject(fn(NursingCarePlanItem $item) => NursingCarePlan::evaluatedIn($item, $slot))
            ->map(fn(NursingCarePlanItem $item) => self::task(null, $now, 'careplan', 'Evaluate: ' . $item->diagnosis,
                'Goal: ' . Str::limit($item->goal, 90), 'default', 'plan'))
            ->values()
            ->all();
    }

    private static function vitals(Patient $patient, Carbon $now): array
    {
        $vital = VitalSign::where('patient_id', $patient->id)->orderByDesc('recorded_at')->first();

        if (!$vital) {
            return [self::task(null, $now, 'vitals', 'Vital signs', 'None recorded yet this stay', 'warning', 'overview')];
        }

        $score = app(WardDashboardController::class)->calculateEWS($vital)['score'];
        $hoursAgo = ($now->getTimestamp() - $vital->recorded_at->getTimestamp()) / 3600;

        $ago = match (true) {
            $hoursAgo >= 48 => ' · ' . (int) floor($hoursAgo / 24) . ' days ago',
            $hoursAgo >= 4 => ' · ' . (int) floor($hoursAgo) . ' h ago',
            default => '',
        };

        return [self::task(null, $now, 'vitals', 'Vital signs',
            'Last ' . NursingCarePlan::when($vital->recorded_at) . ($score !== null ? ' · EWS ' . $score : '') . $ago,
            $score !== null && $score >= 5 ? 'critical' : (($score !== null && $score >= 3) || $hoursAgo >= 4 ? 'warning' : 'default'),
            'overview',
            $score !== null ? 'EWS ' . $score : null)];
    }

    // -------------------------------------------------------------- helpers

    private static function task(?CarbonInterface $at, CarbonInterface $now, string $category, string $title, ?string $detail, string $tone, ?string $tab, ?string $badge = null): array
    {
        $overdue = $at !== null && $at->lessThan($now);

        return [
            'at' => $at,
            'time_label' => $at ? ($at->isSameDay($now) ? $at->format('H:i') : $at->format('d M H:i')) : null,
            'overdue' => $overdue,
            'category' => $category,
            'title' => $title,
            'detail' => $detail !== null && trim($detail) !== '' ? $detail : null,
            'tone' => $overdue && $tone === 'default' ? 'warning' : $tone,
            'tab' => $tab,
            'badge' => $overdue ? 'Overdue' : $badge,
        ];
    }
}
