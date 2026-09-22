<?php

namespace App\Support;

use App\Models\AdmissionLog;
use App\Models\BloodTransfusion;
use App\Models\ClinicalIndicatorScore;
use App\Models\ConsultantNote;
use App\Models\ConsultantOrder;
use App\Models\ConsultantOrderHandover;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\Infusion;
use App\Models\MedicationAdministration;
use App\Models\PatientCareProvider;
use App\Models\PatientMovement;
use App\Models\PatientReferral;
use App\Models\SugarReading;
use App\Models\VitalSign;
use App\Models\WardNotification;
use App\Models\WardScheduleAssignment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The discharge summary's clinical timeline: every event recorded during one
 * admission, from every source, in the order it happened, grouped by day.
 *
 * It is built from the records DischargeSummaryService has already loaded for
 * the summary's other sections, so the timeline and those sections always
 * agree and nothing is queried twice.
 *
 * Each event is a plain array:
 *   at        Carbon    when it happened (the recorded time, not the filing time)
 *   category  string    a key of CATEGORIES
 *   title     string    what happened, e.g. "Dose given"
 *   detail    ?string   the facts, e.g. "Paracetamol 1 g PO"
 *   note      ?string   free text recorded with it (reasons, notes)
 *   by        ?string   who recorded / did it
 *   tone      string    default | good | warning | critical | muted
 *   badge     ?string   a short flag, e.g. "EWS 3", "STAT"
 */
class AdmissionTimeline
{
    public const CATEGORIES = [
        'admission' => 'Admission & discharge',
        'care' => 'Doctors & notes',
        'vitals' => 'Vital signs',
        'fluid' => 'Intake / output',
        'medication' => 'Medications',
        'infusion' => 'Infusions',
        'transfusion' => 'Blood transfusion',
        'order' => 'Consultant orders',
        'assessment' => 'Assessments & HGT',
        'ecg' => 'ECG',
        'movement' => 'Movements',
        'alert' => 'Calls & alerts',
    ];

    /** Longest free text carried into a timeline row; the sections hold the full text. */
    private const NOTE_LIMIT = 240;

    /**
     * @param  array  $summary  the arrays DischargeSummaryService::summaryForEpisode() assembles
     * @return array{days: array, categories: array<string, array{label: string, count: int}>, total: int}
     */
    public static function build(AdmissionEpisode $episode, array $summary): array
    {
        $events = collect()
            ->concat(self::admissionEvents($episode, $summary['admissionEvents'] ?? collect()))
            ->concat(self::careEvents($episode, $summary))
            ->concat(self::vitalEvents($summary['vitalSigns'] ?? collect(), $summary['vitalEws'] ?? [], $summary['ews']['label'] ?? 'EWS'))
            ->concat(self::fluidEvents($summary['fluidBalance'] ?? []))
            ->concat(self::medicationEvents($episode, $summary['medications'] ?? collect()))
            ->concat(self::infusionEvents($summary['infusions'] ?? collect()))
            ->concat(self::transfusionEvents($summary['transfusions'] ?? collect()))
            ->concat(self::orderEvents($episode, $summary['consultantOrders'] ?? collect()))
            ->concat(self::assessmentEvents($summary['assessmentScores'] ?? collect(), $summary['glucoseReadings'] ?? collect()))
            ->concat(self::ecgEvents($summary['ecgFiles'] ?? collect()))
            ->concat(self::movementEvents($summary['movements'] ?? collect()))
            ->concat(self::alertEvents($summary['wardAlerts'] ?? collect()))
            ->filter(fn(array $event) => $event['at'] instanceof CarbonInterface)
            // Records can be back-dated or filed late; nothing outside the stay
            // belongs on its timeline.
            ->filter(fn(array $event) => $event['at']->betweenIncluded($episode->windowStart, $episode->windowEnd))
            ->values();

        $categoryOrder = array_flip(array_keys(self::CATEGORIES));
        $events = $events
            ->sort(fn(array $a, array $b) => [$a['at']->getTimestamp(), $categoryOrder[$a['category']] ?? 99]
                <=> [$b['at']->getTimestamp(), $categoryOrder[$b['category']] ?? 99])
            ->values();

        $categories = [];
        foreach (self::CATEGORIES as $key => $label) {
            $categories[$key] = ['label' => $label, 'count' => $events->where('category', $key)->count()];
        }

        return [
            'days' => self::days(
                $episode,
                $events,
                self::bedsByDate($episode, $summary['admissionEvents'] ?? collect()),
                $summary['nursingRoster']['assignments'] ?? collect(),
                $summary['nursingRoster']['shiftLabels'] ?? [],
            ),
            'categories' => $categories,
            'total' => $events->count(),
        ];
    }

