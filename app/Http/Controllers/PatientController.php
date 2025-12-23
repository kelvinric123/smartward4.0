<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Patient::latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('mrn', 'like', '%' . $search . '%')
                    ->orWhere('rn', 'like', '%' . $search . '%');
            });
        }

        $patients = $query->paginate(15)->withQueryString();

        return view('patients.index', compact('patients', 'search'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('patients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mrn' => 'required|string|unique:patients,mrn',
            'rn' => 'required|string|unique:patients,rn',
            'ic_passport' => 'required|string|unique:patients,ic_passport',
            'age' => 'required|integer|min:0|max:150',
            'gender' => 'required|in:Male,Female',
            'phone' => 'required|string',
        ]);

        Patient::create($validated);

        return redirect()->route('patients.index')->with('success', 'Patient created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient)
    {
        return view('patients.show', compact('patient'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient)
    {
        return view('patients.edit', compact('patient'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'mrn' => 'required|string|unique:patients,mrn,' . $patient->id,
            'rn' => 'required|string|unique:patients,rn,' . $patient->id,
            'ic_passport' => 'required|string|unique:patients,ic_passport,' . $patient->id,
            'age' => 'required|integer|min:0|max:150',
            'gender' => 'required|in:Male,Female',
            'phone' => 'required|string',
        ]);

        $patient->update($validated);

        return redirect()->route('patients.index')->with('success', 'Patient updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient)
    {
        $patient->delete();
        return redirect()->route('patients.index')->with('success', 'Patient deleted successfully.');
    }

    /**
     * Toggle the active status of the patient.
     */
    public function deactivate(Patient $patient)
    {
        $patient->update(['is_active' => !$patient->is_active]);
        $status = $patient->is_active ? 'activated' : 'deactivated';
        return redirect()->route('patients.index')->with('success', "Patient {$status} successfully.");
    }
}
