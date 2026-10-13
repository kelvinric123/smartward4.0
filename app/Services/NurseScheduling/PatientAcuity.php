<?php

namespace App\Services\NurseScheduling;

use App\Http\Controllers\WardDashboardController;
use App\Models\Bed;
use App\Models\BloodTransfusion;
use App\Models\ClinicalIndicatorScore;
use App\Models\ConsultantOrder;
use App\Models\OxygenTherapyChange;
use App\Models\Patient;
use App\Models\PatientAcuityRating;
use App\Models\VitalSign;
use App\Models\Ward;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Patient classification for the AI Nurse Schedule's Patient acuity tab: each
 * patient's care level, 1 Minimal to 4 Intensive. The level is the nurse's
 * rating, kept on patients.nursing_level (the level the ward dashboard shows);
 * the history of ratings is in patient_acuity_ratings.
 *
 * A prototype classification checked by care-interaction indicators (ANA,
 * 2017, pp. 10-12; see App\Support\AcuityReferences). Every indicator in
 * CRITERIA that a patient meets suggests a lowest level; the suggestion is the
 * highest of them, one level higher when three or more areas of care need it
 * at once (nursing work is more than the sum of its tasks). Where the rating
 * and the suggestion differ the tab asks for a review, but the nurse's
 * judgement stands: the indicators cannot see everything a nurse can.
 *
 * Built only from what the system already records. A planning aid, not a
 * validated acuity tool: a ward should check it against its own care before
 * relying on it.
 */
final class PatientAcuity
{
    public const LEVELS = [
        1 => [
            'name' => 'Minimal care',
            'short' => 'Minimal',
            'description' => 'Stable and largely self-caring. Routine observations and medicines.',
        ],
        2 => [
            'name' => 'Moderate care',
            'short' => 'Moderate',
            'description' => 'Some help with daily living, or one or two added needs such as oxygen, an infusion, isolation, a fall risk or a new admission.',
        ],
        3 => [
            'name' => 'Maximum care',
            'short' => 'Maximum',
            'description' => 'Unstable or at risk of getting worse, highly dependent, or several needs at once. Frequent observations.',
        ],
        4 => [
            'name' => 'Intensive care',
            'short' => 'Intensive',
            'description' => 'Critically ill or needing constant observation, close to one nurse for one or two patients.',
        ],
    ];

    public const DIMENSIONS = [
        'stability' => 'Physiological stability',
        'breathing' => 'Breathing support',
        'neuro' => 'Consciousness and cognition',
        'safety' => 'Safety and mental state',
        'skin' => 'Skin and mobility',
        'treatment' => 'Treatments',
        'infection' => 'Infection control',
        'flow' => 'Admission',
        'setting' => 'Ward setting',
        'complexity' => 'Several needs at once',
    ];

    private const EWS_SOURCE = 'Vital signs in the last 24 h, on the ward\'s early warning score: the higher of the newest reading and the newest full set';

    private const OXYGEN_SOURCE = 'The newer of the last oxygen therapy setting and the last oxygen on the vital signs, this admission';