    /**
     * Which bed(s) the patient was in on each day of the stay, as
     * "ward_id|bed_number" keys: the bed at the start of the day plus any bed
     * a transfer moved them to that day. Used to put only the nurses who had
     * the patient on each day's roster line.
     */
    protected static function bedsByDate(AdmissionEpisode $episode, Collection $logs): array
    {
        $moves = $logs
            ->filter(fn(AdmissionLog $log) => $log->ward_id && $log->bed_number
                && ($log->action === 'transfer' || $log === $episode->admissionLog
                    || ($log->exists && $log->id === $episode->admissionLog->id)))
            ->map(fn(AdmissionLog $log) => [
                'at' => $log->action === 'transfer' ? $log->created_at : $episode->windowStart,
                'bed' => $log->ward_id . '|' . $log->bed_number,
            ])
            ->sortBy(fn(array $move) => $move['at']->getTimestamp())
            ->values();

        $beds = [];
        $day = $episode->windowStart->copy()->startOfDay();
        $lastDay = $episode->windowEnd->copy()->startOfDay();

        while ($day->lte($lastDay)) {
            $dayEnd = $day->copy()->endOfDay();
            $current = $moves->filter(fn(array $move) => $move['at']->lt($day))->last();
            $movedIn = $moves->filter(fn(array $move) => $move['at']->betweenIncluded($day, $dayEnd));

            $beds[$day->toDateString()] = collect([$current])->filter()->concat($movedIn)
                ->pluck('bed')->unique()->values()->all();

            $day->addDay();
        }

        return $beds;
    }

    /**
     * Group events by calendar day, Day 1 being the day of admission. Days
     * with nothing recorded are kept, so a gap in the record shows as a gap,
     * but a run of them collapses into one "nothing recorded" block.
     */
    protected static function days(AdmissionEpisode $episode, Collection $events, array $bedsByDate, Collection $assignments, array $shiftLabels): array
    {
        $byDate = $events->groupBy(fn(array $event) => $event['at']->toDateString());
        $rosterByDate = $assignments->groupBy(fn(WardScheduleAssignment $a) => Carbon::parse($a->scheduled_date)->toDateString());

        $admissionDay = $episode->admittedAt->copy()->startOfDay();
        $day = $episode->windowStart->copy()->startOfDay();
        $lastDay = $episode->windowEnd->copy()->startOfDay();

        $blocks = [];
        $emptyRun = null;

        while ($day->lte($lastDay)) {
            $date = $day->toDateString();
            $dayEvents = $byDate->get($date, collect())->values();

            if ($dayEvents->isEmpty()) {
                $emptyRun = $emptyRun ?? ['empty' => true, 'from' => $day->copy(), 'to' => $day->copy()];
                $emptyRun['to'] = $day->copy();
            } else {
                if ($emptyRun) {
                    $blocks[] = $emptyRun;
                    $emptyRun = null;
                }

                // Only the nurses rostered to a bed the patient was in that day;
                // with no bed history to go on, everyone rostered to their beds.
                $dayBeds = $bedsByDate[$date] ?? [];
                $dayRoster = $rosterByDate->get($date, collect())
                    ->filter(fn(WardScheduleAssignment $a) => !$dayBeds || !$a->bed
                        || in_array($a->bed->ward_id . '|' . $a->bed->bed_number, $dayBeds, true));

                $blocks[] = [
                    'empty' => false,
                    'date' => $day->copy(),
                    'number' => (int) floor(($day->getTimestamp() - $admissionDay->getTimestamp()) / 86400) + 1,
                    'events' => $dayEvents,
                    'counts' => $dayEvents->countBy('category')->all(),
                    'roster' => self::rosterForDay($dayRoster, $shiftLabels),
                ];
            }

            $day->addDay();
        }

        if ($emptyRun) {
            $blocks[] = $emptyRun;
        }

        return $blocks;
    }

