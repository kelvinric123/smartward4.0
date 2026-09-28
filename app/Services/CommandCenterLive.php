<?php

namespace App\Services;

use App\Http\Controllers\WardDashboardController;
use App\Models\BloodTransfusion;
use App\Models\Patient;
use App\Models\PatientMedication;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardNotification;
use App\Support\ClinicalIndicatorMonitoring;
use App\Support\ClinicalIndicatorReadings;
use App\Support\FluidBalanceChart;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The Command Center's live board: what needs attention across the wards
 * right now, taken from the same sources the ward dashboards flag.
 *
 * For each inpatient: the EWS from their latest vital signs, while those are
 * current (see EWS_CURRENT_HOURS), monitor readings
 * at an escalation level in the last few hours (hemodynamics, the
 * ventilator), monitored assessments overdue, medication doses overdue, fluid
 * balance alerts and urgent ward notifications. These roll up per ward beside
 * bed occupancy, and into one list of the patients needing attention, the
 * most urgent first.
 */
final class CommandCenterLive
{
    /** How long the attention list runs before the rest are only counted. */
    public const ATTENTION_LIMIT = 25;

    /**
     * An EWS counts as current while its vital signs are this recent. Every
     * ward takes observations at least daily, so an older score says how the
     * patient was, not how they are: it is shown, marked old, but not counted.
     */
    public const EWS_CURRENT_HOURS = 24;

    public const EWS_OLD = 'old';

    private const ACTIVE = [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE];
    private const INCOMING = [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING];

    /** The ClinicalIndicatorLibrary category of the ventilator scale: charted recently means ventilated. */
    private const VENTILATION = 'Ventilation';

    /** How much each concern weighs when ordering the attention list. */
    private const EWS_WEIGHT = [WardNotification::SEVERITY_URGENT => 50, WardNotification::SEVERITY_WARNING => 20];
    private const FLUID_WEIGHT = ['critical' => 25, 'warning' => 10];

