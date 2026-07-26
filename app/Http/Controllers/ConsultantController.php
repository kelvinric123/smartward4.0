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
            'personnel_code' => 'nullable|string|max:50|unique:consultants,personnel_code',
            'name' => 'required|string|max:255',
            'specialty_id' => 'nullable|exists:specialties,id',
            'registration_number' => 'required|string|unique:consultants,registration_number',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualifications' => 'nullable|string',
            'years_of_experience' => 'nullable|integer|min:0',
            'app_username' => 'nullable|string|max:100|unique:consultants,app_username',
            'app_password' => 'nullable|string|min:4|max:100',
        ]);

        if (empty($validated['app_password'])) {
            unset($validated['app_password']);
        }

        $validated['is_active'] = true;
        Consultant::create($validated);

        return redirect()->route('consultants.index')->with('success', 'Consultant created successfully.');
    }

    public function show(Consultant $consultant)
    {
        $consultant->load('specialty');
        return view('admin.consultants.show', compact('consultant'));
    }

    public function edit(Consultant $consultant)
    {
        $specialties = Specialty::where('is_active', true)->get();
        return view('admin.consultants.edit', compact('consultant', 'specialties'));
    }

    public function update(Request $request, Consultant $consultant)
    {
        $validated = $request->validate([
            'personnel_code' => 'nullable|string|max:50|unique:consultants,personnel_code,' . $consultant->id,
            'name' => 'required|string|max:255',
            'specialty_id' => 'nullable|exists:specialties,id',
            'registration_number' => 'required|string|unique:consultants,registration_number,' . $consultant->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'qualifications' => 'nullable|string',
            'years_of_experience' => 'nullable|integer|min:0',
            'app_username' => 'nullable|string|max:100|unique:consultants,app_username,' . $consultant->id,
            'app_password' => 'nullable|string|min:4|max:100',
        ]);

        // Leaving the password blank keeps the current one
        if (empty($validated['app_password'])) {
            unset($validated['app_password']);
        }

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

    public function bulkUploadForm()
    {
        return view('admin.consultants.bulk-upload');
    }

    public function bulkUploadPreview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:md,txt,csv|max:2048',
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $parsed = $this->parseStaffList($content);

        if (empty($parsed['staff'])) {
            return back()->withErrors(['file' => 'Could not parse any staff from the uploaded file. Please ensure it follows the correct markdown table format.']);
        }

        // Check existing consultants by personnel_code
        $existingCodes = Consultant::whereIn('personnel_code', collect($parsed['staff'])->pluck('personnel_code')->filter())->pluck('personnel_code')->toArray();

        // Check existing consultants by registration_number (username)
        $existingUsernames = Consultant::whereIn('registration_number', collect($parsed['staff'])->pluck('registration_number')->filter())->pluck('registration_number')->toArray();

        // Get all current active consultants to check for missing staff
        $allActiveConsultants = Consultant::where('is_active', true)->get();
        $uploadedCodes = collect($parsed['staff'])->pluck('personnel_code')->filter()->toArray();

        $missingConsultants = $allActiveConsultants->filter(function ($consultant) use ($uploadedCodes) {
            return $consultant->personnel_code && !in_array($consultant->personnel_code, $uploadedCodes);
        });

        $toAdd = [];
        $existing = [];

        foreach ($parsed['staff'] as $staff) {
            $existsByCode = in_array($staff['personnel_code'], $existingCodes);
            $existsByUsername = in_array($staff['registration_number'], $existingUsernames);

            if ($existsByCode || $existsByUsername) {
                $existing[] = $staff;
            } else {
                $toAdd[] = $staff;
            }
        }

        // Store parsed data in session for confirmation
        session([
            'consultant_bulk_upload_data' => [
                'to_add' => $toAdd,
                'existing' => $existing,
                'missing' => $missingConsultants->toArray(),
            ]
        ]);

        return view('admin.consultants.bulk-upload-preview', [
            'toAdd' => $toAdd,
            'existing' => $existing,
            'missingConsultants' => $missingConsultants,
        ]);
    }

    public function bulkUploadConfirm(Request $request)
    {
        $data = session('consultant_bulk_upload_data');

        if (!$data || empty($data['to_add'])) {
            return redirect()->route('consultants.bulk-upload')->withErrors(['file' => 'No data to import. Please upload the file again.']);
        }

        $added = 0;
        foreach ($data['to_add'] as $staff) {
            Consultant::create([
                'personnel_code' => $staff['personnel_code'],
                'name' => $staff['name'],
                'registration_number' => $staff['registration_number'],
                'email' => $staff['email'],
                'is_active' => true,
            ]);
            $added++;
        }

        session()->forget('consultant_bulk_upload_data');

        $message = "{$added} consultant(s) imported successfully.";
        if (!empty($data['missing'])) {
            $message .= " Note: " . count($data['missing']) . " consultant(s) from the previous list were not in this upload.";
        }

        return redirect()->route('consultants.index')->with('success', $message);
    }

    /**
     * Parse staff list from markdown table format
     */
    private function parseStaffList(string $content): array
    {
        $lines = explode("\n", $content);
        $staff = [];
        $headerFound = false;
        $columnMap = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and title
            if (empty($line) || strpos($line, '# ') === 0) {
                continue;
            }

            // Skip separator lines (like |:---|:---|)
            if (preg_match('/^\|[\s:-]+\|/', $line)) {
                continue;
            }

            // Parse table row
            if (strpos($line, '|') !== false) {
                $cells = array_map('trim', explode('|', $line));
                // Remove empty first and last elements (from leading/trailing |)
                $cells = array_values(array_filter($cells, function ($cell, $key) use ($cells) {
                    return $key !== 0 || $cell !== '';
                }, ARRAY_FILTER_USE_BOTH));

                // Clean up - remove truly empty first/last from pipe splitting
                if (isset($cells[0]) && $cells[0] === '') {
                    array_shift($cells);
                }
                if (!empty($cells) && end($cells) === '') {
                    array_pop($cells);
                }

                // Check if this is the header row
                if (!$headerFound) {
                    // Check if row contains header keywords
                    $lowerCells = array_map('strtolower', $cells);
                    if (in_array('name', $lowerCells) || in_array('email address', $lowerCells)) {
                        $headerFound = true;
                        // Map column positions
                        foreach ($cells as $idx => $cell) {
                            $lower = strtolower(trim($cell));
                            if ($lower === 'name')
                                $columnMap['name'] = $idx;
                            if ($lower === 'email address')
                                $columnMap['email'] = $idx;
                            if ($lower === 'username')
                                $columnMap['username'] = $idx;
                            if ($lower === 'employee no')
                                $columnMap['employee_no'] = $idx;
                        }
                        continue;
                    }
                }

                // Parse data row
                if ($headerFound && !empty($columnMap)) {
                    $name = isset($columnMap['name']) && isset($cells[$columnMap['name']]) ? trim($cells[$columnMap['name']]) : '';
                    $email = isset($columnMap['email']) && isset($cells[$columnMap['email']]) ? trim($cells[$columnMap['email']]) : '';
                    $username = isset($columnMap['username']) && isset($cells[$columnMap['username']]) ? trim($cells[$columnMap['username']]) : '';
                    $employeeNo = isset($columnMap['employee_no']) && isset($cells[$columnMap['employee_no']]) ? trim($cells[$columnMap['employee_no']]) : '';

                    // Skip rows without name or employee number
                    if (empty($name) || empty($employeeNo)) {
                        continue;
                    }

                    // Clean up email (remove spaces)
                    $email = str_replace(' ', '', $email);

                    $staff[] = [
                        'name' => $name,
                        'email' => $email,
                        'registration_number' => $username,
                        'personnel_code' => $employeeNo,
                    ];
                }
            }
        }

        return ['staff' => $staff];
    }
}
