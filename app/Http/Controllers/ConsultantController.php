<?php

namespace App\Http\Controllers;

use App\Models\Consultant;
use App\Models\Specialty;
use Illuminate\Http\Request;

class ConsultantController extends Controller
{
    public function index()
    {
        $consultants = Consultant::with('specialty')->latest()->paginate(10);
        return view('admin.consultants.index', compact('consultants'));
    }

    public function create()
    {
        $specialties = Specialty::where('is_active', true)->get();
        return view('admin.consultants.create', compact('specialties'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'specialty_id' => 'required|exists:specialties,id',
            'registration_number' => 'required|string|unique:consultants,registration_number',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualifications' => 'nullable|string',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = true;
        Consultant::create($validated);

        return redirect()->route('consultants.index')->with('success', 'Consultant created successfully.');
    }

    public function edit(Consultant $consultant)
    {
        $specialties = Specialty::where('is_active', true)->get();
        return view('admin.consultants.edit', compact('consultant', 'specialties'));
    }

    public function update(Request $request, Consultant $consultant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'specialty_id' => 'required|exists:specialties,id',
            'registration_number' => 'required|string|unique:consultants,registration_number,' . $consultant->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualifications' => 'nullable|string',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        $consultant->update($validated);

        return redirect()->route('consultants.index')->with('success', 'Consultant updated successfully.');
    }

    public function deactivate(Consultant $consultant)
    {
        $consultant->update(['is_active' => !$consultant->is_active]);
        $status = $consultant->is_active ? 'activated' : 'deactivated';
        return redirect()->route('consultants.index')->with('success', "Consultant {$status} successfully.");
    }

    public function destroy(Consultant $consultant)
    {
        $consultant->delete();
        return redirect()->route('consultants.index')->with('success', 'Consultant deleted successfully.');
    }
}