    /**
     * The nurses rostered to the patient's bed(s) that day, per shift, in the
     * ward's shift order.
     */
    protected static function rosterForDay(Collection $assignments, array $shiftLabels): array
    {
        $roster = [];

        foreach ($shiftLabels ?: ['AM' => 'AM', 'PM' => 'PM', 'ON' => 'ON'] as $code => $label) {
            $names = $assignments
                ->where('shift', $code)
                ->map(fn(WardScheduleAssignment $a) => $a->nurse?->name)
                ->filter()
                ->unique()
                ->values();

            if ($names->isNotEmpty()) {
                $roster[] = ['shift' => $label, 'nurses' => $names->implode(', ')];
            }
        }

        return $roster;
    }

    // ------------------------------------------------------------ sources

    protected static function admissionEvents(AdmissionEpisode $episode, Collection $logs): Collection
    {
        $events = collect();

        foreach ($logs as $log) {
            $place = self::place($log->ward?->ward_name, $log->bed_number);
            $by = $log->user?->name ?? ($log->source === 'adt' ? 'HIS (ADT)' : null);
            $isOpening = $log === $episode->admissionLog
                || ($log->exists && $log->id === $episode->admissionLog->id);

            if ($isOpening) {
                $facts = array_filter([
                    $place,
                    $log->consultant_name ? 'Consultant ' . $log->consultant_name : null,
                    $log->source === 'adt' ? 'via HIS (ADT)' : null,
                ]);

                $events->push(self::event(
                    $episode->admittedAt,
                    'admission',
                    $log->action === 'check-in' ? 'Checked in (pre-booked bed)' : 'Admitted',
                    implode(' · ', $facts) ?: null,
                    $log->notes,
                    $by,
                    'good',
                ));

                continue;
            }

            [$title, $tone] = match ($log->action) {
                'transfer' => ['Transferred', 'default'],
                'discharge_scheduled' => ['Discharge scheduled', 'default'],
                'pending_discharge' => ['Pending discharge', 'default'],
                'discharge' => ['Discharged', 'good'],
                default => [AdmissionLog::actionLabel($log->action), 'default'],
            };

            $at = $log->action === 'discharge' ? ($log->discharged_at ?? $log->created_at) : $log->created_at;

            $events->push(self::event(
                $at,
                'admission',
                $title,
                $log->action === 'transfer' ? ($place ? 'To ' . $place : null) : ($log->action === 'discharge' ? ($place ? 'From ' . $place : null) : null),
                $log->notes,
                $by,
                $tone,
            ));
        }

        // Discharged without a closing log (legacy data): still end the story.
        if ($episode->isDischarged() && !$episode->dischargeLog) {
            $events->push(self::event($episode->dischargedAt, 'admission', 'Discharged', null, null, null, 'good'));
        }

        return $events;
    }

