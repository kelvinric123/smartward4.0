<?php

namespace App\Http\Controllers;

use App\Models\DietType;
use App\Models\IsolationType;
use Illuminate\Http\Request;

class DietTypeController extends Controller
{
    public function index()
    {
        $dietTypes = DietType::orderBy('code')->paginate(20, ['*'], 'diet_page');
        $isolationTypes = IsolationType::orderBy('code')->paginate(20, ['*'], 'isolation_page');
        return view('admin.diet-types.index', compact('dietTypes', 'isolationTypes'));
    }

    public function create()
    {
        return view('admin.diet-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:diet_types,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;
        DietType::create($validated);

        return redirect()->route('diet-types.index')->with('success', 'Diet type created successfully.');
    }

    public function edit(DietType $dietType)
    {
        return view('admin.diet-types.edit', compact('dietType'));
    }

    public function update(Request $request, DietType $dietType)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:diet_types,code,' . $dietType->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $dietType->update($validated);

        return redirect()->route('diet-types.index')->with('success', 'Diet type updated successfully.');
    }

    public function toggleActive(DietType $dietType)
    {
        $dietType->update(['is_active' => !$dietType->is_active]);
        $status = $dietType->is_active ? 'activated' : 'deactivated';
        return redirect()->route('diet-types.index')->with('success', "Diet type {$status} successfully.");
    }

    public function destroy(DietType $dietType)
    {
        $dietType->delete();
        return redirect()->route('diet-types.index')->with('success', 'Diet type deleted successfully.');
    }
}










