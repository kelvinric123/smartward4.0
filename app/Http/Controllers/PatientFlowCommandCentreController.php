<?php

namespace App\Http\Controllers;

use App\Models\PatientFlowCommandCentre;
use App\Models\Ward;
use App\Models\Patient;
use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PatientFlowCommandCentreController extends Controller
{
    public function index()
    {
        $commandCentres = PatientFlowCommandCentre::with('wards')->latest()->paginate(10);
        return view('admin.patient-flow-command-centres.index', compact('commandCentres'));
    }

    public function create()
    {
        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();
        return view('admin.patient-flow-command-centres.create', compact('wards'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'login_username' => 'required|string|max:255|unique:patient_flow_command_centres,login_username',
            'login_password' => 'required|string|min:6',
            'ward_ids' => 'required|array|min:1',
            'ward_ids.*' => 'exists:wards,id',
        ]);

        $commandCentre = PatientFlowCommandCentre::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'login_username' => $validated['login_username'],
            'login_password' => Hash::make($validated['login_password']),
            'settings' => PatientFlowCommandCentre::defaultSettings(),
            'is_active' => true,
        ]);

        $commandCentre->wards()->sync($validated['ward_ids']);

        return redirect()->route('patient-flow-command-centres.index')
            ->with('success', 'Patient Flow Command Centre created successfully.');
    }

    public function edit(PatientFlowCommandCentre $patient_flow_command_centre)
    {
        $commandCentre = $patient_flow_command_centre;
        $commandCentre->load('wards');
        $wards = Ward::where('is_active', true)->orderBy('ward_name')->get();
        $selectedWardIds = $commandCentre->wards->pluck('id')->toArray();

        return view('admin.patient-flow-command-centres.edit', compact('commandCentre', 'wards', 'selectedWardIds'));
    }

    public function update(Request $request, PatientFlowCommandCentre $patient_flow_command_centre)
    {
        $commandCentre = $patient_flow_command_centre;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'login_username' => 'required|string|max:255|unique:patient_flow_command_centres,login_username,' . $commandCentre->id,
            'login_password' => 'nullable|string|min:6',
            'ward_ids' => 'required|array|min:1',
            'ward_ids.*' => 'exists:wards,id',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'login_username' => $validated['login_username'],
        ];

        if (!empty($validated['login_password'])) {
            $updateData['login_password'] = Hash::make($validated['login_password']);
        }

        $commandCentre->update($updateData);
        $commandCentre->wards()->sync($validated['ward_ids']);

        return redirect()->route('patient-flow-command-centres.index')
            ->with('success', 'Patient Flow Command Centre updated successfully.');
    }

    public function toggleActive(PatientFlowCommandCentre $commandCentre)
    {
        $commandCentre->update(['is_active' => !$commandCentre->is_active]);
        $status = $commandCentre->is_active ? 'activated' : 'deactivated';

        return redirect()->route('patient-flow-command-centres.index')
            ->with('success', "Command Centre {$status} successfully.");
    }

    // ── Settings ──────────────────────────────────────────────────

    public function settings(PatientFlowCommandCentre $commandCentre)
    {
        $defaults = PatientFlowCommandCentre::defaultSettings();
        $current = array_merge($defaults, $commandCentre->settings ?? []);

        return view('admin.patient-flow-command-centres.settings', compact('commandCentre', 'current'));
    }

    public function updateSettings(Request $request, PatientFlowCommandCentre $commandCentre)
    {
        $settings = [
            'show_bed_details' => $request->boolean('show_bed_details'),
            'show_summary_cards' => $request->boolean('show_summary_cards'),
            'show_patient_name' => $request->boolean('show_patient_name'),
            'show_consultant' => $request->boolean('show_consultant'),
            'show_admitting_section' => $request->boolean('show_admitting_section'),
            'show_pending_discharge_section' => $request->boolean('show_pending_discharge_section'),
            'show_discharged_section' => $request->boolean('show_discharged_section'),
            'show_prebooked_section' => $request->boolean('show_prebooked_section'),
            'refresh_interval' => max(10, min(300, (int)$request->input('refresh_interval', 30))),
        ];

        $commandCentre->update(['settings' => $settings]);

        return redirect()->route('patient-flow-command-centres.settings', $commandCentre)
            ->with('success', 'Settings updated successfully.');
    }

    // ── Admin Dashboard (in-app layout) ───────────────────────────

    public function dashboard(PatientFlowCommandCentre $commandCentre)
    {
        $commandCentre->load('wards');
        $wardData = $this->getWardFlowData($commandCentre);

        return view('admin.patient-flow-command-centres.dashboard', compact('commandCentre', 'wardData'));
    }

    public function dashboardData(PatientFlowCommandCentre $commandCentre)
    {
        $wardData = $this->getWardFlowData($commandCentre);

        return response()->json([
            'wardData' => $wardData,
            'timestamp' => now()->format('d M Y, h:i:s A'),
        ]);
    }

    // ── Public (session auth) ─────────────────────────────────────

    public function showLogin()
    {
        if (session()->has('command_centre_id')) {
            return redirect()->route('command-centre.view', session('command_centre_id'));
        }
        return view('command-centre.login');
    }

    public function login(Request $request)
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string']);

        $commandCentre = PatientFlowCommandCentre::where('login_username', $request->username)
            ->where('is_active', true)->first();

        if (!$commandCentre || !Hash::check($request->password, $commandCentre->login_password)) {
            return back()->withErrors(['login' => 'Invalid credentials or command centre is inactive.'])->withInput();
        }

        session([
            'command_centre_id' => $commandCentre->id,
            'command_centre_name' => $commandCentre->name,
        ]);

        return redirect()->route('command-centre.view', $commandCentre->id);
    }

    public function logout()
    {
        session()->forget(['command_centre_id', 'command_centre_name']);
        return redirect()->route('command-centre.login');
    }

    public function publicDashboard($id)
    {
        if (!session()->has('command_centre_id') || session('command_centre_id') != $id) {
            return redirect()->route('command-centre.login');
        }

        $commandCentre = PatientFlowCommandCentre::where('id', $id)->where('is_active', true)->firstOrFail();
        $commandCentre->load('wards');
        $wardData = $this->getWardFlowData($commandCentre);
        $settings = array_merge(PatientFlowCommandCentre::defaultSettings(), $commandCentre->settings ?? []);

        return view('command-centre.dashboard', compact('commandCentre', 'wardData', 'settings'));
    }

    public function publicDashboardData($id)
    {
        if (!session()->has('command_centre_id') || session('command_centre_id') != $id) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $commandCentre = PatientFlowCommandCentre::where('id', $id)->where('is_active', true)->firstOrFail();
        $commandCentre->load('wards');
        $wardData = $this->getWardFlowData($commandCentre);

        return response()->json([
            'wardData' => $wardData,
            'timestamp' => now()->format('d M Y, h:i:s A'),
        ]);
    }

    // ── Data Gathering ────────────────────────────────────────────

    private function getWardFlowData(PatientFlowCommandCentre $commandCentre): array
    {
        $today = Carbon::today();
        $wardData = [];

        foreach ($commandCentre->wards as $ward) {
            $totalBeds = Bed::where('ward_id', $ward->id)->where('is_active', true)->count();

            $admittedPatients = Patient::where('ward_id', $ward->id)
                ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
                ->get();

            $occupiedBeds = $admittedPatients->count();
            $availableBeds = max(0, $totalBeds - $occupiedBeds);

            $admissionsToday = Patient::where('ward_id', $ward->id)
                ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE])
                ->whereDate('admitted_at', $today)->count();

            $pendingDischarge = Patient::where('ward_id', $ward->id)
                ->where('status', Patient::STATUS_PENDING_DISCHARGE)->count();

            $dischargedToday = Patient::where('ward_id', $ward->id)
                ->where('status', Patient::STATUS_DISCHARGED)
                ->whereDate('discharged_at', $today)->count();

            $prebooked = Patient::where('ward_id', $ward->id)
                ->whereIn('status', [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING])->count();

            $occupancyPercent = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100) : 0;

            // Bed-level detail: patients with their bed + consultant + status
            $bedDetails = [];

            // Admitted today (admitting)
            $admittingPatients = Patient::where('ward_id', $ward->id)
                ->where('status', Patient::STATUS_ADMITTED)
                ->whereDate('admitted_at', $today)
                ->with('consultant')
                ->get();
            foreach ($admittingPatients as $p) {
                $bedDetails[] = [
                    'bed_number' => $p->bed_number ?? '-',
                    'patient_name' => $p->name,
                    'mrn' => $p->mrn,
                    'consultant_name' => $p->consultant->name ?? '-',
                    'status' => 'Admitting',
                    'status_color' => 'blue',
                    'timestamp' => $p->admitted_at?->format('h:i A') ?? '-',
                ];
            }

            // Pending discharge
            $pendingPatients = Patient::where('ward_id', $ward->id)
                ->where('status', Patient::STATUS_PENDING_DISCHARGE)
                ->with('consultant')
                ->get();
            foreach ($pendingPatients as $p) {
                $bedDetails[] = [
                    'bed_number' => $p->bed_number ?? '-',
                    'patient_name' => $p->name,
                    'mrn' => $p->mrn,
                    'consultant_name' => $p->consultant->name ?? '-',
                    'status' => 'Pending Discharge',
                    'status_color' => 'amber',
                    'timestamp' => $p->pending_discharge_at?->format('h:i A') ?? '-',
                ];
            }

            // Discharged today
            $dischargedPatients = Patient::where('ward_id', $ward->id)
                ->where('status', Patient::STATUS_DISCHARGED)
                ->whereDate('discharged_at', $today)
                ->with('consultant')
                ->get();
            foreach ($dischargedPatients as $p) {
                $bedDetails[] = [
                    'bed_number' => $p->bed_number ?? '-',
                    'patient_name' => $p->name,
                    'mrn' => $p->mrn,
                    'consultant_name' => $p->consultant->name ?? '-',
                    'status' => 'Discharged',
                    'status_color' => 'teal',
                    'timestamp' => $p->discharged_at?->format('h:i A') ?? '-',
                ];
            }

            // Prebooked
            $prebookedPatients = Patient::where('ward_id', $ward->id)
                ->whereIn('status', [Patient::STATUS_PREBOOK, Patient::STATUS_PREBOOK_PENDING])
                ->with('consultant')
                ->get();
            foreach ($prebookedPatients as $p) {
                $bedDetails[] = [
                    'bed_number' => $p->target_bed_number ?? $p->bed_number ?? '-',
                    'patient_name' => $p->name,
                    'mrn' => $p->mrn,
                    'consultant_name' => $p->consultant->name ?? '-',
                    'status' => 'Prebooked',
                    'status_color' => 'purple',
                    'timestamp' => $p->booked_at?->format('h:i A') ?? '-',
                ];
            }

            $wardData[] = [
                'id' => $ward->id,
                'ward_name' => $ward->ward_name,
                'ward_code' => $ward->ward_code,
                'total_beds' => $totalBeds,
                'occupied_beds' => $occupiedBeds,
                'available_beds' => $availableBeds,
                'admissions_today' => $admissionsToday,
                'pending_discharge' => $pendingDischarge,
                'discharged_today' => $dischargedToday,
                'prebooked' => $prebooked,
                'occupancy_percent' => $occupancyPercent,
                'bed_details' => $bedDetails,
            ];
        }

        return $wardData;
    }
}