    /**
     * @param  EloquentCollection<int, Ward>  $wards  the wards in view: every active ward, or the one filtered to
     * @param  string  $ewsSystem  the EWS the viewer's ward dashboard scores with
     */
    public static function build(EloquentCollection $wards, string $ewsSystem = 'ews_ihh'): array
    {
        $wards->load(['wardType', 'beds' => fn ($query) => $query->where('is_active', true)]);
        $wardIds = $wards->modelKeys();

        $patients = Patient::whereIn('ward_id', $wardIds)
            ->where('is_active', true)
            ->whereIn('status', array_merge(self::ACTIVE, self::INCOMING))
            ->get();
        $inpatients = $patients->whereIn('status', self::ACTIVE)->values();
        $ids = $inpatients->modelKeys();

        $vitals = self::latestVitals($ids);
        $readings = ClinicalIndicatorReadings::recentForPatients($inpatients, null, true);
        $assessments = ClinicalIndicatorMonitoring::forPatients($inpatients);
        $medications = PatientMedication::alertsForPatients($ids);
        $fluid = FluidBalanceChart::alertsForPatients($inpatients);
        $notifications = WardNotification::pending()
            ->whereIn('ward_id', $wardIds)
            ->get(['id', 'ward_id', 'patient_id', 'severity']);
        $transfusing = BloodTransfusion::whereIn('patient_id', $ids)
            ->where('status', BloodTransfusion::STATUS_IN_PROGRESS)
            ->pluck('patient_id');

        $dashboard = app(WardDashboardController::class);
        $wardsById = $wards->keyBy('id');

        $rows = $inpatients->map(function (Patient $patient) use ($wardsById, $dashboard, $ewsSystem, $vitals, $readings, $assessments, $medications, $fluid, $notifications, $transfusing) {
            $ward = $wardsById[$patient->ward_id];
            $vital = $vitals->get($patient->id);
            $ews = $dashboard->calculateEWS($vital, $ewsSystem);
            $ewsScore = ($ews['has_vitals'] ?? false) ? $ews['score'] : null;
            $ewsSeverity = match (true) {
                $ewsScore === null => null,
                $vital->recorded_at->lt(now()->subHours(self::EWS_CURRENT_HOURS)) => self::EWS_OLD,
                default => WardNotification::getSeverityFromEws($ewsScore),
            };
            $sets = collect($readings[$patient->id] ?? []);

            $escalations = $sets
                ->filter(fn (array $set) => self::grade($set['flag']) === 2)
                ->map(fn (array $set) => [
                    'code' => $set['code'],
                    'indicator_id' => $set['indicator_id'],
                    'readings' => collect($set['readings'])
                        ->filter(fn (array $reading) => self::grade($reading['flag']) === 2)
                        ->map(fn (array $reading) => trim($reading['abbr'] . ' ' . $reading['text'] . ' ' . $reading['unit'] . ' '
                            . ClinicalIndicatorReadings::FLAGS[$reading['flag']]['arrow']))
                        ->implode(' · '),
                ])
                ->values()
                ->all();

            $overdue = collect($assessments[$patient->id]['items'] ?? [])
                ->where('state', ClinicalIndicatorMonitoring::STATE_OVERDUE)
                ->map(fn (array $item) => ['code' => $item['code'], 'indicator_id' => $item['indicator_id'], 'history' => $item['history']])
                ->values()
                ->all();

            $doses = collect($medications[$patient->id]['items'] ?? [])
                ->where('state', 'overdue')
                ->pluck('name')
                ->values()
                ->all();

            $fluidLevel = $fluid[$patient->id]['level'] ?? null;
            $fluidAlert = isset(self::FLUID_WEIGHT[$fluidLevel ?? ''])
                ? ['level' => $fluidLevel, 'title' => $fluid[$patient->id]['alerts'][0]['title'] ?? 'Fluid balance alert']
                : null;

            $patientAlerts = $notifications->where('patient_id', $patient->id);
            $urgentAlerts = $patientAlerts->where('severity', WardNotification::SEVERITY_URGENT)->count();

            return [
                'id' => $patient->id,
                'name' => $patient->name,
                'mrn' => $patient->mrn,
                'ward_id' => $ward->id,
                'ward' => $ward->ward_name,
                'bed' => $patient->bed_number,
                'critical_care' => (bool) $ward->wardType?->is_critical_care,
                'pending_discharge' => $patient->status === Patient::STATUS_PENDING_DISCHARGE,
                'ews' => $ewsScore === null ? null : ['score' => $ewsScore, 'severity' => $ewsSeverity, 'at' => $vital->recorded_at],
                'escalations' => $escalations,
                'ventilated' => $sets->contains(fn (array $set) => $set['category'] === self::VENTILATION),
                'assessments_overdue' => $overdue,
                'doses_overdue' => $doses,
                'fluid' => $fluidAlert,
                'alerts' => $patientAlerts->count(),
                'alerts_urgent' => $urgentAlerts,
                'transfusing' => $transfusing->contains($patient->id),
                'rank' => (self::EWS_WEIGHT[$ewsSeverity ?? ''] ?? 0)
                    + 40 * count($escalations)
                    + 15 * count($overdue)
                    + 15 * min(3, count($doses))
                    + (self::FLUID_WEIGHT[$fluidLevel ?? ''] ?? 0)
                    + 10 * $urgentAlerts,
            ];
        });

        $wardRows = $wards->map(fn (Ward $ward) => self::wardRow($ward, $rows, $patients, $notifications))->values();
        $criticalCare = $wardRows->where('critical_care', true);

        // Most urgent first; among equals the worse EWS, then by ward and bed
        $attention = $rows
            ->filter(fn (array $row) => $row['rank'] > 0)
            ->sort(fn (array $a, array $b) => [$b['rank'], $b['ews']['score'] ?? -1, $a['ward'], $a['bed']]
                <=> [$a['rank'], $a['ews']['score'] ?? -1, $b['ward'], $b['bed']])
            ->values();

        return [
            'stats' => [
                'inpatients' => $rows->count(),
                'pending_discharge' => $rows->where('pending_discharge', true)->count(),
                'incoming' => $patients->whereIn('status', self::INCOMING)->count(),
                'cc_wards' => $criticalCare->count(),
                'cc_beds' => $criticalCare->sum('beds'),
                'cc_occupied' => $criticalCare->sum('occupied'),
                'ventilated' => $rows->where('ventilated', true)->count(),
                'ews_urgent' => $rows->where('ews.severity', WardNotification::SEVERITY_URGENT)->count(),
                'ews_warning' => $rows->where('ews.severity', WardNotification::SEVERITY_WARNING)->count(),
                'escalations' => $rows->filter(fn (array $row) => $row['escalations'])->count(),
                'overdue_care' => $rows->filter(fn (array $row) => $row['assessments_overdue'] || $row['doses_overdue'])->count(),
                'assessments_overdue' => $rows->sum(fn (array $row) => count($row['assessments_overdue'])),
                'doses_overdue' => $rows->sum(fn (array $row) => count($row['doses_overdue'])),
                'fluid_alerts' => $rows->filter(fn (array $row) => $row['fluid'])->count(),
                'transfusing' => $rows->where('transfusing', true)->count(),
                'alerts' => $notifications->count(),
                'alerts_urgent' => $notifications->where('severity', WardNotification::SEVERITY_URGENT)->count(),
            ],
            'attention' => $attention->take(self::ATTENTION_LIMIT)->all(),
            'attention_total' => $attention->count(),
            'wards' => $wardRows->all(),
            'generated_at' => now(),
        ];
    }

