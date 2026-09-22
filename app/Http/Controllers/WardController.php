<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\Hospital;
use App\Models\WardType;
use Illuminate\Http\Request;

class WardController extends Controller
{
    public function index()
    {
        $wards = Ward::with(['hospital', 'wardType'])->latest()->paginate(10);
        return view('admin.wards.index', compact('wards'));
    }

    public function create()
    {
        $hospitals = Hospital::where('is_active', true)->get();
        $wardTypes = $this->selectableWardTypes();
        return view('admin.wards.create', compact('hospitals', 'wardTypes'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'hospital_id' => 'required|exists:hospitals,id',
            'ward_code' => 'required|string|max:255|unique:wards,ward_code',
            'ward_name' => 'required|string|max:255',
            'ward_type_id' => 'nullable|exists:ward_types,id',
            'capacity' => 'required|integer|min:1',
            'specialties' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['ward_type_id'] = $validated['ward_type_id'] ?? null;
        $validated['is_active'] = true;
        Ward::create($validated);

        return redirect()->route('wards.index')->with('success', 'Ward created successfully.');
    }

    public function edit(Ward $ward)
    {
        $hospitals = Hospital::where('is_active', true)->get();
        $wardTypes = $this->selectableWardTypes($ward->ward_type_id);
        return view('admin.wards.edit', compact('ward', 'hospitals', 'wardTypes'));
    }

    public function update(Request $request, Ward $ward)
    {
        $validated = $request->validate([
            'hospital_id' => 'required|exists:hospitals,id',
            'ward_code' => 'required|string|max:255|unique:wards,ward_code,' . $ward->id,
            'ward_name' => 'required|string|max:255',
            'ward_type_id' => 'nullable|exists:ward_types,id',
            'capacity' => 'required|integer|min:1',
            'specialties' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $validated['ward_type_id'] = $validated['ward_type_id'] ?? null;
        $ward->update($validated);

        return redirect()->route('wards.index')->with('success', 'Ward updated successfully.');
    }

    public function deactivate(Ward $ward)
    {
        $ward->update(['is_active' => !$ward->is_active]);
        $status = $ward->is_active ? 'activated' : 'deactivated';
        return redirect()->route('wards.index')->with('success', "Ward {$status} successfully.");
    }

    /**
     * Ward types on offer: the system ones plus any hospital's own. The blade
     * filters this list down to the hospital picked in the form, and a type
     * already bound to the ward stays selectable even if it was deactivated.
     */
    private function selectableWardTypes($keepId = null)
    {
        return WardType::where(function ($query) use ($keepId) {
            $query->where('is_active', true);
            if ($keepId) {
                $query->orWhere('id', $keepId);
            }
        })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'hospital_id', 'code', 'name']);
    }

    public function destroy(Ward $ward)
    {
        $ward->delete();
        return redirect()->route('wards.index')->with('success', 'Ward deleted successfully.');
    }
}
