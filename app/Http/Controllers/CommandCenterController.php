<?php

namespace App\Http\Controllers;

use App\Models\AdmissionLog;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\VitalSign;
use App\Models\Ward;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $wardBreakdown = Ward::where('is_active', true)
            ->with(['beds' => fn($q) => $q->where('is_active', true)])
            ->get()
            ->map(function (Ward $ward) use ($startDate, $endDate) {
                $beds = $ward->beds;
                $total = $beds->count();
                $occ = $beds->where('status', 'occupied')->count();
                $admissions = AdmissionLog::where('ward_id', $ward->id)
                    ->where('action', 'admit')
                    ->whereBetween('admitted_at', [$startDate, $endDate])
                    ->count();
                $discharges = Patient::where('ward_id', $ward->id)
                    ->whereBetween('discharged_at', [$startDate, $endDate])
                    ->count();
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
        ]);
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
        $labels = [];
        $admissions = [];
        $discharges = [];
        $vitalSigns = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end = (clone $start)->endOfMonth();
            $labels[] = $start->format('M');

            $admQuery = AdmissionLog::query()
                ->where('action', 'admit')
                ->whereBetween('admitted_at', [$start, $end]);
            if ($wardId) {
                $admQuery->where('ward_id', $wardId);
            }
            $admissions[] = $admQuery->count();

            $disQuery = Patient::query()->whereBetween('discharged_at', [$start, $end]);
            if ($wardId) {
                $disQuery->where('ward_id', $wardId);
            }
            $discharges[] = $disQuery->count();

            $vsQuery = VitalSign::query()->whereBetween('recorded_at', [$start, $end]);
            if ($wardId) {
                $vsQuery->whereHas('patient', fn($q) => $q->where('ward_id', $wardId));
            }
            $vitalSigns[] = $vsQuery->count();
        }

        return [
            'year' => $year,
            'labels' => $labels,
            'admissions' => $admissions,
            'discharges' => $discharges,
            'vital_signs' => $vitalSigns,
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
