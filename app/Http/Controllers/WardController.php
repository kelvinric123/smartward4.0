<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\Hospital;
use Illuminate\Http\Request;

class WardController extends Controller
{
    public function index()
    {
        $wards = Ward::with('hospital')->latest()->paginate(10);
        return view('admin.wards.index', compact('wards'));
    }

    public function create()
    {
        $hospitals = Hospital::where('is_active', true)->get();
        return view('admin.wards.create', compact('hospitals'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hospital_id' => 'required|exists:hospitals,id',
            'ward_code' => 'required|string|max:255|unique:wards,ward_code',
            'ward_name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'specialties' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['is_active'] = true;
        Ward::create($validated);

        return redirect()->route('wards.index')->with('success', 'Ward created successfully.');
    }

    public function edit(Ward $ward)
    {
        $hospitals = Hospital::where('is_active', true)->get();
        return view('admin.wards.edit', compact('ward', 'hospitals'));
    }

    public function update(Request $request, Ward $ward)
    {
        $validated = $request->validate([
            'hospital_id' => 'required|exists:hospitals,id',
            'ward_code' => 'required|string|max:255|unique:wards,ward_code,' . $ward->id,
            'ward_name' => 'required|string|max:255',
            'capacity' => 'required|integer|min:1',
            'specialties' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $ward->update($validated);

        return redirect()->route('wards.index')->with('success', 'Ward updated successfully.');
    }

    public function deactivate(Ward $ward)
    {
        $ward->update(['is_active' => !$ward->is_active]);
        $status = $ward->is_active ? 'activated' : 'deactivated';
        return redirect()->route('wards.index')->with('success', "Ward {$status} successfully.");
    }

    public function destroy(Ward $ward)
    {
        $ward->delete();
        return redirect()->route('wards.index')->with('success', 'Ward deleted successfully.');
    }
}
