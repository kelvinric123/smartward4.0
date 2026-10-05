<?php

namespace App\Services\NurseScheduling;

use App\Http\Controllers\WardDashboardController;
use App\Models\Bed;
use App\Models\ConsultantOrder;
use App\Models\Infusion;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * How much nursing each bed on a ward needs right now, as a score that a
 * nurse's beds can be added up by. An occupied bed starts at the patient
 * weight, and each thing that makes the patient heavier to look after adds
 * its weight. An empty bed counts a little, to cover an admission. The
 * weights are the ward's (WorkloadWeights), and a weight of 0 switches that
 * factor off.
 *
 * Built from what the ward dashboard already knows: nursing level, the latest
 * early warning score (last 24 h), isolation, fall risk, a running infusion,
 * an open STAT order and a fresh admission. A planning aid, not a validated
 * acuity tool.
 */
final class WorkloadCalculator
{
    /**
     * @return Collection<int, array{bed: Bed, patient: ?Patient, score: float, factors: array<int, array{label: string, points: float}>}>
     *         keyed by bed id, in bed order
     */
    public static function forWard(Ward $ward, ?WorkloadWeights $weights = null): Collection
    {
        $weights ??= WorkloadWeights::forWard($ward);

        // A bed under maintenance has no patient and needs no nurse
        $beds = Bed::where('ward_id', $ward->id)
            ->where('is_active', true)
            ->where('status', '<>', Bed::STATUS_MAINTENANCE)
            ->orderByRaw('CAST(bed_number AS UNSIGNED)')
            ->orderBy('bed_number')
            ->get();

        $patients = Patient::where('ward_id', $ward->id)
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->whereNotNull('bed_number')
            ->get()
            ->keyBy('bed_number');

        $patientIds = $patients->pluck('id');
        $withInfusion = Infusion::whereIn('patient_id', $patientIds)->active()->pluck('patient_id')->flip();
        $withStatOrder = ConsultantOrder::whereIn('patient_id', $patientIds)->open()
            ->where('urgency', 'stat')->pluck('patient_id')->flip();

        $ewsSystem = self::ewsSystem();
        $dashboard = app(WardDashboardController::class);

        return $beds->mapWithKeys(function (Bed $bed) use ($patients, $withInfusion, $withStatOrder, $ewsSystem, $dashboard, $weights) {
            $patient = $patients->get($bed->bed_number);
            $factors = [];
            $add = function (string $label, string $weight) use (&$factors, $weights) {
                if ($weights->get($weight) > 0) {
                    $factors[] = ['label' => $label, 'points' => $weights->get($weight)];
                }
            };

            if (!$patient) {
                $add('Empty bed', 'empty_bed');

                return [$bed->id => self::row($bed, null, $factors)];
            }

            $add('Patient', 'patient');

            if (preg_match('/^level_([1-4])$/', (string) $patient->nursing_level, $level)) {
                $add('Nursing level ' . $level[1], 'nursing_level_' . $level[1]);
            }

            // The same early warning score the bed box shows, if the vitals are recent
            $vitals = VitalSign::where('patient_id', $patient->id)->orderByDesc('recorded_at')->first();
            if ($vitals && $vitals->recorded_at && $vitals->recorded_at->gt(now()->subDay())) {
                $ews = $dashboard->calculateEWS($vitals, $ewsSystem)['score'] ?? null;
                if ($ews !== null && $ews >= 3) {
                    $add('EWS ' . $ews, $ews >= 7 ? 'ews_high' : ($ews >= 5 ? 'ews_medium' : 'ews_low'));
                }
            }

            if (!in_array($patient->isolation_type, [null, '', 'none'], true)) {
                $add('Isolation', 'isolation');
            }
            // The ADT feed sends an active fall risk alert as 1
            if (in_array((string) $patient->fall_risk, ['high', 'alert_active', '1'], true)) {
                $add('High fall risk', 'fall_risk');
            }
            if (isset($withInfusion[$patient->id])) {
                $add('Infusion running', 'infusion');
            }
            if (isset($withStatOrder[$patient->id])) {
                $add('STAT order', 'stat_order');
            }
            if ($patient->admitted_at && $patient->admitted_at->gt(now()->subDay())) {
                $add('New admission', 'new_admission');
            }

            return [$bed->id => self::row($bed, $patient, $factors)];
        });
    }

    /**
     * Each nurse's share of a shift, from a bed id => nurse id map. Every
     * nurse listed gets a row, even with nothing assigned. A load more than
     * $band (a fraction) above or below the shift average is heavy or light.
     *
     * @return array<int, array{beds: int, patients: int, score: float, level: string}>
     */
    public static function loads(Collection $beds, array $mapping, iterable $nurseIds, float $band = 0.25): array
    {
        $loads = [];
        foreach ($nurseIds as $nurseId) {
            $loads[$nurseId] = ['beds' => 0, 'patients' => 0, 'score' => 0.0, 'level' => 'even'];
        }

        foreach ($mapping as $bedId => $nurseId) {
            $row = $beds->get($bedId);
            if (!$row || !$nurseId) {
                continue;
            }
            $loads[$nurseId] ??= ['beds' => 0, 'patients' => 0, 'score' => 0.0, 'level' => 'even'];
            $loads[$nurseId]['beds']++;
            $loads[$nurseId]['patients'] += $row['patient'] ? 1 : 0;
            $loads[$nurseId]['score'] = round($loads[$nurseId]['score'] + $row['score'], 1);
        }

        // Heavy or light against the shift's average, so an uneven split stands out
        $average = count($loads) ? array_sum(array_column($loads, 'score')) / count($loads) : 0;
        foreach ($loads as $nurseId => $load) {
            $loads[$nurseId]['level'] = match (true) {
                $average <= 0 => 'even',
                $load['score'] > $average * (1 + $band) => 'heavy',
                $load['score'] < $average * (1 - $band) => 'light',
                default => 'even',
            };
        }

        return $loads;
    }

    private static function row(Bed $bed, ?Patient $patient, array $factors): array
    {
        return [
            'bed' => $bed,
            'patient' => $patient,
            'score' => round(array_sum(array_column($factors, 'points')), 1),
            'factors' => $factors,
        ];
    }

    /** The early warning score system the signed-in user set on the ward dashboard. */
    private static function ewsSystem(): string
    {
        $settings = Auth::id() ? WardDashboardSetting::where('user_id', Auth::id())->first() : null;
        $clinical = is_array($settings?->clinical_settings) ? $settings->clinical_settings : [];

        return $clinical['ews_system'] ?? 'ews_ihh';
    }
}