    protected static function careEvents(AdmissionEpisode $episode, array $summary): Collection
    {
        $events = collect();

        foreach ($summary['careTeam']['careProviders'] ?? [] as $provider) {
            /** @var PatientCareProvider $provider */
            if (!$provider->assigned_at) {
                continue;
            }

            $isAnaesthetist = $provider->anaesthetist_id !== null;
            $events->push(self::event(
                $provider->assigned_at,
                'care',
                $isAnaesthetist ? 'Anaesthetist assigned' : ($provider->role_label ?? 'Doctor') . ' assigned',
                trim(($provider->display_name ?? '') . ($provider->consultant?->specialty?->name ? ' (' . $provider->consultant->specialty->name . ')' : '')) ?: null,
                null,
                $provider->source === PatientCareProvider::SOURCE_MANUAL ? 'Added on the ward' : 'HIS (ADT)',
            ));
        }

        foreach ($summary['referrals'] ?? [] as $referral) {
            /** @var PatientReferral $referral */
            $to = $referral->referral_type === 'anaesthetist'
                ? $referral->anaesthetist?->name
                : $referral->consultant?->name;

            $events->push(self::event(
                $referral->created_at,
                'care',
                'Referred to ' . ($referral->referral_type === 'anaesthetist' ? 'anaesthetist' : 'consultant'),
                trim(($to ?? '') . ($referral->reason ? ' - ' . $referral->reason : '')) ?: null,
                $referral->notes,
                $referral->creator?->name,
            ));
        }

        foreach ($summary['consultantNotes'] ?? [] as $note) {
            /** @var ConsultantNote $note */
            $events->push(self::event(
                $note->created_at,
                'care',
                'Consultant note',
                null,
                $note->note,
                $note->consultant?->name,
            ));
        }

        return $events;
    }

    protected static function vitalEvents(Collection $vitals, array $ewsScores, string $ewsLabel): Collection
    {
        return $vitals->map(function (VitalSign $vital) use ($ewsScores, $ewsLabel) {
            $parts = array_filter([
                $vital->blood_pressure ? 'BP ' . $vital->blood_pressure : null,
                $vital->pulse_rate_display !== null ? 'HR ' . $vital->pulse_rate_display : null,
                $vital->temperature !== null ? 'T ' . number_format((float) $vital->temperature, 1) . ' °C' : null,
                $vital->spo2_display !== null ? 'SpO₂ ' . $vital->spo2_display . '%' : null,
                $vital->respiratory_rate !== null ? 'RR ' . $vital->respiratory_rate : null,
                $vital->oxygen_delivery ? 'O₂ ' . $vital->oxygenShortLabel() : null,
            ]);

            $score = $ewsScores[$vital->id] ?? null;

            return self::event(
                $vital->recorded_at,
                'vitals',
                'Vital signs',
                implode(' · ', $parts) ?: 'No values recorded',
                $vital->notes,
                $vital->operator?->name ?? $vital->recordedBy?->name ?? ($vital->gateway_id ? 'Monitor ' . $vital->gateway_id : null),
                $score === null ? 'default' : ($score >= 5 ? 'critical' : ($score >= 3 ? 'warning' : 'default')),
                $score !== null ? $ewsLabel . ' ' . $score : null,
            );
        });
    }

    protected static function fluidEvents(array $fluid): Collection
    {
        $events = collect();

        foreach ($fluid['entries'] ?? [] as $entry) {
            /** @var FluidBalanceEntry $entry */
            $events->push(self::event(
                $entry->recorded_at,
                'fluid',
                $entry->isIntake() ? 'Intake' : 'Output',
                $entry->label() . ' · ' . FluidBalanceEntry::formatMl((int) $entry->volume_ml),
                null,
                $entry->recordedBy?->name,
            ));
        }

        foreach ($fluid['plans'] ?? [] as $plan) {
            /** @var FluidBalancePlan $plan */
            $events->push(self::event(
                $plan->created_at,
                'fluid',
                'Fluid plan set',
                $plan->summary(),
                $plan->notes,
                $plan->setBy?->name,
            ));
        }

        foreach ($fluid['assessments'] ?? [] as $assessment) {
            /** @var FluidOverloadAssessment $assessment */
            $facts = array_filter([
                $assessment->edemaSummary(),
                $assessment->signLabels() ? implode(', ', $assessment->signLabels()) : null,
                $assessment->weight_kg !== null ? 'Weight ' . number_format((float) $assessment->weight_kg, 1) . ' kg' : null,
            ]);

            $events->push(self::event(
                $assessment->assessed_at,
                'fluid',
                'Fluid overload assessment',
                implode(' · ', $facts) ?: null,
                $assessment->notes,
                $assessment->recordedBy?->name,
                $assessment->hasUrgentSign() ? 'critical' : ($assessment->hasOverloadSigns() || $assessment->hasEdema() ? 'warning' : 'default'),
            ));
        }

        return $events;
    }

