<?php

namespace App\Http\Controllers;

use App\Models\Nurse;
use Illuminate\Http\Request;

class NurseController extends Controller
{
    public function index()
    {
        $nurses = Nurse::latest()->paginate(10);
        return view('admin.nurses.index', compact('nurses'));
    }

    public function create()
    {
        return view('admin.nurses.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:nurses,registration_number',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualification' => 'required|in:Diploma,Degree,Masters',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = true;
        Nurse::create($validated);

        return redirect()->route('nurses.index')->with('success', 'Nurse created successfully.');
    }

    public function edit(Nurse $nurse)
    {
        return view('admin.nurses.edit', compact('nurse'));
    }

    public function update(Request $request, Nurse $nurse)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:nurses,registration_number,' . $nurse->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualification' => 'required|in:Diploma,Degree,Masters',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        $nurse->update($validated);

        return redirect()->route('nurses.index')->with('success', 'Nurse updated successfully.');
    }

    public function deactivate(Nurse $nurse)
    {
        $nurse->update(['is_active' => !$nurse->is_active]);
        $status = $nurse->is_active ? 'activated' : 'deactivated';
        return redirect()->route('nurses.index')->with('success', "Nurse {$status} successfully.");
    }

    public function destroy(Nurse $nurse)
    {
        $nurse->delete();
        return redirect()->route('nurses.index')->with('success', 'Nurse deleted successfully.');
    }
}
