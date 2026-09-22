<?php

namespace App\Http\Controllers;

use App\Models\Anaesthetist;
use Illuminate\Http\Request;

class AnaesthetistController extends Controller
{
    public function index(Request $request)
    {
        // Searched in the database, not in the rendered page: filtering the
        // current page of 10 rows only ever found what was already on screen.
        $search = trim((string) $request->input('search', ''));

        $query = Anaesthetist::latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                foreach (['name', 'personnel_code', 'registration_number', 'phone', 'email', 'qualifications'] as $field) {
                    $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        $anaesthetists = $query->paginate(10)->withQueryString();

        return view('admin.anaesthetists.index', compact('anaesthetists', 'search'));
    }

    public function create()
    {
        return view('admin.anaesthetists.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'personnel_code' => 'nullable|string|max:50|unique:anaesthetists,personnel_code',
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

    public function show(Anaesthetist $anaesthetist)
    {
        return view('admin.anaesthetists.show', compact('anaesthetist'));
    }

    public function edit(Anaesthetist $anaesthetist)
    {
        return view('admin.anaesthetists.edit', compact('anaesthetist'));
    }

    public function update(Request $request, Anaesthetist $anaesthetist)
    {
        $validated = $request->validate([
            'personnel_code' => 'nullable|string|max:50|unique:anaesthetists,personnel_code,' . $anaesthetist->id,
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

    public function bulkUploadForm()
    {
        return view('admin.anaesthetists.bulk-upload');
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

        // Check existing anaesthetists by personnel_code
        $existingCodes = Anaesthetist::whereIn('personnel_code', collect($parsed['staff'])->pluck('personnel_code')->filter())->pluck('personnel_code')->toArray();

        // Check existing anaesthetists by registration_number (username)
        $existingUsernames = Anaesthetist::whereIn('registration_number', collect($parsed['staff'])->pluck('registration_number')->filter())->pluck('registration_number')->toArray();

        // Get all current active anaesthetists to check for missing staff
        $allActiveAnaesthetists = Anaesthetist::where('is_active', true)->get();
        $uploadedCodes = collect($parsed['staff'])->pluck('personnel_code')->filter()->toArray();

        $missingAnaesthetists = $allActiveAnaesthetists->filter(function ($anaesthetist) use ($uploadedCodes) {
            return $anaesthetist->personnel_code && !in_array($anaesthetist->personnel_code, $uploadedCodes);
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
            'anaesthetist_bulk_upload_data' => [
                'to_add' => $toAdd,
                'existing' => $existing,
                'missing' => $missingAnaesthetists->toArray(),
            ]
        ]);

        return view('admin.anaesthetists.bulk-upload-preview', [
            'toAdd' => $toAdd,
            'existing' => $existing,
            'missingAnaesthetists' => $missingAnaesthetists,
        ]);
    }

    public function bulkUploadConfirm(Request $request)
    {
        $data = session('anaesthetist_bulk_upload_data');

        if (!$data || empty($data['to_add'])) {
            return redirect()->route('anaesthetists.bulk-upload')->withErrors(['file' => 'No data to import. Please upload the file again.']);
        }

        $added = 0;
        foreach ($data['to_add'] as $staff) {
            Anaesthetist::create([
                'personnel_code' => $staff['personnel_code'],
                'name' => $staff['name'],
                'registration_number' => $staff['registration_number'],
                'email' => $staff['email'],
                'is_active' => true,
            ]);
            $added++;
        }

        session()->forget('anaesthetist_bulk_upload_data');

        $message = "{$added} anaesthetist(s) imported successfully.";
        if (!empty($data['missing'])) {
            $message .= " Note: " . count($data['missing']) . " anaesthetist(s) from the previous list were not in this upload.";
        }

        return redirect()->route('anaesthetists.index')->with('success', $message);
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