    /** Every indicator: the area of care, what it is, the lowest level it suggests and where it is read from. */
    public const CRITERIA = [
        ['dimension' => 'stability', 'indicator' => 'Early warning score 7 or more', 'level' => 4, 'source' => self::EWS_SOURCE],
        ['dimension' => 'stability', 'indicator' => 'Early warning score 5 to 6', 'level' => 3, 'source' => self::EWS_SOURCE],
        ['dimension' => 'stability', 'indicator' => 'Early warning score 3 to 4', 'level' => 2, 'source' => self::EWS_SOURCE],
        ['dimension' => 'breathing', 'indicator' => 'Mechanical ventilation', 'level' => 4, 'source' => self::OXYGEN_SOURCE],
        ['dimension' => 'breathing', 'indicator' => 'BiPAP, CPAP, high-flow or non-rebreather mask', 'level' => 3, 'source' => self::OXYGEN_SOURCE],
        ['dimension' => 'breathing', 'indicator' => 'Other supplemental oxygen', 'level' => 2, 'source' => self::OXYGEN_SOURCE],
        ['dimension' => 'neuro', 'indicator' => 'GCS 8 or below, or AVPU responds to pain or unresponsive', 'level' => 4, 'source' => 'GCS or AVPU in the last 24 h'],
        ['dimension' => 'neuro', 'indicator' => 'GCS 9 to 12, or AVPU responds to voice', 'level' => 3, 'source' => 'GCS or AVPU in the last 24 h'],
        ['dimension' => 'neuro', 'indicator' => '4AT 4 or more (possible delirium)', 'level' => 3, 'source' => '4AT in the last 72 h'],
        ['dimension' => 'neuro', 'indicator' => 'GCS 13 to 14, or 4AT 1 to 3', 'level' => 2, 'source' => 'GCS in the last 24 h, 4AT in the last 72 h'],
        ['dimension' => 'safety', 'indicator' => 'C-SSRS high risk', 'level' => 4, 'source' => 'Mental state assessment in the last 72 h'],
        ['dimension' => 'safety', 'indicator' => 'C-SSRS moderate risk', 'level' => 3, 'source' => 'Mental state assessment in the last 72 h'],
        ['dimension' => 'safety', 'indicator' => 'C-SSRS low risk', 'level' => 2, 'source' => 'Mental state assessment in the last 72 h'],
        ['dimension' => 'safety', 'indicator' => 'High fall risk', 'level' => 2, 'source' => 'Fall risk on the patient record, or Morse 45+ or Humpty Dumpty 12+ in the last 72 h'],
        ['dimension' => 'skin', 'indicator' => 'Braden 12 or below', 'level' => 3, 'source' => 'Braden in the last 72 h'],
        ['dimension' => 'skin', 'indicator' => 'Braden 13 to 14', 'level' => 2, 'source' => 'Braden in the last 72 h'],
        ['dimension' => 'treatment', 'indicator' => 'Blood transfusion running', 'level' => 3, 'source' => 'Blood transfusion record'],
        ['dimension' => 'treatment', 'indicator' => 'Infusion running', 'level' => 2, 'source' => 'Infusion pumps bound to the patient, or infusion records'],
        ['dimension' => 'treatment', 'indicator' => 'Open STAT consultant order', 'level' => 2, 'source' => 'Consultant orders'],
        ['dimension' => 'infection', 'indicator' => 'Isolation precautions', 'level' => 2, 'source' => 'Isolation on the patient record'],
        ['dimension' => 'flow', 'indicator' => 'Admitted in the last 24 h', 'level' => 2, 'source' => 'Admission time'],
        ['dimension' => 'setting', 'indicator' => 'Critical care ward (lowest level for every patient)', 'level' => 3, 'source' => 'Ward type'],
        ['dimension' => 'complexity', 'indicator' => 'Three or more areas of care at the top level suggested (raises Moderate or Maximum by one)', 'level' => null, 'source' => 'The indicators above'],
    ];

    /** Areas of care at the same level that raise the suggestion by one. */
    public const COMPLEX_AREAS = 3;

    /** How far back a reading counts: vital signs and consciousness change fast, risk screens are done daily or less. */
    public const RECENT_HOURS = 24;

    public const SCREEN_HOURS = 72;

    private const BREATHING_LEVELS = [
        'ventilator' => 4,
        'bipap' => 3,
        'cpap' => 3,
        'hfnc' => 3,
        'high_flow_mask' => 3,
        'non_rebreather' => 3,
        'nasal_cannula' => 2,
        'simple_mask' => 2,
        'venturi_mask' => 2,
        'tracheostomy' => 2,
        'other' => 2,
    ];

    private const SCALES = ['GCS', 'AVPU', '4AT', 'CSSRS', 'BRADEN', 'MORSE', 'HUMPTY'];

