<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Ward;
use App\Models\Nurse;
use App\Models\Anaesthetist;
use App\Models\Patient;
use App\Models\Consultant;
use Illuminate\Http\Request;

class BedController extends Controller
{
    public function index()
    {
        // Sync bed status with patient assignments
        $this->syncBedStatus();

        $beds = Bed::with(['ward', 'nurse', 'anaesthetist', 'patient', 'consultants'])->latest()->paginate(10);
        return view('admin.beds.index', compact('beds'));
    }

    private function syncBedStatus()
    {
        // Get all beds
        $beds = Bed::all();

        foreach ($beds as $bed) {
            // Check if bed has a patient assigned through the patient table
            $patient = Patient::where('ward_id', $bed->ward_id)
                ->where('bed_number', $bed->bed_number)
                ->where('is_active', true)
                ->whereIn('status', ['admitted', 'prebook', 'pending_discharge'])
                ->first();

            if ($patient) {
                // Update bed status based on patient status
                $bedStatus = $patient->status === 'prebook' ? 'reserved' : 'occupied';
                $bed->update([
                    'status' => $bedStatus,
                    'patient_id' => $patient->id,
                ]);
            } else {
                // No patient assigned, mark bed as available
                if ($bed->status !== 'maintenance') {
                    $bed->update([
                        'status' => 'available',
                        'patient_id' => null,
                    ]);
                }
            }
        }
    }

    public function create()
    {
        $wards = Ward::where('is_active', true)->get();
        $nurses = Nurse::where('is_active', true)->get();
        $anaesthetists = Anaesthetist::where('is_active', true)->get();
        $patients = Patient::where('is_active', true)->get();
        $consultants = Consultant::where('is_active', true)->get();

        return view('admin.beds.create', compact('wards', 'nurses', 'anaesthetists', 'patients', 'consultants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'section' => 'nullable|string|in:1,2,3,4',
            'bed_number' => 'required|string|max:255',
            'bed_id' => 'required|string|max:255|unique:beds,bed_id',
            'bed_display_name' => 'required|string|max:255',
            'status' => 'required|in:available,occupied,reserved,maintenance',
            'nurse_id' => 'nullable|exists:nurses,id',
            'anaesthetist_id' => 'nullable|exists:anaesthetists,id',
            'patient_id' => 'nullable|exists:patients,id',
            'consultant_ids' => 'nullable|array',
            'consultant_ids.*' => 'exists:consultants,id',
        ]);

        $consultantIds = $validated['consultant_ids'] ?? [];
        unset($validated['consultant_ids']);

        $validated['is_active'] = true;
        $bed = Bed::create($validated);

        if (!empty($consultantIds)) {
            $bed->consultants()->sync($consultantIds);
        }

        return redirect()->route('beds.index')->with('success', 'Bed created successfully.');
    }

    public function edit(Bed $bed)
    {
        $wards = Ward::where('is_active', true)->get();
        $nurses = Nurse::where('is_active', true)->get();
        $anaesthetists = Anaesthetist::where('is_active', true)->get();
        $patients = Patient::where('is_active', true)->get();
        $consultants = Consultant::where('is_active', true)->get();

        return view('admin.beds.edit', compact('bed', 'wards', 'nurses', 'anaesthetists', 'patients', 'consultants'));
    }

    public function update(Request $request, Bed $bed)
    {
        $validated = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'section' => 'nullable|string|in:1,2,3,4',
            'bed_number' => 'required|string|max:255',
            'bed_id' => 'required|string|max:255|unique:beds,bed_id,' . $bed->id,
            'bed_display_name' => 'required|string|max:255',
            'status' => 'required|in:available,occupied,reserved,maintenance',
            'nurse_id' => 'nullable|exists:nurses,id',
            'anaesthetist_id' => 'nullable|exists:anaesthetists,id',
            'patient_id' => 'nullable|exists:patients,id',
            'consultant_ids' => 'nullable|array',
            'consultant_ids.*' => 'exists:consultants,id',
        ]);

        $consultantIds = $validated['consultant_ids'] ?? [];
        unset($validated['consultant_ids']);

        $bed->update($validated);

        if (isset($consultantIds)) {
            $bed->consultants()->sync($consultantIds);
        }

        return redirect()->route('beds.index')->with('success', 'Bed updated successfully.');
    }

    public function deactivate(Bed $bed)
    {
        $bed->update(['is_active' => !$bed->is_active]);
        $status = $bed->is_active ? 'activated' : 'deactivated';
        return redirect()->route('beds.index')->with('success', "Bed {$status} successfully.");
    }

    /**
     * Put a bed under maintenance, or bring it back into service. A bed can only
     * go under maintenance while nobody is admitted to it or booked into it; the
     * user is told who is there and what to do first.
     */
    public function maintenance(Bed $bed)
    {
        $label = $bed->bed_display_name ?: $bed->bed_number;

        if ($bed->isUnderMaintenance()) {
            $bed->update(['status' => 'available']);

            return back()->with('success', "Bed {$label} is back in service.");
        }

        $occupant = $bed->occupant();
        if ($occupant) {
            $reason = in_array($occupant->status, [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING], true)
                ? "it is prebooked for {$occupant->name}. Cancel or move the prebooking first."
                : "{$occupant->name} is admitted to it. Discharge or transfer the patient first.";

            return back()->with('error', "Bed {$label} cannot go under maintenance: {$reason}");
        }

        $bed->update(['status' => Bed::STATUS_MAINTENANCE, 'patient_id' => null]);

        return back()->with('success', "Bed {$label} is now under maintenance. Nobody can be admitted, "
            . 'prebooked or transferred to it until maintenance ends.');
    }

    public function destroy(Bed $bed)
    {
        $bed->delete();
        return redirect()->route('beds.index')->with('success', 'Bed deleted successfully.');
    }
}
