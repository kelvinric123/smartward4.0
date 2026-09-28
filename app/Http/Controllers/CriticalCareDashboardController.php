<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ward;
use App\Models\WardType;
use App\Support\ClinicalIndicatorReadings;
use Illuminate\Http\Request;

/**
 * Critical Care Ward Dashboard: the ward dashboard for the wards whose ward
 * type is marked critical care (Ward Types page). It starts as a copy of the
 * ward dashboard - same data, its own copy of the view - so critical care
 * features can be added here without changing the general ward dashboard.
 */
class CriticalCareDashboardController extends WardDashboardController
{
    public function index(Request $request)
    {
        $wards = Ward::where('is_active', true)->criticalCare()->get();
        $data = $this->dashboardViewData($request, $wards, true);

        $patients = $data['selectedWard']
            ? Patient::where('ward_id', $data['selectedWard']->id)
                ->where('is_active', true)
                ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
                ->get()
            : collect();

        return view('wards.critical-care-dashboard', $data + [
            // Named in the empty state, so it says which ward type a ward needs to show up here
            'criticalCareTypes' => $wards->isEmpty()
                ? WardType::active()->criticalCare()->orderBy('sort_order')->orderBy('name')->pluck('name')->unique()->values()
                : collect(),
            // The ventilator as charted in the last few hours, on each bed card
            'bedsideReadings' => ClinicalIndicatorReadings::recentForPatients($patients),
        ]);
    }
}
