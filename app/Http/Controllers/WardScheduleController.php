<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class WardScheduleController extends Controller
{
    public function index(Request $request)
    {
        $wards = Ward::where('is_active', true)
            ->orderBy('ward_name')
            ->get();

        $selectedWardId = $request->input('ward_id', $wards->first()->id ?? null);
        $selectedWard = $selectedWardId
            ? $wards->firstWhere('id', $selectedWardId)
            : null;

        if (!$selectedWard && $wards->isNotEmpty()) {
            $selectedWard = $wards->first();
            $selectedWardId = $selectedWard->id;
        }

        $selectedDate = $request->input('date', now()->toDateString());

        $dateRange = collect(range(-2, 2))
            ->map(fn (int $offset) => Carbon::parse($selectedDate)->addDays($offset));

        $beds = $selectedWardId
            ? Bed::where('ward_id', $selectedWardId)
                ->where('is_active', true)
                ->with('patient')
                ->orderByRaw('CAST(bed_number AS UNSIGNED)') // sort Bed1..Bed22 numerically
                ->orderBy('id') // stable secondary order
                ->get()
            : collect();

        return view('wards.schedule', [
            'wards' => $wards,
            'selectedWard' => $selectedWard,
            'selectedWardId' => $selectedWardId,
            'selectedDate' => $selectedDate,
            'beds' => $beds,
            'shifts' => ['AM', 'PM', 'ON'],
            'dateRange' => $dateRange,
        ]);
    }

    /**
     * Lightweight patient details view for ward schedule (info + additional tabs only).
     */
    public function patientDetailsIframe(Request $request)
    {
        $patientId = $request->input('patient_id');

        $patient = $patientId
            ? Patient::with([
                'ward',
                'consultant',
                'nurse',
                'anaesthetist',
            ])->where('is_active', true)->find($patientId)
            : null;

        return view('wards.patient-details-iframe', [
            'patient' => $patient,
            'activeTab' => 'info',
            'clinicalIndicatorOptions' => $this->getClinicalIndicatorOptions(),
        ]);
    }

    /**
     * Clinical indicator options (subset used by schedule patient details).
     */
    private function getClinicalIndicatorOptions(): array
    {
        return [
            'nursing_level' => [
                ['value' => 'none', 'label' => 'None', 'color' => 'bg-gray-100 text-gray-600'],
                ['value' => 'level_1', 'label' => 'Level 1', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'level_2', 'label' => 'Level 2', 'color' => 'bg-blue-100 text-blue-700'],
                ['value' => 'level_3', 'label' => 'Level 3', 'color' => 'bg-yellow-100 text-yellow-700'],
                ['value' => 'level_4', 'label' => 'Level 4', 'color' => 'bg-red-100 text-red-700'],
            ],
            'diet_type' => [
                ['value' => 'npo', 'label' => 'NPO (Nil By Mouth)', 'color' => 'bg-red-100 text-red-700'],
                ['value' => 'clear_fluid', 'label' => 'Clear Fluid', 'color' => 'bg-blue-100 text-blue-700'],
                ['value' => 'full_fluid', 'label' => 'Full Fluid', 'color' => 'bg-cyan-100 text-cyan-700'],
                ['value' => 'soft_diet', 'label' => 'Soft Diet', 'color' => 'bg-orange-100 text-orange-700'],
                ['value' => 'regular', 'label' => 'Regular', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'vegetarian', 'label' => 'Vegetarian', 'color' => 'bg-lime-100 text-lime-700'],
                ['value' => 'diabetic', 'label' => 'Diabetic', 'color' => 'bg-purple-100 text-purple-700'],
                ['value' => 'renal', 'label' => 'Renal', 'color' => 'bg-pink-100 text-pink-700'],
                ['value' => 'low_salt', 'label' => 'Low Salt', 'color' => 'bg-amber-100 text-amber-700'],
                ['value' => 'halal', 'label' => 'Halal', 'color' => 'bg-emerald-100 text-emerald-700'],
                ['value' => 'kosher', 'label' => 'Kosher', 'color' => 'bg-indigo-100 text-indigo-700'],
                ['value' => 'gluten_free', 'label' => 'Gluten Free', 'color' => 'bg-rose-100 text-rose-700'],
            ],
            'fall_risk' => [
                ['value' => 'none', 'label' => 'None', 'color' => 'bg-gray-100 text-gray-600'],
                ['value' => 'low', 'label' => 'Low', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'medium', 'label' => 'Medium', 'color' => 'bg-yellow-100 text-yellow-700'],
                ['value' => 'high', 'label' => 'High', 'color' => 'bg-red-100 text-red-700'],
            ],
            'isolation_type' => [
                ['value' => 'none', 'label' => 'None', 'color' => 'bg-gray-100 text-gray-600'],
                ['value' => 'contact', 'label' => 'Contact', 'color' => 'bg-blue-100 text-blue-700'],
                ['value' => 'droplet', 'label' => 'Droplet', 'color' => 'bg-green-100 text-green-700'],
                ['value' => 'airborne', 'label' => 'Airborne', 'color' => 'bg-orange-100 text-orange-700'],
                ['value' => 'protective', 'label' => 'Protective', 'color' => 'bg-purple-100 text-purple-700'],
            ],
        ];
    }
}