    /** One ward's line on the board: beds, flow and how many of its patients have each concern. */
    private static function wardRow(Ward $ward, Collection $rows, Collection $patients, Collection $notifications): array
    {
        $inWard = $rows->where('ward_id', $ward->id);
        $beds = $ward->beds;
        $occupied = $beds->where('status', 'occupied')->count();
        $alerts = $notifications->where('ward_id', $ward->id);
        $criticalCare = (bool) $ward->wardType?->is_critical_care;

        return [
            'id' => $ward->id,
            'name' => $ward->ward_name,
            'code' => $ward->ward_code,
            'type' => $ward->wardType?->name,
            'critical_care' => $criticalCare,
            'beds' => $beds->count(),
            'occupied' => $occupied,
            'occupancy' => $beds->count() > 0 ? (int) round($occupied / $beds->count() * 100) : 0,
            'inpatients' => $inWard->count(),
            'pending_discharge' => $inWard->where('pending_discharge', true)->count(),
            'incoming' => $patients->where('ward_id', $ward->id)->whereIn('status', self::INCOMING)->count(),
            'ews_urgent' => $inWard->where('ews.severity', WardNotification::SEVERITY_URGENT)->count(),
            'ews_warning' => $inWard->where('ews.severity', WardNotification::SEVERITY_WARNING)->count(),
            'escalations' => $inWard->filter(fn (array $row) => $row['escalations'])->count(),
            'ventilated' => $inWard->where('ventilated', true)->count(),
            'overdue_care' => $inWard->filter(fn (array $row) => $row['assessments_overdue'] || $row['doses_overdue'])->count(),
            'fluid_alerts' => $inWard->filter(fn (array $row) => $row['fluid'])->count(),
            'transfusing' => $inWard->where('transfusing', true)->count(),
            'alerts' => $alerts->count(),
            'alerts_urgent' => $alerts->where('severity', WardNotification::SEVERITY_URGENT)->count(),
            'dashboard_url' => $criticalCare
                ? route('critical-care.dashboard', ['ward_id' => $ward->id])
                : route('ward.dashboard', ['ward_id' => $ward->id]),
        ];
    }

    /**
     * Each patient's latest vital signs, in one query. Where two share the
     * latest time, the one saved last.
     *
     * @return Collection<int, VitalSign> keyed by patient id
     */
    private static function latestVitals(array $patientIds): Collection
    {
        if ($patientIds === []) {
            return collect();
        }

        $latest = VitalSign::query()
            ->whereIn('patient_id', $patientIds)
            ->groupBy('patient_id')
            ->selectRaw('patient_id, MAX(recorded_at) as latest_at');

        return VitalSign::query()
            ->joinSub($latest, 'latest', fn ($join) => $join
                ->on('vital_signs.patient_id', '=', 'latest.patient_id')
                ->on('vital_signs.recorded_at', '=', 'latest.latest_at'))
            ->orderBy('vital_signs.id')
            ->get(['vital_signs.*'])
            ->keyBy('patient_id');
    }

    private static function grade(string $flag): int
    {
        return ClinicalIndicatorReadings::FLAGS[$flag]['grade'] ?? 0;
    }
}