    protected static function medicationEvents(AdmissionEpisode $episode, Collection $medications): Collection
    {
        $events = collect();

        foreach ($medications as $row) {
            $order = $row['order'];
            $name = $order->medication_name;
            $alert = $order->is_high_alert ? 'High alert' : null;

            if ($order->created_at && $order->created_at->betweenIncluded($episode->windowStart, $episode->windowEnd)) {
                $events->push(self::event(
                    $order->created_at,
                    'medication',
                    'Medication charted',
                    $name . ' ' . $order->summary(),
                    $order->instructions,
                    $order->createdBy?->name,
                    'default',
                    $alert,
                ));
            }

            foreach ($row['doses'] as $dose) {
                /** @var MedicationAdministration $dose */
                $given = $dose->status === MedicationAdministration::STATUS_GIVEN;
                $amount = $dose->dose_amount !== null
                    ? $order::formatAmount($dose->dose_amount) . ' ' . ($dose->dose_unit ?? $order->dose_unit)
                    : $order->doseLabel();

                $events->push(self::event(
                    $dose->administered_at,
                    'medication',
                    'Dose ' . strtolower($dose->statusLabel()),
                    trim($name . ' ' . $amount . ' ' . $order->route),
                    $dose->notes,
                    $dose->recordedBy?->name,
                    $given ? 'default' : 'warning',
                    $alert,
                ));
            }

            if ($row['state'] === 'stopped') {
                $events->push(self::event(
                    $order->stopped_at,
                    'medication',
                    'Medication stopped',
                    $name . ' ' . $order->summary(),
                    $order->stop_reason,
                    $order->stoppedBy?->name,
                    'muted',
                ));
            }
        }

        return $events;
    }

    protected static function infusionEvents(Collection $infusions): Collection
    {
        $events = collect();

        foreach ($infusions as $infusion) {
            /** @var Infusion $infusion */
            $pump = $infusion->infusionPump?->device_name ?? $infusion->infusionPump?->serial_no;
            $what = trim(($infusion->medication_name ?? 'Infusion') . ($infusion->formatted_concentration ? ' ' . $infusion->formatted_concentration : ''));
            $started = $infusion->started_at ?? $infusion->last_updated_at ?? $infusion->created_at;

            $events->push(self::event(
                $started ? Carbon::parse($started) : null,
                'infusion',
                $infusion->exists ? 'Infusion started' : 'Infusion running',
                implode(' · ', array_filter([
                    $what,
                    $infusion->flow_rate !== null ? number_format((float) $infusion->flow_rate, 1) . ' mL/h' : null,
                    $pump ? 'Pump ' . $pump : null,
                ])),
                null,
                null,
            ));

            if ($infusion->completed_at && in_array($infusion->status, [Infusion::STATUS_COMPLETED, Infusion::STATUS_STOPPED], true)) {
                $events->push(self::event(
                    Carbon::parse($infusion->completed_at),
                    'infusion',
                    $infusion->status === Infusion::STATUS_COMPLETED ? 'Infusion completed' : 'Infusion stopped',
                    implode(' · ', array_filter([
                        $what,
                        $infusion->infused_volume !== null ? number_format((float) $infusion->infused_volume, 1) . ' mL infused' : null,
                    ])),
                    $infusion->alarm_message,
                    null,
                    $infusion->status === Infusion::STATUS_COMPLETED ? 'default' : 'warning',
                ));
            }
        }

        return $events;
    }

