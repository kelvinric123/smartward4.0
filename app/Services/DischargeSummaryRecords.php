<?php

namespace App\Services;

use App\Models\AdmissionLog;
use App\Models\BloodTransfusion;
use App\Models\ClinicalIndicatorScore;
use App\Models\ConsultantNote;
use App\Models\ConsultantOrder;
use App\Models\DietType;
use App\Models\FluidBalanceEntry;
use App\Models\FluidBalancePlan;
use App\Models\FluidOverloadAssessment;
use App\Models\IsolationType;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\PatientMovement;
use App\Models\PatientReferral;
use App\Models\SugarReading;
use App\Models\WardNotification;
use App\Support\AdmissionEpisode;
use App\Support\FluidBalanceChart;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The records of one admission that the discharge summary reports beyond the
 * vitals, ECGs and infusions DischargeSummaryService reads itself: ADT events,
 * fluid balance, medication doses, consultant orders and notes, referrals,
 * transfusions, assessment scores, HGT, movements out of the ward and bedside
 * alerts - plus the alerts on the patient record.
 *
 * None of these tables know about admissions, so, like the rest of the
 * summary, everything is selected by patient and the episode's time window.
 */
class DischargeSummaryRecords
{
    /** Admission log actions that belong to booking a bed, not to the stay. */
    private const BOOKING_ACTIONS = ['prebook', 'prebook-pending', 'prebook-activated', 'cancel-prebook'];

    private const FALL_RISK_LABELS = [
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'alert_active' => 'FR Alert Active',
    ];

    /**
     * Every admission log row filed during the stay - the admission, each
     * transfer, a scheduled discharge, the discharge - oldest first.
     *
     * Another admission's opening row is left out even when it falls inside
     * the window (an admission that was never closed runs to "now").
     */
    public function admissionEvents(AdmissionEpisode $episode): Collection
    {
        $admission = $episode->admissionLog;

        $logs = AdmissionLog::with(['user', 'ward'])
            ->where('patient_id', $admission->patient_id)
            ->whereBetween('created_at', [$episode->windowStart, $episode->windowEnd])
            ->whereNotIn('action', self::BOOKING_ACTIONS)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->reject(fn(AdmissionLog $log) => in_array($log->action, DischargeSummaryService::ADMISSION_ACTIONS, true)
                && $log->id !== $admission->id);

        // The row that opened the stay always leads, including one built for a
        // patient admitted before admission logs existed (never saved).
        if (!$logs->contains(fn(AdmissionLog $log) => $admission->exists && $log->id === $admission->id)) {
            $logs->prepend($admission);
        }

        if ($episode->dischargeLog && !$logs->contains('id', $episode->dischargeLog->id)) {
            $logs->push($episode->dischargeLog);
        }

        return $logs->values();
    }

    /**
     * The I/O chart over the stay: counted entries, a row per chart day with
     * a running balance, stay totals by type, fluid plans and overload
     * assessments. Struck-out entries are left out of every figure.
     */
    public function fluidBalance(AdmissionEpisode $episode): array
    {
        $patientId = $episode->admissionLog->patient_id;
        $window = [$episode->windowStart, $episode->windowEnd];

        $entries = FluidBalanceEntry::with('recordedBy')
            ->where('patient_id', $patientId)
            ->whereBetween('recorded_at', $window)
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();

        $counted = $entries->reject(fn(FluidBalanceEntry $entry) => $entry->isVoided())->values();

        $plans = FluidBalancePlan::with('setBy')
            ->where('patient_id', $patientId)
            ->whereBetween('created_at', $window)
            ->orderBy('created_at')
            ->get();

        $assessments = FluidOverloadAssessment::with('recordedBy')
            ->where('patient_id', $patientId)
            ->whereBetween('assessed_at', $window)
            ->orderBy('assessed_at')
            ->get();

        return [
            'entries' => $counted,
            'voidedCount' => $entries->count() - $counted->count(),
            'days' => $this->fluidDays($episode, $counted, $plans),
            'totals' => $this->fluidTotals($counted),
            'plans' => $plans,
            'assessments' => $assessments,
            'weights' => $assessments->filter(fn(FluidOverloadAssessment $a) => $a->weight_kg !== null)->values(),
        ];
    }

