<?php

namespace App\Http\Controllers;

use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HospitalController extends Controller
{
    public function index()
    {
        $hospitals = Hospital::latest()->paginate(10);
        return view('admin.hospitals.index', compact('hospitals'));
    }

    public function create()
    {
        return view('admin.hospitals.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo_path'] = $request->file('logo')->store('hospitals', 'public');
        }

        $validated['is_active'] = true;
        Hospital::create($validated);

        return redirect()->route('hospitals.index')->with('success', 'Hospital created successfully.');
    }

    public function edit(Hospital $hospital)
    {
        return view('admin.hospitals.edit', compact('hospital'));
    }

    public function update(Request $request, Hospital $hospital)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            if ($hospital->logo_path) {
                Storage::disk('public')->delete($hospital->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('hospitals', 'public');
        }

        $hospital->update($validated);

        return redirect()->route('hospitals.index')->with('success', 'Hospital updated successfully.');
    }

    public function deactivate(Hospital $hospital)
    {
        $hospital->update(['is_active' => !$hospital->is_active]);
        $status = $hospital->is_active ? 'activated' : 'deactivated';
        return redirect()->route('hospitals.index')->with('success', "Hospital {$status} successfully.");
    }

    public function destroy(Hospital $hospital)
    {
        $hospital->delete();
        return redirect()->route('hospitals.index')->with('success', 'Hospital deleted successfully.');
    }
}
