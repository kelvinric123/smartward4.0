<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\WardType;
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

        return view('wards.critical-care-dashboard', $this->dashboardViewData($request, $wards, true) + [
            // Named in the empty state, so it says which ward type a ward needs to show up here
            'criticalCareTypes' => $wards->isEmpty()
                ? WardType::active()->criticalCare()->orderBy('sort_order')->orderBy('name')->pluck('name')->unique()->values()
                : collect(),
        ]);
    }
}