    /**
     * One row per chart day from the first charted day to the last, gaps
     * included, each with the limit of the fluid plan in force that day.
     * Chart days follow the ward's own day (from its first shift, 07:00 by
     * default), the same days the I/O chart shows.
     */
    protected function fluidDays(AdmissionEpisode $episode, Collection $entries, Collection $plans): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $wardId = $episode->admissionLog->ward_id ?: $episode->admissionLog->patient?->ward_id;
        [$firstStart] = FluidBalanceChart::dayAt($wardId ? (int) $wardId : null, $entries->first()->recorded_at);

        // Minutes after midnight that a chart day starts; shifting a time back
        // by that much gives the calendar date its chart day starts on.
        $offset = $firstStart->hour * 60 + $firstStart->minute;
        $dayOf = fn(Carbon $at) => $at->copy()->subMinutes($offset)->toDateString();

        $byDay = $entries->groupBy(fn(FluidBalanceEntry $entry) => $dayOf($entry->recorded_at));

        $day = Carbon::parse($dayOf($entries->first()->recorded_at));
        $lastDay = Carbon::parse($dayOf($entries->last()->recorded_at));

        $rows = [];
        $cumulative = 0;

        while ($day->lte($lastDay)) {
            $dayEntries = $byDay->get($day->toDateString(), collect());
            $totals = $this->fluidTotals($dayEntries);
            $cumulative += $totals['balance'];

            $start = $day->copy()->addMinutes($offset);
            $end = $start->copy()->addDay();
            $limit = $plans->filter(fn(FluidBalancePlan $plan) => $plan->created_at->lt($end))->last()?->intake_limit_ml;

            $rows[] = $totals + [
                'start' => $start,
                'end' => $end,
                'count' => $dayEntries->count(),
                'cumulative' => $cumulative,
                'limit' => $limit,
                'over' => $limit !== null && $totals['intake'] > $limit,
            ];

            $day->addDay();
        }

