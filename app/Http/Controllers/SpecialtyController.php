<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function index(Request $request)
    {
        // Searched in the database, not in the rendered page: filtering the
        // current page of 10 rows only ever found what was already on screen.
        $search = trim((string) $request->input('search', ''));

        $query = Specialty::withCount('consultants')->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $specialties = $query->paginate(10)->withQueryString();

        return view('admin.specialties.index', compact('specialties', 'search'));
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