    /**
     * The acuity of every bed on the ward that needs a nurse, in bed order.
     *
     * @return Collection<int, array> keyed by bed id; see fromBeds()
     */
    public static function forWard(Ward $ward, ?string $ewsSystem = null): Collection
    {
        ['beds' => $beds, 'patients' => $patients] = WorkloadCalculator::wardBeds($ward);

        return self::fromBeds($ward, $beds->map(fn (Bed $bed) => ['bed' => $bed, 'patient' => $patients->get($bed->bed_number)]), $ewsSystem);
    }

    /**
     * The acuity of beds already loaded, such as WorkloadCalculator rows. Each row:
     *   bed, patient (null for an empty bed),
     *   suggested (1-4, null for an empty bed), classified (the nurse's level, null when unclassified),
     *   level (classified, else suggested: what staffing counts),
     *   status: empty | unclassified | agrees | review_up (indicators suggest more) | review_down (rated above the indicators),
     *   reasons [{dimension, label, level}] highest first, notes [string],
     *   last_rating (?PatientAcuityRating).
     *
     * The early warning score system is the ward's (AcuitySettings) unless one
     * is given, never the viewer's own: the suggestions, the ratings checked
     * against them and the minimum staff set from them stay on one scale.
     *
     * @param  iterable<int, array{bed: Bed, patient: ?Patient}>  $rows  keyed by bed id
     */
    public static function fromBeds(Ward $ward, iterable $rows, ?string $ewsSystem = null): Collection
    {
        $rows = collect($rows);
        $patients = $rows->pluck('patient')->filter()->values();
        $signals = self::signals($patients, $ewsSystem ?? AcuitySettings::forWard($ward)->ewsSystem());
        $criticalCare = (bool) $ward->wardType?->is_critical_care;

        return $rows->map(fn (array $row) => self::row($row['bed'], $row['patient'], $signals, $criticalCare));
    }

    /**
     * The ward picture: patients by level, how many are unclassified, how many
     * ratings need a review, and how many patients were rated in the last 24 h.
     */
    public static function summary(Collection $rows): array
    {
        $occupied = $rows->filter(fn (array $row) => $row['patient']);
        $byLevel = array_fill_keys(array_keys(self::LEVELS), 0);
        foreach ($occupied as $row) {
            $byLevel[$row['level']]++;
        }

        return [
            'patients' => $occupied->count(),
            'beds' => $rows->count(),
            'by_level' => $byLevel,
            'unclassified' => $occupied->where('status', 'unclassified')->count(),
            'review_up' => $occupied->where('status', 'review_up')->count(),
            'review_down' => $occupied->where('status', 'review_down')->count(),
            'agrees' => $occupied->where('status', 'agrees')->count(),
            'rated_recently' => $occupied->filter(fn (array $row) => $row['last_rating']?->rated_at?->gt(now()->subHours(self::RECENT_HOURS)))->count(),
            'high' => $byLevel[3] + $byLevel[4],
        ];
    }

    /** "level_3" => 3; anything else (none, blank) => null. */
    public static function levelFrom(?string $nursingLevel): ?int
    {
        return preg_match('/^level_([1-4])$/', (string) $nursingLevel, $match) ? (int) $match[1] : null;
    }

    public static function nursingLevel(int $level): string
    {
        return 'level_' . $level;
    }

    public static function name(?int $level): string
    {
        return $level ? self::LEVELS[$level]['name'] : 'Not classified';
    }

