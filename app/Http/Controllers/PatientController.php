<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Ward;
use Illuminate\Http\Request;
use App\Services\EkadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Status tabs on the patient list: tab key => [label, matching statuses]
     */
    private const STATUS_TABS = [
        'all' => ['All Patients', []],
        'admitted' => ['Admitted', [Patient::STATUS_ADMITTED]],
        'pending_discharge' => ['Pending Discharge', [Patient::STATUS_PENDING_DISCHARGE]],
        'prebook' => ['Prebook', [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING]],
        'discharged' => ['Discharged', [Patient::STATUS_DISCHARGED, Patient::STATUS_CANCELLED]],
    ];

    /**
     * Tabs on the patient view page => the sections each one shows.
     * "overview" shows every section side by side.
     */
    public const TAB_SECTIONS = [
        'overview' => ['details', 'admission', 'medical', 'payor', 'charges', 'care'],
        'details' => ['details'],
        'admission' => ['admission'],
        'medical' => ['medical'],
        'billing' => ['payor', 'charges'],
        'care' => ['care'],
    ];

    /**
     * Which tab an editable section lives on
     */
    private const SECTION_TABS = [
        'details' => 'details',
        'admission' => 'admission',
        'medical' => 'medical',
        'payor' => 'billing',
        'charges' => 'billing',
    ];

    private const SORT_COLUMNS = [
        'latest' => ['created_at'],
        'name' => ['name'],
        'ward' => ['ward_id', 'bed_number'],
        'status' => ['status'],
        'admitted' => ['admitted_at'],
    ];

    public function index(Request $request)
    {
        $search = is_string($request->input('search')) ? trim($request->input('search')) : '';
        $status = isset(self::STATUS_TABS[$request->input('status')]) ? $request->input('status') : 'all';
        $ward = is_numeric($request->input('ward')) ? (int) $request->input('ward') : null;
        $sort = isset(self::SORT_COLUMNS[$request->input('sort')]) ? $request->input('sort') : 'latest';
        $direction = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        // Search and ward apply to both the rows and the tab counts; the status tab does not
        $base = Patient::query();

        if ($search !== '') {
            $base->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('alias_name', 'like', '%' . $search . '%')
                    ->orWhere('mrn', 'like', '%' . $search . '%')
                    ->orWhere('rn', 'like', '%' . $search . '%')
                    ->orWhere('ic_passport', 'like', '%' . $search . '%');
            });
        }

        if ($ward) {
            $base->where('ward_id', $ward);
        }

        $countsByStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = [];
        foreach (self::STATUS_TABS as $key => [$label, $statuses]) {
            $tabs[$key] = [
                'label' => $label,
                'count' => $statuses === []
                    ? $countsByStatus->sum()
                    : collect($statuses)->sum(fn ($value) => (int) $countsByStatus->get($value, 0)),
            ];
        }

        $query = (clone $base)->with('ward');

        if ($statuses = self::STATUS_TABS[$status][1]) {
            $query->whereIn('status', $statuses);
        }

        foreach (self::SORT_COLUMNS[$sort] as $column) {
            $query->orderBy($column, $direction);
        }

        return view('patients.index', [
            'patients' => $query->paginate(15)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'tabs' => $tabs,
            'ward' => $ward,
            'wards' => Ward::where('is_active', true)->orderBy('ward_name')->get(['id', 'ward_name']),
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('patients.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'alias_name' => 'nullable|string|max:255',
            'mrn' => 'required|string|unique:patients,mrn',
            'rn' => 'required|string|unique:patients,rn',
            'ic_passport' => 'required|string|unique:patients,ic_passport',
            'age' => 'required|integer|min:0|max:150',
            'gender' => 'required|in:Male,Female',
            'phone' => 'required|string',
        ]);

        Patient::create($validated);

        return redirect()->route('patients.index')->with('success', 'Patient created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Patient $patient)
    {
        $patient->load([
            'ward',
            'bed.nurse',
            'consultant.specialty',
            'nurse',
            'anaesthetist',
            'activeCareProviders.consultant.specialty',
            'activeCareProviders.anaesthetist',
        ]);

        // Suggested COE programmes plus any custom ones already in use
        $coeOptions = collect(Patient::COE_INDICATOR_SUGGESTIONS)
            ->merge(Patient::whereNotNull('coe_indicators')->pluck('coe_indicators')->flatten())
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Section to open in edit mode: after a failed save, or ?edit=section
        $openSection = old('_section', $request->query('edit'));
        $requestedTab = $request->query('tab');

        return view('patients.show', [
            'patient' => $patient,
            'summary' => $this->buildSummary($patient),
            'coeOptions' => $coeOptions,
            'openSection' => $openSection,
            'initialTab' => isset(self::TAB_SECTIONS[$requestedTab])
                ? $requestedTab
                : (self::SECTION_TABS[$openSection] ?? 'overview'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Patient $patient)
    {
        // Editing happens section by section on the patient view page
        return redirect()->route('patients.show', ['patient' => $patient, 'tab' => 'details', 'edit' => 'details']);
    }

    /**
     * Update the specified resource in storage.
     *
     * The view page saves one section at a time (_section); only that section's fields are validated and written.
     */
    public function update(Request $request, Patient $patient)
    {
        $section = $request->input('_section', 'details');
        $amount = 'nullable|numeric|min:0|max:9999999999.99';

        $rules = match ($section) {
            'details' => [
                'name' => 'required|string|max:255',
                'alias_name' => 'nullable|string|max:255',
                'mrn' => 'required|string|unique:patients,mrn,' . $patient->id,
                'rn' => 'required|string|unique:patients,rn,' . $patient->id,
                'ic_passport' => 'required|string|unique:patients,ic_passport,' . $patient->id,
                'age' => 'required|integer|min:0|max:150',
                'gender' => 'required|in:Male,Female',
                'phone' => 'required|string',
                'date_of_birth' => 'nullable|date|before_or_equal:today',
                'race' => 'nullable|string|max:100',
                'religion' => 'nullable|string|max:100',
            ],
            'admission' => [
                'expected_discharge_at' => 'nullable|date',
                'estimated_length_of_stay' => 'nullable|integer|min:0|max:3650',
            ],
            'medical' => [
                'coe_indicators' => 'nullable|array',
                'coe_indicators.*' => 'nullable|string|max:100',
            ],
            'payor' => [
                'payor_type' => ['nullable', Rule::in(array_keys(Patient::PAYOR_TYPES))],
                'payor_name' => 'nullable|string|max:255',
                'payor_policy_number' => 'nullable|string|max:100',
                'payor_gl_number' => 'nullable|string|max:100',
                'payor_gl_amount' => $amount,
                'payor_status' => ['nullable', Rule::in(array_keys(Patient::PAYOR_STATUSES))],
                'payor_remarks' => 'nullable|string|max:2000',
            ],
            'charges' => [
                'total_charges' => $amount,
                'deposit_paid' => $amount,
            ],
            default => abort(422, 'Unknown patient section.'),
        };

        $validated = $request->validate($rules);

        if ($section === 'medical') {
            $coe = collect($validated['coe_indicators'] ?? [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $validated['coe_indicators'] = $coe ?: null;
        }

        $patient->fill($validated);
        if ($section === 'charges' && $patient->isDirty(['total_charges', 'deposit_paid'])) {
            $patient->charges_updated_at = now();
        }
        $patient->save();

        // EKad: Explicitly push update since PatientObserver logic is disabled
        if ($section === 'details' && $patient->isAdmitted() && $patient->bed) {
            try {
                $ekadService = new EkadService();
                if ($ekadService->isAutoPushEnabled()) {
                    $ekadService->pushPatientInfo($patient, $patient->bed, [], 'Info Update');
                }
            } catch (\Exception $e) {
                Log::warning('EKad Info Update Failed', ['error' => $e->getMessage()]);
            }
        }

        $messages = [
            'details' => 'Patient details updated successfully.',
            'admission' => 'Admission details updated successfully.',
            'medical' => 'Medical info updated successfully.',
            'payor' => 'Payor / insurance updated successfully.',
            'charges' => 'Charges updated successfully.',
        ];

        return redirect()
            ->route('patients.show', ['patient' => $patient, 'tab' => self::SECTION_TABS[$section]])
            ->with('success', $messages[$section]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient)
    {
        $patient->delete();
        return redirect()->route('patients.index')->with('success', 'Patient deleted successfully.');
    }

    /**
     * Toggle the active status of the patient.
     */
    public function deactivate(Patient $patient)
    {
        $patient->update(['is_active' => !$patient->is_active]);
        $status = $patient->is_active ? 'activated' : 'deactivated';
        return redirect()->route('patients.index')->with('success', "Patient {$status} successfully.");
    }

    /**
     * Derived stay and billing figures for the patient view page.
     */
    private function buildSummary(Patient $patient): array
    {
        $losMinutes = $patient->lengthOfStayMinutes();

        // Without an expected discharge date, project one from the estimated length of stay
        $expectedDischarge = $patient->expected_discharge_at;
        $expectedIsProjected = false;
        if (!$expectedDischarge && $patient->admitted_at && $patient->estimated_length_of_stay) {
            $expectedDischarge = $patient->admitted_at->copy()->addDays((int) $patient->estimated_length_of_stay);
            $expectedIsProjected = true;
        }

        $expectedRelative = null;
        if ($expectedDischarge && $patient->isAdmitted()) {
            $days = (int) round(now()->startOfDay()->diffInDays($expectedDischarge->copy()->startOfDay(), false));
            $expectedRelative = match (true) {
                $days === 0 => 'Today',
                $days === 1 => 'Tomorrow',
                $days > 1 => "In {$days} days",
                $days === -1 => 'Overdue by 1 day',
                default => 'Overdue by ' . abs($days) . ' days',
            };
        }

        $total = $patient->total_charges !== null ? (float) $patient->total_charges : null;
        $deposit = (float) ($patient->deposit_paid ?? 0);
        $glAmount = $patient->payor_gl_amount !== null ? (float) $patient->payor_gl_amount : null;

        // Only an approved guarantee letter counts towards what the payor covers
        $glApproved = in_array($patient->payor_status, ['approved', 'partial'], true);
        $coverage = ($glApproved && $glAmount !== null && $total !== null) ? min($total, $glAmount) : 0.0;

        return [
            'los_days' => $losMinutes !== null ? intdiv($losMinutes, 1440) : null,
            'los_hours' => $losMinutes !== null ? intdiv($losMinutes % 1440, 60) : null,
            'expected_discharge' => $expectedDischarge,
            'expected_is_projected' => $expectedIsProjected,
            'expected_relative' => $expectedRelative,
            'charges' => [
                'total' => $total,
                'deposit' => $deposit,
                'gl_amount' => $glAmount,
                'gl_approved' => $glApproved,
                'coverage' => $coverage,
                'balance' => $total !== null ? $total - $coverage - $deposit : null,
                'gl_usage_pct' => ($glAmount && $total !== null) ? (int) round($total / $glAmount * 100) : null,
                'per_day' => ($total !== null && $losMinutes) ? $total / max(1, (int) ceil($losMinutes / 1440)) : null,
            ],
        ];
    }
}
