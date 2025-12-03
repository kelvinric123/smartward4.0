<?php

namespace App\Http\Controllers;

use App\Models\Anaesthetist;
use Illuminate\Http\Request;

class AnaesthetistController extends Controller
{
    public function index()
    {
        $anaesthetists = Anaesthetist::latest()->paginate(10);
        return view('admin.anaesthetists.index', compact('anaesthetists'));
    }

    public function create()
    {
        return view('admin.anaesthetists.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:anaesthetists,registration_number',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualifications' => 'nullable|string',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        $validated['is_active'] = true;
        Anaesthetist::create($validated);

        return redirect()->route('anaesthetists.index')->with('success', 'Anaesthetist created successfully.');
    }

    public function edit(Anaesthetist $anaesthetist)
    {
        return view('admin.anaesthetists.edit', compact('anaesthetist'));
    }

    public function update(Request $request, Anaesthetist $anaesthetist)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registration_number' => 'required|string|unique:anaesthetists,registration_number,' . $anaesthetist->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualifications' => 'nullable|string',
            'years_of_experience' => 'nullable|integer|min:0',
        ]);

        $anaesthetist->update($validated);

        return redirect()->route('anaesthetists.index')->with('success', 'Anaesthetist updated successfully.');
    }

    public function deactivate(Anaesthetist $anaesthetist)
    {
        $anaesthetist->update(['is_active' => !$anaesthetist->is_active]);
        $status = $anaesthetist->is_active ? 'activated' : 'deactivated';
        return redirect()->route('anaesthetists.index')->with('success', "Anaesthetist {$status} successfully.");
    }

    public function destroy(Anaesthetist $anaesthetist)
    {
        $anaesthetist->delete();
        return redirect()->route('anaesthetists.index')->with('success', 'Anaesthetist deleted successfully.');
    }
}