    private static function row(Bed $bed, ?Patient $patient, array $signals, bool $criticalCare): array
    {
        if (!$patient) {
            return [
                'bed' => $bed,
                'patient' => null,
                'suggested' => null,
                'classified' => null,
                'level' => null,
                'status' => 'empty',
                'reasons' => [],
                'notes' => [],
                'last_rating' => null,
            ];
        }

        $id = $patient->id;
        $reasons = [];
        $add = function (string $dimension, string $label, int $level) use (&$reasons) {
            $reasons[] = ['dimension' => $dimension, 'label' => $label, 'level' => $level];
        };

        // Physiological stability: the early warning score of the newest vital signs (the reading
        // the bed box scores), or of the newest full set when that scores higher (see signals())
        $ews = $signals['ews'][$id] ?? null;
        if ($ews !== null && $ews >= 3) {
            $add('stability', 'EWS ' . $ews, $ews >= 7 ? 4 : ($ews >= 5 ? 3 : 2));
        }

        // Breathing support: the oxygen the patient is on now
        $device = $signals['oxygen'][$id] ?? null;
        if ($device && isset(self::BREATHING_LEVELS[$device])) {
            $add('breathing', VitalSign::OXYGEN_DELIVERY_OPTIONS[$device] ?? ucfirst(str_replace('_', ' ', $device)), self::BREATHING_LEVELS[$device]);
        }

        // Consciousness and cognition
        $scores = $signals['scores'][$id] ?? [];
        $recent = fn (string $code, int $hours) => isset($scores[$code]) && $scores[$code]['at']->gt(now()->subHours($hours)) ? $scores[$code]['score'] : null;
        $gcs = $recent('GCS', self::RECENT_HOURS);
        $avpu = $recent('AVPU', self::RECENT_HOURS);
        $fourAt = $recent('4AT', self::SCREEN_HOURS);
        if ($gcs !== null && $gcs <= 14) {
            $add('neuro', 'GCS ' . $gcs, $gcs <= 8 ? 4 : ($gcs <= 12 ? 3 : 2));
        }
        if ($avpu !== null && $avpu >= 1) {
            $add('neuro', $avpu >= 2 ? ($avpu >= 3 ? 'AVPU unresponsive' : 'AVPU responds to pain') : 'AVPU responds to voice', $avpu >= 2 ? 4 : 3);
        }
        if ($fourAt !== null && $fourAt >= 1) {
            $add('neuro', '4AT ' . $fourAt . ($fourAt >= 4 ? ' (possible delirium)' : ''), $fourAt >= 4 ? 3 : 2);
        }

        // Safety and mental state
        $cssrs = $recent('CSSRS', self::SCREEN_HOURS);
        if ($cssrs !== null && $cssrs >= 1) {
            $add('safety', 'C-SSRS ' . ($cssrs >= 3 ? 'high' : ($cssrs >= 2 ? 'moderate' : 'low')) . ' risk', min(4, $cssrs + 1));
        }
        $morse = $recent('MORSE', self::SCREEN_HOURS);
        $humpty = $recent('HUMPTY', self::SCREEN_HOURS);
        // The ADT feed sends an active fall risk alert as 1
        if (in_array((string) $patient->fall_risk, ['high', 'alert_active', '1'], true)
            || ($morse !== null && $morse >= 45) || ($humpty !== null && $humpty >= 12)) {
            $add('safety', 'High fall risk', 2);
        }

        // Skin and mobility
        $braden = $recent('BRADEN', self::SCREEN_HOURS);
        if ($braden !== null && $braden <= 14) {
            $add('skin', 'Braden ' . $braden, $braden <= 12 ? 3 : 2);
        }

        // Treatments
        if (isset($signals['transfusion'][$id])) {
            $add('treatment', 'Blood transfusion running', 3);
        }
        if (isset($signals['infusion'][$id])) {
            $add('treatment', 'Infusion running', 2);
        }
        if (isset($signals['stat'][$id])) {
            $add('treatment', 'STAT order', 2);
        }

        if (!in_array($patient->isolation_type, [null, '', 'none'], true)) {
            $add('infection', 'Isolation', 2);
        }
        if ($patient->admitted_at && $patient->admitted_at->gt(now()->subHours(self::RECENT_HOURS))) {
            $add('flow', 'New admission', 2);
        }

        // The suggestion: the highest level any indicator asks for, one more when
        // several areas of care ask for it at once
        $byArea = collect($reasons)->groupBy('dimension')->map(fn (Collection $items) => $items->max('level'));
        $suggested = max(1, (int) $byArea->max());
        $areas = $byArea->filter(fn (int $level) => $level === $suggested)->count();
        if (in_array($suggested, [2, 3], true) && $areas >= self::COMPLEX_AREAS) {
            $suggested++;
            $add('complexity', $areas . ' areas of care at once', $suggested);
        }
        if ($criticalCare && $suggested < 3) {
            $suggested = 3;
            $add('setting', 'Critical care ward', 3);
        }

        usort($reasons, fn (array $a, array $b) => $b['level'] <=> $a['level']);

        $classified = self::levelFrom($patient->nursing_level);

        return [
            'bed' => $bed,
            'patient' => $patient,
            'suggested' => $suggested,
            'classified' => $classified,
            'level' => $classified ?? $suggested,
            'status' => match (true) {
                $classified === null => 'unclassified',
                $suggested > $classified => 'review_up',
                $suggested < $classified => 'review_down',
                default => 'agrees',
            },
            'reasons' => $reasons,
            'notes' => $patient->isPendingDischarge() ? ['Discharge planned'] : [],
            'last_rating' => $signals['ratings'][$id] ?? null,
        ];
    }