    protected static function transfusionEvents(Collection $transfusions): Collection
    {
        $events = collect();

        foreach ($transfusions as $unit) {
            /** @var BloodTransfusion $unit */
            $what = trim($unit->product_type . ($unit->unit_number ? ' · unit ' . $unit->unit_number : '')
                . ($unit->unit_blood_group ? ' · ' . $unit->unit_blood_group : ''));

            $events->push(self::event($unit->created_at, 'transfusion', 'Blood unit registered', $what, $unit->notes, $unit->createdBy?->name, 'muted'));

            if ($unit->started_at) {
                $events->push(self::event($unit->started_at, 'transfusion', 'Transfusion started', $what, null, $unit->checkedBy?->name));
            }

            if ($unit->completed_at) {
                $stopped = $unit->status === BloodTransfusion::STATUS_STOPPED;
                $events->push(self::event(
                    $unit->completed_at,
                    'transfusion',
                    $stopped ? 'Transfusion stopped' : 'Transfusion completed',
                    implode(' · ', array_filter([
                        $what,
                        $unit->volume_ml ? $unit->volume_ml . ' mL unit' : null,
                        $unit->elapsedMinutes() !== null ? 'ran ' . self::duration($unit->elapsedMinutes()) : null,
                    ])),
                    $stopped ? $unit->stop_reason : null,
                    null,
                    $stopped ? 'critical' : 'default',
                ));
            }
        }

        return $events;
    }

    protected static function orderEvents(AdmissionEpisode $episode, Collection $orders): Collection
    {
        $events = collect();

        foreach ($orders as $order) {
            /** @var ConsultantOrder $order */
            $events->push(self::event(
                $order->ordered_at,
                'order',
                'Order written',
                $order->instruction,
                null,
                $order->consultant_name ?? $order->consultant?->name ?? $order->createdBy?->name,
                match ($order->urgency) {
                    'stat' => 'critical',
                    'urgent' => 'warning',
                    default => 'default',
                },
                $order->urgencyLabel(),
            ));

            foreach ($order->handovers as $handover) {
                /** @var ConsultantOrderHandover $handover */
                $events->push(self::event(
                    $handover->created_at,
                    'order',
                    'Order handed over',
                    Str::limit($order->instruction, 80) . ' → ' . ($handover->toNurse?->name ?? 'unassigned')
                        . ' (' . $handover->toLabel($episode->windowEnd) . ')',
                    $handover->note,
                    $handover->handedOverBy?->name,
                    'muted',
                ));
            }

            if ($order->closed_at && $order->status !== ConsultantOrder::STATUS_OPEN) {
                $done = $order->status === ConsultantOrder::STATUS_DONE;
                $events->push(self::event(
                    $order->closed_at,
                    'order',
                    $done ? 'Order done' : 'Order cancelled',
                    Str::limit($order->instruction, 120),
                    $order->outcome_note,
                    $order->closedBy?->name,
                    $done ? 'good' : 'muted',
                ));
            }
        }

        return $events;
    }

    protected static function assessmentEvents(Collection $scores, Collection $glucose): Collection
    {
        $events = collect();

        foreach ($scores as $score) {
            /** @var ClinicalIndicatorScore $score */
            $indicator = $score->clinicalIndicator;

            $events->push(self::event(
                $score->recorded_at,
                'assessment',
                $indicator?->name ?? 'Assessment',
                implode(' · ', array_filter([
                    'Score ' . $score->score,
                    $score->band_label,
                    $score->breakdown(),
                ])),
                $score->notes,
                $score->recordedBy?->name,
                match ($score->band_tone) {
                    'high' => 'critical',
                    'moderate' => 'warning',
                    default => 'default',
                },
                $indicator?->code,
            ));
        }

        foreach ($glucose as $reading) {
            /** @var SugarReading $reading */
            $events->push(self::event(
                $reading->recorded_at,
                'assessment',
                'Blood glucose (HGT)',
                number_format((float) $reading->value, 1) . ' mmol/L'
                    . ($reading->isLow() ? ' · low' : ($reading->isHigh() ? ' · high' : '')),
                $reading->notes,
                $reading->recordedBy?->name,
                $reading->isLow() ? 'critical' : ($reading->isHigh() ? 'warning' : 'default'),
                'HGT',
            ));
        }

        return $events;
    }