        return $rows;
    }

    /**
     * Intake, output, balance and urine for a set of (counted) entries, with
     * each side broken down by type.
     */
    public function fluidTotals(Collection $entries): array
    {
        $byType = ['intake' => [], 'output' => []];

        foreach ($entries as $entry) {
            $side = $entry->isIntake() ? 'intake' : 'output';
            $byType[$side][$entry->category] = ($byType[$side][$entry->category] ?? 0) + (int) $entry->volume_ml;
        }

        $intake = array_sum($byType['intake']);
        $output = array_sum($byType['output']);

        return [
            'intake' => $intake,
            'output' => $output,
            'balance' => $intake - $output,
            'urine' => $byType['output']['urine'] ?? 0,
            'by_type' => $byType,
        ];
    }

    /**
     * Medication orders active during the stay, each with the doses recorded
     * inside it and its state when the stay ended (or now, while it runs).
     *
     * An order charted during an earlier stay and never stopped only appears
     * if a dose was recorded against it during this one.
     *
     * @return Collection<int, array{order: PatientMedication, doses: Collection, given: int, held: int, refused: int, lastGiven: ?Carbon, state: string}>
     */
    public function medications(AdmissionEpisode $episode): Collection
    {
        $window = [$episode->windowStart, $episode->windowEnd];

        return PatientMedication::with([
            'administrations' => fn($query) => $query->with('recordedBy')->orderBy('administered_at'),
            'createdBy',
            'stoppedBy',
        ])
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->where(fn(Builder $query) => $query
                ->whereBetween('created_at', $window)
                ->orWhereHas('administrations', fn(Builder $doses) => $doses->whereBetween('administered_at', $window)))
            ->orderBy('created_at')
            ->get()
            ->map(function (PatientMedication $order) use ($episode) {
                $doses = $order->administrations
                    ->filter(fn(MedicationAdministration $dose) => $dose->administered_at
                        && $dose->administered_at->betweenIncluded($episode->windowStart, $episode->windowEnd))
                    ->values();

                if ($order->stopped_at && $order->stopped_at->lte($episode->windowEnd)) {
                    $state = PatientMedication::STATUS_STOPPED;
                } elseif ($order->status === PatientMedication::STATUS_COMPLETED) {
                    $state = PatientMedication::STATUS_COMPLETED;
                } else {
                    // Still running, or stopped only after this stay ended.
                    $state = PatientMedication::STATUS_ACTIVE;
                }

                return [
                    'order' => $order,
                    'doses' => $doses,
                    'given' => $doses->where('status', MedicationAdministration::STATUS_GIVEN)->count(),
                    'held' => $doses->where('status', MedicationAdministration::STATUS_HELD)->count(),
                    'refused' => $doses->where('status', MedicationAdministration::STATUS_REFUSED)->count(),
                    'lastGiven' => $doses->where('status', MedicationAdministration::STATUS_GIVEN)->last()?->administered_at,
                    'state' => $state,
                ];
            });
    }

    /**
     * Consultant orders written (or timed) during the stay, with who closed
     * them and each shift handover.
     */
    public function consultantOrders(AdmissionEpisode $episode): Collection
    {
        $window = [$episode->windowStart, $episode->windowEnd];

        return ConsultantOrder::with([
            'consultant',
            'assignedNurse',
            'createdBy',
            'closedBy',
            'handovers' => fn($query) => $query->with(['fromNurse', 'toNurse', 'handedOverBy']),
        ])
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->where(fn(Builder $query) => $query
                ->whereBetween('ordered_at', $window)
                ->orWhereBetween('created_at', $window))
            ->orderBy('ordered_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Notes consultants wrote from the doctor app during the stay.
     */
    public function consultantNotes(AdmissionEpisode $episode): Collection
    {
        return ConsultantNote::with('consultant')
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->whereBetween('created_at', [$episode->windowStart, $episode->windowEnd])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Referrals to a consultant or anaesthetist made during the stay.
     */
    public function referrals(AdmissionEpisode $episode): Collection
    {
        return PatientReferral::with(['consultant', 'anaesthetist', 'creator'])
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->whereBetween('created_at', [$episode->windowStart, $episode->windowEnd])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Blood units registered or started during the stay.
     */
    public function transfusions(AdmissionEpisode $episode): Collection
    {
        $window = [$episode->windowStart, $episode->windowEnd];

        return BloodTransfusion::with(['createdBy', 'checkedBy'])
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->where(fn(Builder $query) => $query
                ->whereBetween('created_at', $window)
                ->orWhereBetween('started_at', $window))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Assessment scale scores (GCS, AVPU, pain, falls, ...) taken during the
     * stay. The band is the one stored when the score was taken.
     */
    public function assessmentScores(AdmissionEpisode $episode): Collection
    {
        return ClinicalIndicatorScore::with(['clinicalIndicator', 'recordedBy'])
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->whereBetween('recorded_at', [$episode->windowStart, $episode->windowEnd])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Blood glucose (HGT) readings taken during the stay, in mmol/L.
     */
    public function glucoseReadings(AdmissionEpisode $episode): Collection
    {
        return SugarReading::with('recordedBy')
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->whereBetween('recorded_at', [$episode->windowStart, $episode->windowEnd])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Trips out of the ward (radiology, theatre, ...) booked, sent or
     * returned during the stay.
     */
    public function movements(AdmissionEpisode $episode): Collection
    {
        $window = [$episode->windowStart, $episode->windowEnd];

        return PatientMovement::where('patient_id', $episode->admissionLog->patient_id)
            ->where(fn(Builder $query) => $query
                ->whereBetween('scheduled_at', $window)
                ->orWhereBetween('sent_at', $window)
                ->orWhereBetween('created_at', $window))
            ->orderByRaw('COALESCE(sent_at, scheduled_at, created_at)')
            ->get();
    }

    /**
     * Bedside alerts raised for the patient during the stay: patient calls,
     * EWS alerts and infusion alerts, with who answered them.
     */
    public function wardAlerts(AdmissionEpisode $episode): Collection
    {
        return WardNotification::with('responder')
            ->where('patient_id', $episode->admissionLog->patient_id)
            ->whereBetween('created_at', [$episode->windowStart, $episode->windowEnd])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Allergies, diet, fall risk, isolation, level of care and HGT monitoring
     * as the patient record holds them now. The record keeps no history of
     * these, so the summary reports them as currently recorded.
     */
    public function clinicalAlerts(?Patient $patient): array
    {
        if (!$patient) {
            return [
                'allergies' => collect(),
                'nbm' => false,
                'diet' => null,
                'dietOrders' => null,
                'feeding' => null,
                'fallRisk' => null,
                'isolation' => null,
                'nursingLevel' => null,
                'hgt' => null,
            ];
        }

        // ADT stores allergies as parsed entries ({allergen, allergen_code,
        // status}); a hand-entered one is a plain string. Legacy names can
        // still carry the HL7 "code^name" form.
        $allergies = collect($patient->allergies ?? [])
            ->map(function ($entry) {
                $raw = is_array($entry) ? ($entry['allergen'] ?? $entry['allergen_code'] ?? null) : $entry;

                if (!is_scalar($raw) || trim((string) $raw) === '') {
                    return null;
                }

                $raw = (string) $raw;
                $name = str_contains($raw, '^') ? (explode('^', $raw)[1] ?? $raw) : $raw;
                $status = is_array($entry) ? (string) ($entry['status'] ?? '') : '';

                return [
                    'name' => trim($name) !== '' ? trim($name) : $raw,
                    'resolved' => strcasecmp($status, 'Resolved') === 0,
                ];
            })
            ->filter()
            ->sortBy(fn(array $allergy) => $allergy['resolved'] ? 1 : 0)
            ->values();

        $dietCodes = collect($patient->diet_types ?? [])
            ->filter(fn($code) => is_string($code) && $code !== '')
            ->values();
        $nbm = $dietCodes->contains(fn(string $code) => in_array(strtoupper($code), Patient::NBM_DIET_CODES, true));
        $diet = $dietCodes
            ->reject(fn(string $code) => in_array(strtoupper($code), Patient::NBM_DIET_CODES, true))
            ->map(fn(string $code) => DietType::getDisplayName($code))
            ->implode(', ');

        $feeding = collect($patient->feeding_routes ?? [])
            ->filter(fn($route) => is_string($route) && $route !== '')
            ->map(fn(string $route) => Patient::FEEDING_ROUTES[$route] ?? strtoupper($route))
            ->implode(', ');

        $isolation = $patient->isolation_type && $patient->isolation_type !== 'none'
            ? IsolationType::getDisplayName($patient->isolation_type)
            : null;

        $nursingLevel = $patient->nursing_level && $patient->nursing_level !== 'none'
            ? ucfirst(str_replace('_', ' ', $patient->nursing_level))
            : null;

        return [
            'allergies' => $allergies,
            'nbm' => $nbm,
            'diet' => $diet !== '' ? $diet : null,
            'dietOrders' => filled($patient->diet_orders) ? $patient->diet_orders : null,
            'feeding' => $feeding !== '' ? $feeding : null,
            'fallRisk' => self::FALL_RISK_LABELS[$patient->fall_risk] ?? null,
            'isolation' => $isolation,
            'nursingLevel' => $nursingLevel,
            'hgt' => $patient->hgt_enabled
                ? ($patient->hgt_frequency ? SugarReading::getFrequencyLabel($patient->hgt_frequency) : 'Enabled')
                : null,
        ];
    }
}