    /**
     * Everything the indicators read, for all the patients at once: a handful
     * of queries for the ward rather than a handful per bed.
     */
    private static function signals(Collection $patients, string $ewsSystem): array
    {
        $signals = ['ews' => [], 'oxygen' => [], 'scores' => [], 'transfusion' => [], 'infusion' => [], 'stat' => [], 'ratings' => []];
        if ($patients->isEmpty()) {
            return $signals;
        }

        $ids = $patients->pluck('id');
        $admitted = $patients->mapWithKeys(fn (Patient $patient) => [$patient->id => $patient->admitted_at]);
        $thisAdmission = fn (int $patientId, $at) => $at && (!$admitted[$patientId] || $at->gte($admitted[$patientId]));

        // Early warning score from the vital signs in the last 24 h. The newest reading is what
        // the bed box and the workload score; a newer spot reading may lack what a full set has,
        // so the newest full set counts too, and the higher of the two stands. A newer partial
        // reading can raise the score but never hide a rise the full set shows.
        $dashboard = app(WardDashboardController::class);
        VitalSign::whereIn('patient_id', $ids)
            ->where('recorded_at', '>=', now()->subHours(self::RECENT_HOURS))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('patient_id')
            ->each(function (Collection $readings, $patientId) use (&$signals, $dashboard, $ewsSystem, $thisAdmission) {
                $readings = $readings->filter(fn (VitalSign $vitals) => $thisAdmission((int) $patientId, $vitals->recorded_at));
                $scores = collect([$readings->first(), $readings->first(fn (VitalSign $vitals) => $vitals->is_full_reading)])
                    ->filter()
                    ->unique('id')
                    ->map(fn (VitalSign $vitals) => $dashboard->calculateEWS($vitals, $ewsSystem)['score'] ?? null)
                    ->filter(fn ($score) => $score !== null);
                if ($scores->isNotEmpty()) {
                    $signals['ews'][$patientId] = (int) $scores->max();
                }
            });

        // The oxygen on now, as the Oxygen Therapy tab has it: the newer of the last oxygen
        // therapy setting (not voided) and the last oxygen recorded with the vital signs, both
        // this admission and with no other time limit. A setting timed with a reading wins.
        $latestChange = self::latestThisAdmission(
            OxygenTherapyChange::query()->whereNull('oxygen_therapy_changes.voided_at'), 'started_at', $ids
        );
        $latestReading = self::latestThisAdmission(
            VitalSign::query()->whereNotNull('vital_signs.oxygen_delivery'), 'recorded_at', $ids
        );
        foreach ($ids as $id) {
            $change = $latestChange->get($id);
            $reading = $latestReading->get($id);
            $device = match (true) {
                $change && $reading => $reading->recorded_at->gt($change->started_at) ? $reading->oxygen_delivery : $change->oxygen_delivery,
                (bool) $change => $change->oxygen_delivery,
                (bool) $reading => $reading->oxygen_delivery,
                default => null,
            };
            if ($device && $device !== 'room_air') {
                $signals['oxygen'][$id] = $device;
            }
        }

        // The latest score on each scale, this admission
        ClinicalIndicatorScore::query()
            ->join('clinical_indicators', 'clinical_indicators.id', '=', 'clinical_indicator_scores.clinical_indicator_id')
            ->whereIn('clinical_indicator_scores.patient_id', $ids)
            ->whereIn('clinical_indicators.code', self::SCALES)
            ->whereNotNull('clinical_indicator_scores.score')
            ->where('clinical_indicator_scores.recorded_at', '>=', now()->subHours(self::SCREEN_HOURS))
            ->orderByDesc('clinical_indicator_scores.recorded_at')
            ->orderByDesc('clinical_indicator_scores.id')
            ->get(['clinical_indicator_scores.patient_id', 'clinical_indicator_scores.score', 'clinical_indicator_scores.recorded_at', 'clinical_indicators.code'])
            ->each(function ($record) use (&$signals, $thisAdmission) {
                $code = strtoupper((string) $record->code);
                $at = $record->recorded_at instanceof Carbon ? $record->recorded_at : Carbon::parse($record->recorded_at);
                if (!isset($signals['scores'][$record->patient_id][$code]) && $thisAdmission((int) $record->patient_id, $at)) {
                    $signals['scores'][$record->patient_id][$code] = ['score' => (int) $record->score, 'at' => $at];
                }
            });

        $signals['transfusion'] = BloodTransfusion::whereIn('patient_id', $ids)
            ->where('status', BloodTransfusion::STATUS_IN_PROGRESS)
            ->pluck('patient_id')->flip()->all();
        // Live from the infusion engine when it is in use: the local table is then only snapshots
        $signals['infusion'] = ActiveInfusions::patientIds($ids);
        $signals['stat'] = ConsultantOrder::whereIn('patient_id', $ids)->open()
            ->where('urgency', 'stat')->pluck('patient_id')->flip()->all();

        // Each patient's latest rating only: the history grows with every shift
        $signals['ratings'] = PatientAcuityRating::whereIn('id', PatientAcuityRating::selectRaw('MAX(id)')
                ->whereIn('patient_id', $ids)
                ->groupBy('patient_id'))
            ->get()
            ->keyBy('patient_id')
            ->all();

        return $signals;
    }

