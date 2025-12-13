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
            'personnel_code' => 'nullable|string|max:50|unique:nurses,personnel_code',
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
            'personnel_code' => 'nullable|string|max:50|unique:nurses,personnel_code,' . $nurse->id,
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

    public function bulkUploadForm()
    {
        return view('admin.nurses.bulk-upload');
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

        // Check existing nurses by personnel_code
        $existingCodes = Nurse::whereIn('personnel_code', collect($parsed['staff'])->pluck('personnel_code')->filter())->pluck('personnel_code')->toArray();
        
        // Check existing nurses by username (registration_number)
        $existingUsernames = Nurse::whereIn('registration_number', collect($parsed['staff'])->pluck('registration_number')->filter())->pluck('registration_number')->toArray();

        // Get all current active nurses to check for missing staff
        $allActiveNurses = Nurse::where('is_active', true)->get();
        $uploadedCodes = collect($parsed['staff'])->pluck('personnel_code')->filter()->toArray();
        
        $missingNurses = $allActiveNurses->filter(function ($nurse) use ($uploadedCodes) {
            return $nurse->personnel_code && !in_array($nurse->personnel_code, $uploadedCodes);
        });

        $toAdd = [];
        $existing = [];
        $toUpdate = [];

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
        session(['bulk_upload_data' => [
            'to_add' => $toAdd,
            'existing' => $existing,
            'missing' => $missingNurses->toArray(),
        ]]);

        return view('admin.nurses.bulk-upload-preview', [
            'toAdd' => $toAdd,
            'existing' => $existing,
            'missingNurses' => $missingNurses,
        ]);
    }

    public function bulkUploadConfirm(Request $request)
    {
        $data = session('bulk_upload_data');

        if (!$data || empty($data['to_add'])) {
            return redirect()->route('nurses.bulk-upload')->withErrors(['file' => 'No data to import. Please upload the file again.']);
        }

        $added = 0;
        foreach ($data['to_add'] as $staff) {
            Nurse::create([
                'personnel_code' => $staff['personnel_code'],
                'name' => $staff['name'],
                'registration_number' => $staff['registration_number'],
                'email' => $staff['email'],
                'department' => $staff['department'],
                'designation' => $staff['designation'],
                'qualification' => 'Diploma', // Default qualification
                'is_active' => true,
            ]);
            $added++;
        }

        session()->forget('bulk_upload_data');

        $message = "{$added} nurse(s) imported successfully.";
        if (!empty($data['missing'])) {
            $message .= " Note: " . count($data['missing']) . " nurse(s) from the previous list were not in this upload.";
        }

        return redirect()->route('nurses.index')->with('success', $message);
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
                $cells = array_values(array_filter($cells, function($cell, $key) use ($cells) {
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
                            if ($lower === 'name') $columnMap['name'] = $idx;
                            if ($lower === 'email address') $columnMap['email'] = $idx;
                            if ($lower === 'username') $columnMap['username'] = $idx;
                            if ($lower === 'designation') $columnMap['designation'] = $idx;
                            if ($lower === 'employee no') $columnMap['employee_no'] = $idx;
                            if ($lower === 'department') $columnMap['department'] = $idx;
                        }
                        continue;
                    }
                }

                // Parse data row
                if ($headerFound && !empty($columnMap)) {
                    $name = isset($columnMap['name']) && isset($cells[$columnMap['name']]) ? trim($cells[$columnMap['name']]) : '';
                    $email = isset($columnMap['email']) && isset($cells[$columnMap['email']]) ? trim($cells[$columnMap['email']]) : '';
                    $username = isset($columnMap['username']) && isset($cells[$columnMap['username']]) ? trim($cells[$columnMap['username']]) : '';
                    $designation = isset($columnMap['designation']) && isset($cells[$columnMap['designation']]) ? trim($cells[$columnMap['designation']]) : '';
                    $employeeNo = isset($columnMap['employee_no']) && isset($cells[$columnMap['employee_no']]) ? trim($cells[$columnMap['employee_no']]) : '';
                    $department = isset($columnMap['department']) && isset($cells[$columnMap['department']]) ? trim($cells[$columnMap['department']]) : '';

                    // Skip rows without name or employee number (like the "to check:" section or empty rows)
                    if (empty($name) || empty($employeeNo)) {
                        continue;
                    }

                    // Clean up email (remove spaces)
                    $email = str_replace(' ', '', $email);

                    $staff[] = [
                        'name' => $name,
                        'email' => $email,
                        'registration_number' => $username,
                        'designation' => $designation,
                        'personnel_code' => $employeeNo,
                        'department' => $department,
                    ];
                }
            }
        }

        return ['staff' => $staff];
    }
}
