<?php

namespace App\Http\Controllers;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\DietType;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\SugarReading;
use App\Models\VitalSign;
use App\Models\Ward;
use App\Models\WardDashboardSetting;
use App\Services\CommandCenterLive;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CommandCenterController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->isSuperadmin()
            && !$user->hasRole(\App\Models\User::ROLE_HOSPITAL_ADMIN)
            && !$user->hasRole(\App\Models\User::ROLE_IT_ADMIN)) {
            abort(403);
        }

        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();
        $selectedWardId = $request->input('ward_id');
        $selectedWard = $selectedWardId ? Ward::find($selectedWardId) : null;

        // Date range filter (defaults to current month)
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->startOfMonth();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();
        if ($endDate->lt($startDate)) {
            $endDate = (clone $startDate)->endOfDay();
        }

        // Monthly trend year + optional comparison year
        $year = (int) $request->input('year', now()->year);
        $compareYear = $request->filled('compare_year') ? (int) $request->input('compare_year') : null;

        // ---------- Bed snapshot (current state, scoped to ward) ----------
        $bedQuery = Bed::query()->where('is_active', true);
        if ($selectedWard) {
            $bedQuery->where('ward_id', $selectedWard->id);
        }
        $bedCounts = (clone $bedQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalBeds = array_sum($bedCounts);
        $occupied = $bedCounts['occupied'] ?? 0;
        $available = $bedCounts['available'] ?? 0;
        $reserved = $bedCounts['reserved'] ?? 0;
        $maintenance = $bedCounts['maintenance'] ?? 0;
        $occupancyRate = $totalBeds > 0 ? round(($occupied / $totalBeds) * 100, 1) : 0;

        // ---------- Patient snapshot ----------
        $patientQuery = Patient::query()->where('is_active', true);
        if ($selectedWard) {
            $patientQuery->where('ward_id', $selectedWard->id);
        }
        $admittedPatients = (clone $patientQuery)->where('status', Patient::STATUS_ADMITTED)->count();
        $pendingDischarge = (clone $patientQuery)->where('status', Patient::STATUS_PENDING_DISCHARGE)->count();
        $prebooked = (clone $patientQuery)
            ->whereIn('status', [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING])
            ->count();

        // ---------- Current inpatient roster (drives demographics / risk / diet snapshots) ----------
        // Fetched hospital-wide so the per-ward safety matrix always covers every ward,
        // then filtered in PHP for ward-scoped snapshot statistics.
        $inpatientsAll = Patient::query()
            ->where('is_active', true)
            ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
            ->get([
                'id', 'ward_id', 'gender', 'age', 'patient_class', 'nursing_level',
                'fall_risk', 'isolation_type', 'allergies', 'diet_types',
                'hgt_enabled', 'hgt_frequency', 'admitted_at',
            ]);
        $inpatients = $selectedWard
            ? $inpatientsAll->where('ward_id', $selectedWard->id)->values()
            : $inpatientsAll;

        // ---------- Period aggregates ----------
        $admissionsPeriod = AdmissionLog::query()
            ->where('action', 'admit')
            ->whereBetween('admitted_at', [$startDate, $endDate])
            ->when($selectedWard, fn($q) => $q->where('ward_id', $selectedWard->id))
            ->count();

        $dischargesPeriod = Patient::query()
            ->whereBetween('discharged_at', [$startDate, $endDate])
            ->when($selectedWard, fn($q) => $q->where('ward_id', $selectedWard->id))
            ->count();

        $vitalSignsPeriod = VitalSign::query()
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->when($selectedWard, fn($q) => $q->whereHas('patient', fn($p) => $p->where('ward_id', $selectedWard->id)))
            ->count();

        // ---------- Per-ward breakdown (current state) ----------
        // Period flow counted per ward in one query each, not two queries per ward
        $admissionsByWard = AdmissionLog::query()
            ->where('action', 'admit')
            ->whereBetween('admitted_at', [$startDate, $endDate])
            ->selectRaw('ward_id, COUNT(*) as total')
            ->groupBy('ward_id')
            ->pluck('total', 'ward_id');
        $dischargesByWard = Patient::query()
            ->whereBetween('discharged_at', [$startDate, $endDate])
            ->selectRaw('ward_id, COUNT(*) as total')
            ->groupBy('ward_id')
            ->pluck('total', 'ward_id');

        $wardBreakdown = Ward::where('is_active', true)
            ->with(['beds' => fn($q) => $q->where('is_active', true)])
            ->get()
            ->map(function (Ward $ward) use ($admissionsByWard, $dischargesByWard) {
                $beds = $ward->beds;
                $total = $beds->count();
                $occ = $beds->where('status', 'occupied')->count();
                $admissions = (int) ($admissionsByWard[$ward->id] ?? 0);
                $discharges = (int) ($dischargesByWard[$ward->id] ?? 0);
                return [
                    'ward_name' => $ward->ward_name,
                    'ward_code' => $ward->ward_code,
                    'total' => $total,
                    'occupied' => $occ,
                    'available' => $beds->where('status', 'available')->count(),
                    'reserved' => $beds->where('status', 'reserved')->count(),
                    'maintenance' => $beds->where('status', 'maintenance')->count(),
                    'occupancy_rate' => $total > 0 ? round($occ / $total * 100, 1) : 0,
                    'admissions' => $admissions,
                    'discharges' => $discharges,
                ];
            })
            ->values();

        // ---------- Monthly trend data ----------
        $monthly = $this->monthlyStatistics($year, $selectedWard?->id);
        $compareMonthly = $compareYear ? $this->monthlyStatistics($compareYear, $selectedWard?->id) : null;

        // ---------- Vital signs: per-patient/per-day & per-patient/per-admission/per-day ----------
        $vitalsRates = $this->vitalsDailyRates($startDate, $endDate, $selectedWard?->id);

        // ---------- New analytics blocks ----------
        $demographics = $this->demographics($inpatients, $startDate, $endDate, $selectedWard?->id);
        $censusTrend = $this->censusTrend($startDate, $endDate, $selectedWard?->id);
        $vitalStats = $this->vitalStatistics($startDate, $endDate, $selectedWard?->id);
        $riskStats = $this->riskStatistics($inpatients);
        $dietStats = $this->dietStatistics($inpatients, $startDate, $endDate, $selectedWard?->id);
        $wardSafety = $this->wardSafetyMatrix($inpatientsAll, $wards);

        // ---------- Live board: what needs attention right now (the date range does not apply) ----------
        $live = CommandCenterLive::build(
            $selectedWard ? Ward::whereKey($selectedWard->id)->get() : $wards,
            $this->ewsSystem($user->id)
        );

        $hospital = Hospital::first();

        return view('command-center.index', [
            'wards' => $wards,
            'selectedWard' => $selectedWard,
            'selectedWardId' => $selectedWardId,
            'hospital' => $hospital,
            'year' => $year,
            'compareYear' => $compareYear,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'availableYears' => $this->availableYears(),
            'stats' => [
                'total_beds' => $totalBeds,
                'occupied' => $occupied,
                'available' => $available,
                'reserved' => $reserved,
                'maintenance' => $maintenance,
                'occupancy_rate' => $occupancyRate,
                'admitted_patients' => $admittedPatients,
                'pending_discharge' => $pendingDischarge,
                'prebooked' => $prebooked,
                'admissions_period' => $admissionsPeriod,
                'discharges_period' => $dischargesPeriod,
                'vital_signs_period' => $vitalSignsPeriod,
                'total_wards' => $wards->count(),
            ],
            'wardBreakdown' => $wardBreakdown,
            'monthly' => $monthly,
            'compareMonthly' => $compareMonthly,
            'vitalsRates' => $vitalsRates,
            'demographics' => $demographics,
            'censusTrend' => $censusTrend,
            'vitalStats' => $vitalStats,
            'riskStats' => $riskStats,
            'dietStats' => $dietStats,
            'wardSafety' => $wardSafety,
            'live' => $live,
        ]);
    }

    /** The EWS the viewer's ward dashboard scores with, so both agree. */
    private function ewsSystem(int $userId): string
    {
        $settings = WardDashboardSetting::where('user_id', $userId)->first();
        $clinical = $settings && is_array($settings->clinical_settings) ? $settings->clinical_settings : [];

        return $clinical['ews_system'] ?? 'ews_ihh';
    }

    /**
     * Demographics of the current inpatient roster + length-of-stay figures.
     */
    private function demographics(Collection $inpatients, Carbon $start, Carbon $end, ?int $wardId): array
    {
        // Gender split
        $gender = $inpatients
            ->groupBy(fn($p) => $p->gender ? ucfirst(strtolower($p->gender)) : 'Unknown')
            ->map->count()
            ->sortDesc();

        // Age bands
        $bands = [
            '0-17' => 0, '18-34' => 0, '35-49' => 0,
            '50-64' => 0, '65-79' => 0, '80+' => 0, 'Unknown' => 0,
        ];
        foreach ($inpatients as $p) {
            $age = $p->age;
            if ($age === null) {
                $bands['Unknown']++;
            } elseif ($age < 18) {
                $bands['0-17']++;
            } elseif ($age < 35) {
                $bands['18-34']++;
            } elseif ($age < 50) {
                $bands['35-49']++;
            } elseif ($age < 65) {
                $bands['50-64']++;
            } elseif ($age < 80) {
                $bands['65-79']++;
            } else {
                $bands['80+']++;
            }
        }
        if ($bands['Unknown'] === 0) {
            unset($bands['Unknown']);
        }

        // Patient class
        $patientClass = $inpatients
            ->groupBy(fn($p) => $p->patient_class ? strtoupper($p->patient_class) : 'Unspecified')
            ->map->count()
            ->sortDesc();

        // Length of stay — current inpatients
        $losDays = $inpatients
            ->filter(fn($p) => $p->admitted_at !== null)
            ->map(fn($p) => $p->admitted_at->diffInHours(now()) / 24);
        $avgLosCurrent = $losDays->count() > 0 ? round($losDays->avg(), 1) : 0;
        $longestStay = $losDays->count() > 0 ? round($losDays->max(), 1) : 0;

        // Average LOS of patients discharged within the period
        $avgLosDischarged = Patient::query()
            ->whereBetween('discharged_at', [$start, $end])
            ->whereNotNull('admitted_at')
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, admitted_at, discharged_at)) / 24 as los')
            ->value('los');

        $avgAge = $inpatients->whereNotNull('age')->avg('age');

        return [
            'gender' => $gender->toArray(),
            'age_bands' => $bands,
            'patient_class' => $patientClass->toArray(),
            'avg_los_current' => $avgLosCurrent,
            'longest_stay' => $longestStay,
            'avg_los_discharged' => $avgLosDischarged !== null ? round((float) $avgLosDischarged, 1) : null,
            'avg_age' => $avgAge !== null ? round((float) $avgAge, 1) : null,
            'total_inpatients' => $inpatients->count(),
        ];
    }

    /**
     * Daily midnight census (patients in house) across the selected period.
     */
    private function censusTrend(Carbon $start, Carbon $end, ?int $wardId): array
    {
        $patients = Patient::query()
            ->whereNotNull('admitted_at')
            ->where('admitted_at', '<=', $end)
            ->where(fn($q) => $q->whereNull('discharged_at')->orWhere('discharged_at', '>=', $start))
            ->when($wardId, fn($q) => $q->where('ward_id', $wardId))
            ->get(['admitted_at', 'discharged_at']);

        $labels = [];
        $values = [];
        $cursor = (clone $start)->startOfDay();
        $last = (clone $end)->startOfDay();
        $guard = 0;
        while ($cursor->lte($last) && $guard++ < 400) {
            $dayEnd = (clone $cursor)->endOfDay();
            $labels[] = $cursor->format('M j');
            $values[] = $patients
                ->filter(fn($p) => $p->admitted_at->lte($dayEnd)
                    && ($p->discharged_at === null || $p->discharged_at->gte($cursor)))
                ->count();
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'peak' => count($values) > 0 ? max($values) : 0,
            'avg' => count($values) > 0 ? round(array_sum($values) / count($values), 1) : 0,
        ];
    }

    /**
     * Clinical vital-sign analytics for the selected period:
     * averages, abnormal-reading counts, hourly recording pattern and glucose stats.
     */
    private function vitalStatistics(Carbon $start, Carbon $end, ?int $wardId): array
    {
        $scope = fn($q) => $q->whereBetween('recorded_at', [$start, $end])
            ->when($wardId, fn($qq) => $qq->whereHas('patient', fn($p) => $p->where('ward_id', $wardId)));

        $agg = VitalSign::query()->tap($scope)->selectRaw("
            COUNT(*) as total,
            COUNT(DISTINCT patient_id) as patients,
            SUM(reading_type = 'full') as full_readings,
            AVG(systolic_bp) as avg_sys,
            AVG(diastolic_bp) as avg_dia,
            AVG(pulse_rate) as avg_pulse,
            AVG(temperature) as avg_temp,
            AVG(spo2) as avg_spo2,
            AVG(respiratory_rate) as avg_rr,
            SUM(temperature >= 38.0) as fever,
            SUM(temperature < 36.0) as hypothermia,
            SUM(spo2 IS NOT NULL AND spo2 < 95) as low_spo2,
            SUM(pulse_rate > 100) as tachycardia,
            SUM(pulse_rate IS NOT NULL AND pulse_rate < 60) as bradycardia,
            SUM(systolic_bp >= 140 OR diastolic_bp >= 90) as hypertensive,
            SUM(systolic_bp IS NOT NULL AND systolic_bp < 90) as hypotensive,
            SUM(respiratory_rate > 20) as tachypnoea
        ")->first();

        $total = (int) ($agg->total ?? 0);
        $abnormal = [
            'Fever ≥38°C' => ['count' => (int) $agg->fever, 'color' => '#f97316'],
            'Hypothermia <36°C' => ['count' => (int) $agg->hypothermia, 'color' => '#0ea5e9'],
            'Low SpO₂ <95%' => ['count' => (int) $agg->low_spo2, 'color' => '#6366f1'],
            'Tachycardia >100' => ['count' => (int) $agg->tachycardia, 'color' => '#ef4444'],
            'Bradycardia <60' => ['count' => (int) $agg->bradycardia, 'color' => '#8b5cf6'],
            'Hypertensive ≥140/90' => ['count' => (int) $agg->hypertensive, 'color' => '#f43f5e'],
            'Hypotensive <90 sys' => ['count' => (int) $agg->hypotensive, 'color' => '#f59e0b'],
            'Tachypnoea >20' => ['count' => (int) $agg->tachypnoea, 'color' => '#14b8a6'],
        ];
        $abnormalTotal = array_sum(array_column($abnormal, 'count'));

        // Recording activity by hour of day (0-23)
        $byHour = VitalSign::query()->tap($scope)
            ->selectRaw('HOUR(recorded_at) as h, COUNT(*) as c')
            ->groupBy('h')
            ->pluck('c', 'h');
        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $hourly[] = (int) ($byHour[$h] ?? 0);
        }

        // Reading type breakdown
        $readingTypes = VitalSign::query()->tap($scope)
            ->selectRaw("COALESCE(reading_type, 'unspecified') as t, COUNT(*) as c")
            ->groupBy('t')
            ->pluck('c', 't')
            ->map(fn($c) => (int) $c)
            ->toArray();

        $last24h = VitalSign::query()
            ->where('recorded_at', '>=', now()->subDay())
            ->when($wardId, fn($q) => $q->whereHas('patient', fn($p) => $p->where('ward_id', $wardId)))
            ->count();

        return [
            'total' => $total,
            'patients_covered' => (int) ($agg->patients ?? 0),
            'full_readings' => (int) ($agg->full_readings ?? 0),
            'full_pct' => $total > 0 ? round((int) $agg->full_readings / $total * 100, 1) : 0,
            'abnormal_total' => $abnormalTotal,
            'abnormal_pct' => $total > 0 ? round($abnormalTotal / $total * 100, 1) : 0,
            'last_24h' => $last24h,
            'averages' => [
                'bp' => $agg->avg_sys ? round($agg->avg_sys) . '/' . round($agg->avg_dia) : null,
                'pulse' => $agg->avg_pulse ? round($agg->avg_pulse) : null,
                'temp' => $agg->avg_temp ? round($agg->avg_temp, 1) : null,
                'spo2' => $agg->avg_spo2 ? round($agg->avg_spo2, 1) : null,
                'rr' => $agg->avg_rr ? round($agg->avg_rr, 1) : null,
            ],
            'abnormal' => $abnormal,
            'hourly' => $hourly,
            'reading_types' => $readingTypes,
        ];
    }

    /**
     * Fall risk / nursing acuity / isolation / allergy snapshot of the (ward-scoped) inpatient roster.
     */
    private function riskStatistics(Collection $inpatients): array
    {
        $fall = $this->distribution($inpatients, 'fall_risk', [
            'none' => 'None',
            'low' => 'Low',
            'moderate' => 'Moderate',
            'high' => 'High',
            'alert_active' => 'FR Alert Active',
        ]);
        $nursing = $this->distribution($inpatients, 'nursing_level', [
            'none' => 'None',
            'level_1' => 'Level 1',
            'level_2' => 'Level 2',
            'level_3' => 'Level 3',
            'level_4' => 'Level 4',
        ]);
        $isolation = $this->distribution($inpatients, 'isolation_type', [
            'none' => 'None',
            'contact' => 'Contact',
            'droplet' => 'Droplet',
            'airborne' => 'Airborne',
            'protective' => 'Protective',
            'mrsa' => 'MRSA',
            'vre' => 'VRE',
            'cdiff' => 'C.Diff',
            'covid' => 'COVID-19',
            'tb' => 'TB',
        ]);

        $highFall = collect($fall)->whereIn('key', ['high', 'alert_active'])->sum('count');
        $isolated = collect($isolation)->where('key', '!=', 'none')->sum('count');
        $highAcuity = collect($nursing)->whereIn('key', ['level_3', 'level_4'])->sum('count');
        $withAllergies = $inpatients->filter(fn($p) => !empty($p->allergies))->count();
        $hgtMonitored = $inpatients->where('hgt_enabled', true)->count();

        return [
            'fall' => $fall,
            'nursing' => $nursing,
            'isolation' => $isolation,
            'high_fall' => $highFall,
            'isolated' => $isolated,
            'high_acuity' => $highAcuity,
            'with_allergies' => $withAllergies,
            'hgt_monitored' => $hgtMonitored,
            'total_inpatients' => $inpatients->count(),
        ];
    }

    /**
     * Diet order + glucose monitoring snapshot of the (ward-scoped) inpatient roster.
     */
    private function dietStatistics(Collection $inpatients, Carbon $start, Carbon $end, ?int $wardId): array
    {
        $dietNames = DietType::pluck('name', 'code')->toArray();

        $counts = [];
        $withDiet = 0;
        foreach ($inpatients as $p) {
            $codes = collect((array) $p->diet_types)
                ->map(fn($c) => strtoupper(trim((string) $c)))
                ->filter()
                ->unique();
            if ($codes->isNotEmpty()) {
                $withDiet++;
            }
            foreach ($codes as $code) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }
        arsort($counts);

        $distribution = [];
        foreach ($counts as $code => $count) {
            $distribution[] = [
                'code' => $code,
                'label' => $dietNames[$code] ?? ucwords(strtolower(str_replace('_', ' ', $code))),
                'count' => $count,
            ];
        }

        $regularCodes = ['RD', 'REGULAR'];
        $nbm = collect($distribution)->whereIn('code', ['NBM', 'NPO'])->sum('count');
        $diabetic = collect($distribution)->whereIn('code', ['DMD', 'DIABETIC'])->sum('count');
        $therapeutic = $inpatients->filter(function ($p) use ($regularCodes) {
            $codes = collect((array) $p->diet_types)->map(fn($c) => strtoupper(trim((string) $c)))->filter();
            return $codes->isNotEmpty() && $codes->diff($regularCodes)->isNotEmpty();
        })->count();

        // HGT (glucose) monitoring
        $hgtEnabled = $inpatients->where('hgt_enabled', true);
        $hgtFrequency = $hgtEnabled
            ->groupBy(fn($p) => strtolower((string) ($p->hgt_frequency ?: 'unspecified')))
            ->map->count()
            ->mapWithKeys(fn($c, $f) => [SugarReading::getFrequencyLabel($f) => $c])
            ->toArray();

        // Sugar readings within the period
        $sugar = SugarReading::query()
            ->whereBetween('recorded_at', [$start, $end])
            ->when($wardId, fn($q) => $q->whereHas('patient', fn($p) => $p->where('ward_id', $wardId)))
            ->selectRaw("
                COUNT(*) as total,
                AVG(value) as avg_val,
                MIN(value) as min_val,
                MAX(value) as max_val,
                SUM(value < 4.0) as low,
                SUM(value >= 4.0 AND value <= 7.0) as normal,
                SUM(value > 7.0 AND value <= 11.0) as elevated,
                SUM(value > 11.0) as high
            ")->first();

        return [
            'distribution' => $distribution,
            'with_diet' => $withDiet,
            'no_diet' => max(0, $inpatients->count() - $withDiet),
            'nbm' => $nbm,
            'diabetic' => $diabetic,
            'therapeutic' => $therapeutic,
            'hgt_enabled' => $hgtEnabled->count(),
            'hgt_frequency' => $hgtFrequency,
            'sugar' => [
                'total' => (int) ($sugar->total ?? 0),
                'avg' => $sugar->avg_val !== null ? round((float) $sugar->avg_val, 1) : null,
                'min' => $sugar->min_val !== null ? (float) $sugar->min_val : null,
                'max' => $sugar->max_val !== null ? (float) $sugar->max_val : null,
                'low' => (int) ($sugar->low ?? 0),
                'normal' => (int) ($sugar->normal ?? 0),
                'elevated' => (int) ($sugar->elevated ?? 0),
                'high' => (int) ($sugar->high ?? 0),
            ],
            'total_inpatients' => $inpatients->count(),
        ];
    }

    /**
     * Per-ward safety matrix computed from the full (unfiltered) inpatient roster.
     */
    private function wardSafetyMatrix(Collection $inpatientsAll, Collection $wards): array
    {
        $byWard = $inpatientsAll->groupBy('ward_id');

        return $wards->map(function (Ward $ward) use ($byWard) {
            $pts = $byWard->get($ward->id, collect());
            return [
                'ward_name' => $ward->ward_name,
                'ward_code' => $ward->ward_code,
                'inpatients' => $pts->count(),
                'high_fall' => $pts->filter(fn($p) => in_array(strtolower((string) $p->fall_risk), ['high', 'alert_active']))->count(),
                'isolated' => $pts->filter(fn($p) => $p->isolation_type && strtolower($p->isolation_type) !== 'none')->count(),
                'high_acuity' => $pts->filter(fn($p) => in_array(strtolower((string) $p->nursing_level), ['level_3', 'level_4']))->count(),
                'allergies' => $pts->filter(fn($p) => !empty($p->allergies))->count(),
                'nbm' => $pts->filter(function ($p) {
                    return collect((array) $p->diet_types)
                        ->map(fn($c) => strtoupper(trim((string) $c)))
                        ->intersect(['NBM', 'NPO'])
                        ->isNotEmpty();
                })->count(),
                'hgt' => $pts->where('hgt_enabled', true)->count(),
            ];
        })->values()->all();
    }

    /**
     * Ordered {key,label,count} distribution of a patient attribute, with
     * unexpected values appended after the known taxonomy.
     */
    private function distribution(Collection $patients, string $field, array $order): array
    {
        $counts = $patients
            ->groupBy(fn($p) => strtolower((string) ($p->{$field} ?: 'none')))
            ->map->count();

        $rows = [];
        foreach ($order as $key => $label) {
            $rows[] = ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)];
        }
        foreach ($counts as $key => $count) {
            if (!array_key_exists($key, $order)) {
                $rows[] = ['key' => $key, 'label' => ucwords(str_replace('_', ' ', $key)), 'count' => (int) $count];
            }
        }
        return $rows;
    }

    /**
     * Build two daily rate series for the selected period:
     *   - per_patient   = vitals on that day / distinct patients with vitals that day
     *   - per_admission = vitals on that day / distinct (patient, admission_id) pairs that day
     *
     * Both are "per day" averages — they answer "on average how many vital signs
     * did a single patient (or a single admission) get on that day".
     */
    private function vitalsDailyRates(Carbon $start, Carbon $end, ?int $wardId): array
    {
        $base = VitalSign::query()
            ->whereBetween('recorded_at', [$start, $end]);
        if ($wardId) {
            $base->whereHas('patient', fn($q) => $q->where('ward_id', $wardId));
        }

        // total vitals per day
        $vitalsByDay = (clone $base)
            ->selectRaw('DATE(recorded_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        // distinct patients per day
        $patientsByDay = (clone $base)
            ->selectRaw('DATE(recorded_at) as day, COUNT(DISTINCT patient_id) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        // distinct (patient, admission_id) pairs per day — falls back to patient_id when admission_id is null
        $admissionsByDay = (clone $base)
            ->selectRaw("DATE(recorded_at) as day, COUNT(DISTINCT CONCAT(patient_id, '|', COALESCE(admission_id, CONCAT('p', patient_id)))) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $perPatient = [];
        $perAdmission = [];
        $rawVitals = [];
        $rawPatients = [];
        $rawAdmissions = [];

        $cursor = (clone $start)->startOfDay();
        $last = (clone $end)->startOfDay();
        $guard = 0;
        while ($cursor->lte($last) && $guard++ < 400) {
            $key = $cursor->format('Y-m-d');
            $v = (int) ($vitalsByDay[$key] ?? 0);
            $p = (int) ($patientsByDay[$key] ?? 0);
            $a = (int) ($admissionsByDay[$key] ?? 0);

            $labels[] = $cursor->format('M j');
            $perPatient[]   = $p > 0 ? round($v / $p, 2) : 0;
            $perAdmission[] = $a > 0 ? round($v / $a, 2) : 0;
            $rawVitals[]     = $v;
            $rawPatients[]   = $p;
            $rawAdmissions[] = $a;

            $cursor->addDay();
        }

        $totalVitals     = array_sum($rawVitals);
        $totalPatients   = array_sum($rawPatients);   // patient-days
        $totalAdmissions = array_sum($rawAdmissions); // admission-days
        $activeDaysPatient   = count(array_filter($perPatient));
        $activeDaysAdmission = count(array_filter($perAdmission));

        return [
            'labels'         => $labels,
            'per_patient'    => $perPatient,
            'per_admission'  => $perAdmission,
            'raw_vitals'     => $rawVitals,
            'raw_patients'   => $rawPatients,
            'raw_admissions' => $rawAdmissions,
            'avg_per_patient'   => $totalPatients   > 0 ? round($totalVitals / $totalPatients, 2)   : 0,
            'avg_per_admission' => $totalAdmissions > 0 ? round($totalVitals / $totalAdmissions, 2) : 0,
            'total_vitals'   => $totalVitals,
            'days_with_data_patient'   => $activeDaysPatient,
            'days_with_data_admission' => $activeDaysAdmission,
        ];
    }

    private function monthlyStatistics(int $year, ?int $wardId): array
    {
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end = (clone $start)->endOfYear();

        // One grouped query per series rather than one per month
        $perMonth = function ($query, string $column) use ($start, $end): array {
            $counts = $query->whereBetween($column, [$start, $end])
                ->selectRaw("MONTH({$column}) as month, COUNT(*) as total")
                ->groupBy('month')
                ->pluck('total', 'month');

            return array_map(fn (int $month) => (int) ($counts[$month] ?? 0), range(1, 12));
        };

        return [
            'year' => $year,
            'labels' => array_map(fn (int $month) => Carbon::create($year, $month, 1)->format('M'), range(1, 12)),
            'admissions' => $perMonth(
                AdmissionLog::query()->where('action', 'admit')->when($wardId, fn($q) => $q->where('ward_id', $wardId)),
                'admitted_at'
            ),
            'discharges' => $perMonth(
                Patient::query()->when($wardId, fn($q) => $q->where('ward_id', $wardId)),
                'discharged_at'
            ),
            'vital_signs' => $perMonth(
                VitalSign::query()->when($wardId, fn($q) => $q->whereHas('patient', fn($p) => $p->where('ward_id', $wardId))),
                'recorded_at'
            ),
        ];
    }

    private function availableYears(): array
    {
        $currentYear = (int) now()->year;
        $years = [];
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $years[] = $y;
        }
        return $years;
    }
}
