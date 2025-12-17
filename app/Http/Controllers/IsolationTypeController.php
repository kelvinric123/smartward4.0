<?php

namespace App\Http\Controllers;

use App\Models\IsolationType;
use App\Models\DietType;
use Illuminate\Http\Request;

class IsolationTypeController extends Controller
{
    public function index()
    {
        $dietTypes = DietType::orderBy('code')->paginate(20, ['*'], 'diet_page');
        $isolationTypes = IsolationType::orderBy('code')->paginate(20, ['*'], 'isolation_page');
        return view('admin.diet-types.index', compact('dietTypes', 'isolationTypes'));
    }

    public function create()
    {
        return view('admin.isolation-types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:isolation_types,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;
        IsolationType::create($validated);

        return redirect()->route('diet-types.index', ['tab' => 'isolation'])->with('success', 'Isolation type created successfully.');
    }

    public function edit(IsolationType $isolationType)
    {
        return view('admin.isolation-types.edit', compact('isolationType'));
    }

    public function update(Request $request, IsolationType $isolationType)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:isolation_types,code,' . $isolationType->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $isolationType->update($validated);

        return redirect()->route('diet-types.index', ['tab' => 'isolation'])->with('success', 'Isolation type updated successfully.');
    }

    public function toggleActive(IsolationType $isolationType)
    {
        $isolationType->update(['is_active' => !$isolationType->is_active]);
        $status = $isolationType->is_active ? 'activated' : 'deactivated';
        return redirect()->route('diet-types.index', ['tab' => 'isolation'])->with('success', "Isolation type {$status} successfully.");
    }

    public function destroy(IsolationType $isolationType)
    {
        $isolationType->delete();
        return redirect()->route('diet-types.index', ['tab' => 'isolation'])->with('success', 'Isolation type deleted successfully.');
    }
}









