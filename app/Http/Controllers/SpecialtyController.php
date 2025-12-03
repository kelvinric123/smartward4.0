<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function index()
    {
        $specialties = Specialty::withCount('consultants')->latest()->paginate(10);
        return view('admin.specialties.index', compact('specialties'));
    }

    public function create()
    {
        return view('admin.specialties.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = true;
        Specialty::create($validated);

        return redirect()->route('specialties.index')->with('success', 'Specialty created successfully.');
    }

    public function edit(Specialty $specialty)
    {
        return view('admin.specialties.edit', compact('specialty'));
    }

    public function update(Request $request, Specialty $specialty)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $specialty->update($validated);

        return redirect()->route('specialties.index')->with('success', 'Specialty updated successfully.');
    }

    public function deactivate(Specialty $specialty)
    {
        $specialty->update(['is_active' => !$specialty->is_active]);
        $status = $specialty->is_active ? 'activated' : 'deactivated';
        return redirect()->route('specialties.index')->with('success', "Specialty {$status} successfully.");
    }

    public function destroy(Specialty $specialty)
    {
        $specialty->delete();
        return redirect()->route('specialties.index')->with('success', 'Specialty deleted successfully.');
    }
}