    protected static function ecgEvents(Collection $files): Collection
    {
        return $files->map(fn(array $file) => self::event(
            self::parse($file['recorded_at'] ?? null),
            'ecg',
            'ECG recorded',
            ($file['source'] ?? null) === 'manual' ? 'Uploaded report' : 'From the ECG machine',
        ));
    }

    protected static function movementEvents(Collection $movements): Collection
    {
        $events = collect();

        foreach ($movements as $movement) {
            /** @var PatientMovement $movement */
            $where = trim($movement->location . ($movement->location_type ? ' (' . $movement->location_type . ')' : ''));

            if ($movement->created_at) {
                $events->push(self::event(
                    $movement->created_at,
                    'movement',
                    'Movement booked',
                    $where . ($movement->scheduled_at ? ' for ' . $movement->scheduled_at->format('d M, H:i') : ''),
                    $movement->notes,
                    null,
                    'muted',
                ));
            }

            if ($movement->sent_at) {
                $events->push(self::event($movement->sent_at, 'movement', 'Left the ward', $where));
            }

            if ($movement->returned_at) {
                $events->push(self::event($movement->returned_at, 'movement', 'Back on the ward', $where, null, null, 'good'));
            }
        }

        return $events;
    }

    protected static function alertEvents(Collection $alerts): Collection
    {
        return $alerts->map(function (WardNotification $alert) {
            $title = match ($alert->type) {
                WardNotification::TYPE_PATIENT_REQUEST => 'Patient call',
                WardNotification::TYPE_EWS => 'EWS alert',
                WardNotification::TYPE_INFUSION => 'Infusion alert',
                default => 'Alert',
            };

            if ($alert->responded_at) {
                $minutes = (int) max(0, round(($alert->responded_at->getTimestamp() - $alert->created_at->getTimestamp()) / 60));
                $response = ($alert->responder?->name ? 'Answered by ' . $alert->responder->name : 'Cleared automatically')
                    . ' after ' . self::duration($minutes);
            } else {
                $response = 'Not answered';
            }

            return self::event(
                $alert->created_at,
                'alert',
                $title,
                $alert->message,
                $response,
                null,
                match ($alert->severity) {
                    WardNotification::SEVERITY_URGENT => 'critical',
                    WardNotification::SEVERITY_WARNING => 'warning',
                    default => 'default',
                },
                $alert->type === WardNotification::TYPE_EWS && $alert->ews_score !== null ? 'EWS ' . $alert->ews_score : null,
            );
        });
    }

    // ------------------------------------------------------------- helpers

    protected static function event(
        ?CarbonInterface $at,
        string $category,
        string $title,
        ?string $detail = null,
        ?string $note = null,
        ?string $by = null,
        string $tone = 'default',
        ?string $badge = null,
    ): array {
        $note = $note !== null ? trim($note) : null;

        return [
            'at' => $at,
            'category' => $category,
            'title' => $title,
            'detail' => $detail !== null && trim($detail) !== '' ? trim($detail) : null,
            'note' => $note !== null && $note !== '' ? Str::limit($note, self::NOTE_LIMIT) : null,
            'by' => $by,
            'tone' => $tone,
            'badge' => $badge,
        ];
    }

    protected static function place(?string $ward, ?string $bed): ?string
    {
        $parts = array_filter([$ward, $bed ? 'Bed ' . $bed : null]);

        return $parts ? implode(', ', $parts) : null;
    }

    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $hours . ' h' . ($rest ? ' ' . $rest . ' min' : '');
    }

    protected static function parse(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
