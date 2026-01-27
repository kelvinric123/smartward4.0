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
use Illuminate\Support\Str;
use App\Services\EkadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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

        $isLockedToNurse = false;
        if (Auth::user()->hasRole(User::ROLE_NURSE)) {
            $nurse = Nurse::where('email', Auth::user()->email)->where('is_active', true)->first();
            if ($nurse) {
                $individualMode = true;
                $selectedNurseId = $nurse->id;
                $isLockedToNurse = true;
            }
        }

        $dateRange = collect(range(0, 4))
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
            'isLockedToNurse' => $isLockedToNurse ?? false,
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

        $bedsToUpdate = [];

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

            // Collect bed IDs for EKAD update if assignment is for today
            if ($scheduledDate === now()->toDateString()) {
                $bedsToUpdate[$assignment['bed_id']] = true;
            }
        }

        // Trigger Ekad updates for affected beds (once per bed)
        if (!empty($bedsToUpdate)) {
            try {
                $ekadService = new EkadService();
                $bedIds = array_keys($bedsToUpdate);
                $beds = Bed::with([
                    'patient' => function ($q) {
                        $q->where('is_active', true)
                            ->whereIn('status', ['admitted', 'prebook', 'pending_discharge']);
                    }
                ])->whereIn('id', $bedIds)->get();

                foreach ($beds as $bed) {
                    if ($bed->patient) {
                        // Push without override so EkadService calculates the correct nurse for the current time
                        $ekadService->pushPatientInfo($bed->patient, $bed, [], 'Update Information');
                        Log::info('EKad: Pushed nurse assignment update', [
                            'bed_id' => $bed->id,
                            'patient' => $bed->patient->name,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('EKad Nurse update push failed', ['error' => $e->getMessage()]);
            }
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

    public function downloadTemplate(Request $request)
    {
        $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'date' => 'required|date',
        ]);

        $wardId = $request->input('ward_id');
        $date = Carbon::parse($request->input('date'));

        $ward = Ward::find($wardId);
        $beds = Bed::where('ward_id', $wardId)
            ->where('is_active', true)
            ->orderByRaw('CAST(bed_number AS UNSIGNED)')
            ->get();

        $nurses = Nurse::where('is_active', true)
            ->orderBy('name')
            ->get();

        $rows = [];
        // Generate rows for the entire month of the selected date
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        for ($d = $startDate; $d->lte($endDate); $d->addDay()) {
            foreach ($beds as $bed) {
                foreach (['AM', 'PM', 'ON'] as $shift) {
                    $rows[] = [
                        'date' => $d->toDateString(),
                        'bed' => $bed->bed_display_name ?? 'Bed ' . $bed->bed_number,
                        'shift' => $shift,
                    ];
                }
            }
        }

        $content = view('wards.exports.schedule-template', [
            'rows' => $rows,
            'nurses' => $nurses,
        ])->render();

        return response($content)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="ward-schedule-template-' . $ward->ward_code . '-' . $date->format('Y-m') . '.xml"');
    }

    public function uploadRoster(Request $request)
    {
        $request->validate([
            'file' => 'required|file', // Strict XML validation fails sometimes with .xml type depending on OS mime types
        ]);

        $file = $request->file('file');

        // Basic check for content
        $content = file_get_contents($file->getRealPath());
        if (!str_contains($content, 'urn:schemas-microsoft-com:office:spreadsheet')) {
            return back()->with('error', 'Invalid file format. Please use the downloaded XML template.');
        }

        try {
            $xml = simplexml_load_string($content);
            $namespaces = $xml->getNamespaces(true);
            $ss = $namespaces['ss'] ?? 'urn:schemas-microsoft-com:office:spreadsheet';

            // Find Roster worksheet
            $rosterSheet = null;
            foreach ($xml->Worksheet as $sheet) {
                $attributes = $sheet->attributes($ss);
                if ((string) $attributes['Name'] === 'Roster') {
                    $rosterSheet = $sheet;
                    break;
                }
            }

            if (!$rosterSheet) {
                return back()->with('error', 'Could not find "Roster" worksheet.');
            }

            $count = 0;
            $errors = 0;

            // Iterate rows (skip header row 1)
            $rows = $rosterSheet->Table->Row;
            $isHeader = true;

            foreach ($rows as $row) {
                if ($isHeader) {
                    $isHeader = false;
                    continue;
                }

                $cells = $row->Cell;
                // Helper to get cell data by index (1-based in XML logic, but simplexml iteration is 0-based if sequential. 
                // However valid XML Excel often skips empty cells, using ss:Index. We need to handle that carefully.)
                // For simplicity, we assume the template structure is preserved and all cells have data or are sequential.
                // A better approach is to map cells by specific logic.

                // Let's rely on node iteration. We expect 4 columns: Date, Bed, Shift, Nurse.
                // Date = 0, Bed = 1, Shift = 2, Nurse = 3 (if 0-indexed without gaps)

                $dataValues = [];
                $currentIndex = 1;

                foreach ($cells as $cell) {
                    $attrs = $cell->attributes($ss);
                    if (isset($attrs['Index'])) {
                        $currentIndex = (int) $attrs['Index'];
                    }

                    $data = (string) $cell->Data;
                    $dataValues[$currentIndex] = $data;
                    $currentIndex++;
                }

                $dateStr = $dataValues[1] ?? null;
                $bedName = $dataValues[2] ?? null;
                $shift = $dataValues[3] ?? null;
                $nurseStr = $dataValues[4] ?? null;

                if (!$dateStr || !$bedName || !$shift || !$nurseStr) {
                    continue; // Skip incomplete lines
                }

                // Parse Nurse String "Name [ID]"
                if (preg_match('/^.+ \[(\d+)\]$/', $nurseStr, $matches)) {
                    $nurseId = $matches[1];
                } else {
                    $errors++;
                    continue;
                }

                // Find Bed ID
                // We assume 'Bed X' or display name is unique within ward.
                // We need ward_id from the request.
                $wardId = $request->input('ward_id'); // Ensure this is passed in the upload form

                if (!$wardId) {
                    // Try to guess from filename or assume it's lost? 
                    // We must require ward_id in the upload form.
                    return back()->with('error', 'Ward ID is missing.');
                }

                // Cache beds for performance
                static $wardBeds = null;
                if ($wardBeds === null) {
                    $wardBeds = Bed::where('ward_id', $wardId)->get();
                }

                // Try to find the bed
                // The template exports: $bed->bed_display_name ?? 'Bed ' . $bed->bed_number
                $bed = $wardBeds->first(function ($b) use ($bedName) {
                    return ($b->bed_display_name === $bedName) ||
                        ('Bed ' . $b->bed_number === $bedName) ||
                        ($b->bed_number === $bedName);
                });

                if (!$bed) {
                    // Bed not found in this ward
                    // Could check if bedName contains [ID] if we change template later
                    $errors++;
                    continue;
                }

                $scheduledDate = Carbon::parse($dateStr)->toDateString();

                WardScheduleAssignment::updateOrCreate(
                    [
                        'ward_id' => $wardId,
                        'bed_id' => $bed->id,
                        'scheduled_date' => $scheduledDate,
                        'shift' => $shift,
                    ],
                    [
                        'nurse_id' => $nurseId,
                    ]
                );
                $count++;
            }

            $message = "Successfully imported $count assignments.";
            if ($errors > 0) {
                $message .= " ($errors rows skipped due to invalid data)";
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'Error parsing file: ' . $e->getMessage());
        }
    }
}


