<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\Nurse;
use App\Models\Patient;
use App\Models\WardScheduleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class WardScheduleController extends Controller
{
    public function index(Request $request)
    {
        return $this->getScheduleData($request, 'wards.schedule', false);
    }

    public function individual(Request $request)
    {
        return $this->getScheduleData($request, 'wards.schedule', true);
    }

    private function getScheduleData(Request $request, string $view, bool $individualMode)
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
        $selectedNurseId = $individualMode ? $request->input('nurse_id') : null;

        $dateRange = collect(range(-2, 2))
            ->map(fn(int $offset) => Carbon::parse($selectedDate)->addDays($offset));

        $beds = $selectedWardId
            ? Bed::where('ward_id', $selectedWardId)
                ->where('is_active', true)
                ->with('patient')
                ->orderByRaw('CAST(bed_number AS UNSIGNED)') // sort Bed1..Bed22 numerically
                ->orderBy('id') // stable secondary order
                ->get()
            : collect();

        $nurses = Nurse::where('is_active', true)
            ->orderBy('name')
            ->get();

        $assignments = collect();

        if ($selectedWardId) {
            $query = WardScheduleAssignment::with('nurse')
                ->where('ward_id', $selectedWardId)
                ->whereBetween('scheduled_date', [
                    $dateRange->first()->toDateString(),
                    $dateRange->last()->toDateString(),
                ]);

            if ($individualMode && $selectedNurseId) {
                $query->where('nurse_id', $selectedNurseId);
            }

            $assignments = $query->get()
                ->keyBy(fn($assignment) => $assignment->bed_id . '|' . $assignment->scheduled_date->format('Y-m-d') . '|' . $assignment->shift)
                ->map(fn($assignment) => [
                    'id' => $assignment->id,
                    'bed_id' => $assignment->bed_id,
                    'shift' => $assignment->shift,
                    'date' => $assignment->scheduled_date->format('Y-m-d'),
                    'nurse_id' => $assignment->nurse_id,
                    'nurse_name' => $assignment->nurse->name ?? null,
                ]);

            if ($individualMode && $selectedNurseId) {
                // In individual mode, only show beds that have at least one assignment for this nurse
                $assignedBedIds = $assignments->pluck('bed_id')->unique();
                $beds = $beds->whereIn('id', $assignedBedIds);
            }
        }

        return view($view, [
            'wards' => $wards,
            'selectedWard' => $selectedWard,
            'selectedWardId' => $selectedWardId,
            'selectedDate' => $selectedDate,
            'beds' => $beds,
            'shifts' => ['AM', 'PM', 'ON'],
            'dateRange' => $dateRange,
            'nurses' => $nurses,
            'assignments' => $assignments->toArray(),
            'individualMode' => $individualMode,
            'selectedNurseId' => $selectedNurseId,
        ]);
    }

    public function assignNurses(Request $request)
    {
        $rawAssignments = json_decode($request->input('assignments', '[]'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return back()->with('error', 'Unable to read selected shifts. Please try again.');
        }

        $request->merge([
            'assignments' => $rawAssignments,
        ]);

        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'date' => 'required|date',
            'nurse_id' => 'required|exists:nurses,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.bed_id' => 'required|exists:beds,id',
            'assignments.*.date' => 'required|date',
            'assignments.*.shift' => 'required|in:AM,PM,ON',
        ]);

        $wardId = $validated['ward_id'];
        $nurseId = $validated['nurse_id'];
        $assignments = $validated['assignments'];

        foreach ($assignments as $assignment) {
            $bedBelongsToWard = Bed::where('ward_id', $wardId)
                ->where('id', $assignment['bed_id'])
                ->exists();

            if (!$bedBelongsToWard) {
                return back()->with('error', 'One or more selected beds do not belong to this ward.');
            }
        }

        foreach ($assignments as $assignment) {
            $scheduledDate = Carbon::parse($assignment['date'])->toDateString();

            WardScheduleAssignment::updateOrCreate(
                [
                    'bed_id' => $assignment['bed_id'],
                    'scheduled_date' => $scheduledDate,
                    'shift' => $assignment['shift'],
                ],
                [
                    'ward_id' => $wardId,
                    'nurse_id' => $nurseId,
                ]
            );
        }

        return redirect()->route('ward.schedule', [
            'ward_id' => $wardId,
            'date' => $validated['date'],
        ])->with('success', 'Nurse assigned to ' . count($assignments) . ' shift(s).');
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