    /**
     * Each patient's newest oxygen row of a table this admission (at or after
     * admitted_at; any time when the admission time is unknown), found in the
     * database so a long stay's history is never loaded. Rows timed the same
     * moment go to the higher id. Keyed by patient id.
     *
     * @param  Builder  $query  the table's rows that count (not voided, with oxygen)
     */
    private static function latestThisAdmission(Builder $query, string $at, Collection $ids): Collection
    {
        $table = $query->getModel()->getTable();
        $column = $table . '.' . $at;

        $latest = (clone $query)
            ->join('patients', 'patients.id', '=', $table . '.patient_id')
            ->whereIn($table . '.patient_id', $ids)
            ->where(fn ($where) => $where->whereNull('patients.admitted_at')->orWhereColumn($column, '>=', 'patients.admitted_at'))
            ->groupBy($table . '.patient_id')
            ->select($table . '.patient_id')
            ->selectRaw('MAX(' . $column . ') as latest_at');

        return $query
            ->joinSub($latest, 'latest', fn ($join) => $join->on('latest.patient_id', '=', $table . '.patient_id')->on('latest.latest_at', '=', $column))
            ->orderByDesc($table . '.id')
            ->get([$table . '.patient_id', $table . '.oxygen_delivery', $column])
            ->unique('patient_id')
            ->keyBy('patient_id');
    }
}
