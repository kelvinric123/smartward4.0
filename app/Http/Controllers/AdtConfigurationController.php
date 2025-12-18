<?php

namespace App\Http\Controllers;

use App\Models\AdtConfiguration;
use App\Models\AdtHospitalMapping;
use App\Models\AdtWardMapping;
use App\Models\AdtBedMapping;
use App\Models\AdtDoctorMapping;
use App\Models\AdtDietMapping;
use App\Models\AdtIsolationMapping;
use App\Models\AdtMessageLog;
use App\Models\Hospital;
use App\Models\Ward;
use App\Models\Bed;
use App\Models\Consultant;
use App\Models\Nurse;
use App\Models\Anaesthetist;
use App\Models\Patient;
use App\Models\DietType;
use App\Models\IsolationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdtConfigurationController extends Controller
{
    /**
     * Display the ADT Configuration page.
     */
    public function index(Request $request)
    {
        // Get or create default configuration
        $configuration = AdtConfiguration::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Default ADT Configuration',
                'listener_host' => '0.0.0.0',
                'listener_port' => 3000,
                'is_active' => true,
                'auto_admit' => true,
                'auto_discharge' => true,
                'auto_transfer' => true,
            ]
        );

        // Get mappings with relationships
        $hospitalMappings = AdtHospitalMapping::with('hospital')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $wardMappings = AdtWardMapping::with('ward.hospital')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $bedMappings = AdtBedMapping::with('bed.ward')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $doctorMappings = AdtDoctorMapping::with('consultant')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $dietMappings = AdtDietMapping::where('adt_configuration_id', $configuration->id)->get();
        $isolationMappings = AdtIsolationMapping::where('adt_configuration_id', $configuration->id)->get();

        // Build query for recent logs with filters
        $logsQuery = AdtMessageLog::with(['patient', 'bed.ward']);
        
        // Apply filters
        if ($request->filled('status')) {
            $logsQuery->where('status', $request->status);
        }
        
        if ($request->filled('event_type')) {
            $logsQuery->where('event_type', $request->event_type);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $logsQuery->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mrn', 'like', "%{$search}%")
                    ->orWhere('patient_id', 'like', "%{$search}%")
                    ->orWhere('message_control_id', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('date_from')) {
            $logsQuery->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $logsQuery->whereDate('created_at', '<=', $request->date_to);
        }
        
        $recentLogs = $logsQuery->latest()->limit(100)->get();

        // Get statistics
        $stats = [
            'total_messages' => AdtMessageLog::count(),
            'processed' => AdtMessageLog::where('status', 'processed')->count(),
            'failed' => AdtMessageLog::where('status', 'failed')->count(),
            'unmapped' => AdtMessageLog::where('status', 'unmapped')->count(),
            'today' => AdtMessageLog::whereDate('created_at', today())->count(),
        ];

        // Get available resources for mapping
        $hospitals = Hospital::where('is_active', true)->orderBy('name')->get();
        $wards = Ward::with('hospital')->where('is_active', true)->orderBy('ward_name')->get();
        $beds = Bed::with('ward.hospital')->where('is_active', true)->orderBy('bed_number')->get();
        $consultants = Consultant::where('is_active', true)->orderBy('name')->get();

        // Event types for filter
        $eventTypes = AdtMessageLog::EVENT_TYPES;
        $doctorTypes = AdtDoctorMapping::TYPES;

        return view('integration.adt.index', compact(
            'configuration',
            'hospitalMappings',
            'wardMappings',
            'bedMappings',
            'doctorMappings',
            'dietMappings',
            'isolationMappings',
            'recentLogs',
            'stats',
            'hospitals',
            'wards',
            'beds',
            'consultants',
            'eventTypes',
            'doctorTypes'
        ));
    }

    /**
     * Render mappings-only view (for iframe).
     */
    public function mappingsFrame()
    {
        $configuration = AdtConfiguration::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Default ADT Configuration',
                'listener_host' => '0.0.0.0',
                'listener_port' => 3000,
                'is_active' => true,
                'auto_admit' => true,
                'auto_discharge' => true,
                'auto_transfer' => true,
            ]
        );

        $hospitalMappings = AdtHospitalMapping::with('hospital')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $wardMappings = AdtWardMapping::with('ward.hospital')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $bedMappings = AdtBedMapping::with('bed.ward')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $doctorMappings = AdtDoctorMapping::with('consultant')
            ->where('adt_configuration_id', $configuration->id)
            ->get();

        $dietMappings = AdtDietMapping::where('adt_configuration_id', $configuration->id)->get();
        $isolationMappings = AdtIsolationMapping::where('adt_configuration_id', $configuration->id)->get();

        $hospitals = Hospital::where('is_active', true)->orderBy('name')->get();
        $wards = Ward::with('hospital')->where('is_active', true)->orderBy('ward_name')->get();
        $beds = Bed::with('ward.hospital')->where('is_active', true)->orderBy('bed_number')->get();
        $consultants = Consultant::where('is_active', true)->orderBy('name')->get();
        $doctorTypes = AdtDoctorMapping::TYPES;

        return view('integration.adt.mappings', compact(
            'configuration',
            'hospitalMappings',
            'wardMappings',
            'bedMappings',
            'doctorMappings',
            'dietMappings',
            'isolationMappings',
            'hospitals',
            'wards',
            'beds',
            'consultants',
            'doctorTypes'
        ));
    }

    /**
     * Update ADT configuration settings.
     */
    public function updateConfiguration(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'listener_host' => 'required|string|max:255',
            'listener_port' => 'required|integer|min:1|max:65535',
            'auto_admit' => 'boolean',
            'auto_discharge' => 'boolean',
            'auto_transfer' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        $validated['auto_admit'] = $request->has('auto_admit');
        $validated['auto_discharge'] = $request->has('auto_discharge');
        $validated['auto_transfer'] = $request->has('auto_transfer');
        $validated['is_active'] = $request->has('is_active');

        $configuration->update($validated);

        return redirect()->route('adt.index')
            ->with('success', 'ADT configuration updated successfully.');
    }

    /**
     * Store a new hospital mapping.
     */
    public function storeHospitalMapping(Request $request)
    {
        $validated = $request->validate([
            'adt_hospital_code' => 'required|string|max:255',
            'adt_hospital_name' => 'nullable|string|max:255',
            'hospital_id' => 'required|exists:hospitals,id',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        // Check for duplicate
        $existing = AdtHospitalMapping::where('adt_configuration_id', $configuration->id)
            ->where('adt_hospital_code', $validated['adt_hospital_code'])
            ->exists();

        if ($existing) {
            return redirect()->route('adt.index')
                ->with('error', 'A mapping for this ADT hospital code already exists.');
        }

        AdtHospitalMapping::create([
            'adt_configuration_id' => $configuration->id,
            ...$validated,
        ]);

        return redirect()->route('adt.index')
            ->with('success', 'Hospital mapping created successfully.');
    }

    /**
     * Delete a hospital mapping.
     */
    public function destroyHospitalMapping(AdtHospitalMapping $hospitalMapping)
    {
        $hospitalMapping->delete();

        return redirect()->route('adt.index')
            ->with('success', 'Hospital mapping deleted successfully.');
    }

    /**
     * Store a new ward mapping.
     */
    public function storeWardMapping(Request $request)
    {
        $validated = $request->validate([
            'adt_ward_code' => 'required|string|max:255',
            'adt_ward_name' => 'nullable|string|max:255',
            'ward_id' => 'required|exists:wards,id',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        // Check for duplicate
        $existing = AdtWardMapping::where('adt_configuration_id', $configuration->id)
            ->where('adt_ward_code', $validated['adt_ward_code'])
            ->exists();

        if ($existing) {
            return redirect()->route('adt.index')
                ->with('error', 'A mapping for this ADT ward code already exists.');
        }

        AdtWardMapping::create([
            'adt_configuration_id' => $configuration->id,
            ...$validated,
        ]);

        return redirect()->route('adt.index')
            ->with('success', 'Ward mapping created successfully.');
    }

    /**
     * Delete a ward mapping.
     */
    public function destroyWardMapping(AdtWardMapping $wardMapping)
    {
        $wardMapping->delete();

        return redirect()->route('adt.index')
            ->with('success', 'Ward mapping deleted successfully.');
    }

    /**
     * Store a new bed mapping.
     */
    public function storeBedMapping(Request $request)
    {
        $validated = $request->validate([
            'adt_bed_code' => 'required|string|max:255',
            'adt_bed_name' => 'nullable|string|max:255',
            'bed_id' => 'required|exists:beds,id',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        // Check for duplicate
        $existing = AdtBedMapping::where('adt_configuration_id', $configuration->id)
            ->where('adt_bed_code', $validated['adt_bed_code'])
            ->exists();

        if ($existing) {
            return redirect()->route('adt.index')
                ->with('error', 'A mapping for this ADT bed code already exists.');
        }

        AdtBedMapping::create([
            'adt_configuration_id' => $configuration->id,
            ...$validated,
        ]);

        return redirect()->route('adt.index')
            ->with('success', 'Bed mapping created successfully.');
    }

    /**
     * Delete a bed mapping.
     */
    public function destroyBedMapping(AdtBedMapping $bedMapping)
    {
        $bedMapping->delete();

        return redirect()->route('adt.index')
            ->with('success', 'Bed mapping deleted successfully.');
    }

    /**
     * Store a new doctor mapping.
     */
    public function storeDoctorMapping(Request $request)
    {
        $validated = $request->validate([
            'adt_doctor_code' => 'required|string|max:255',
            'adt_doctor_name' => 'nullable|string|max:255',
            'doctor_type' => 'required|string|in:attending,referring,consulting,admitting',
            'consultant_id' => 'required|exists:consultants,id',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        // Check for duplicate
        $existing = AdtDoctorMapping::where('adt_configuration_id', $configuration->id)
            ->where('adt_doctor_code', $validated['adt_doctor_code'])
            ->where('doctor_type', $validated['doctor_type'])
            ->exists();

        if ($existing) {
            return redirect()->route('adt.index')
                ->with('error', 'A mapping for this ADT doctor code and type already exists.');
        }

        AdtDoctorMapping::create([
            'adt_configuration_id' => $configuration->id,
            ...$validated,
        ]);

        return redirect()->route('adt.index')
            ->with('success', 'Doctor mapping created successfully.');
    }

    public function storeDietMapping(Request $request)
    {
        $validated = $request->validate([
            'adt_diet_code' => 'required|string|max:255',
            'adt_diet_name' => 'nullable|string|max:255',
            'mapped_diet' => 'nullable|string|max:255',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        $existing = AdtDietMapping::where('adt_configuration_id', $configuration->id)
            ->where('adt_diet_code', $validated['adt_diet_code'])
            ->exists();

        if ($existing) {
            return redirect()->route('adt.index')
                ->with('error', 'A mapping for this ADT diet code already exists.');
        }

        AdtDietMapping::create([
            'adt_configuration_id' => $configuration->id,
            ...$validated,
            'is_active' => true,
        ]);

        return redirect()->route('adt.index')
            ->with('success', 'Diet mapping created successfully.');
    }

    public function destroyDietMapping(AdtDietMapping $dietMapping)
    {
        $dietMapping->delete();

        return redirect()->route('adt.index')
            ->with('success', 'Diet mapping deleted successfully.');
    }

    public function storeIsolationMapping(Request $request)
    {
        $validated = $request->validate([
            'adt_isolation_code' => 'required|string|max:255',
            'adt_isolation_name' => 'nullable|string|max:255',
            'mapped_isolation' => 'nullable|string|max:255',
        ]);

        $configuration = AdtConfiguration::firstOrFail();

        $existing = AdtIsolationMapping::where('adt_configuration_id', $configuration->id)
            ->where('adt_isolation_code', $validated['adt_isolation_code'])
            ->exists();

        if ($existing) {
            return redirect()->route('adt.index')
                ->with('error', 'A mapping for this ADT isolation code already exists.');
        }

        AdtIsolationMapping::create([
            'adt_configuration_id' => $configuration->id,
            ...$validated,
            'is_active' => true,
        ]);

        return redirect()->route('adt.index')
            ->with('success', 'Isolation mapping created successfully.');
    }

    public function destroyIsolationMapping(AdtIsolationMapping $isolationMapping)
    {
        $isolationMapping->delete();

        return redirect()->route('adt.index')
            ->with('success', 'Isolation mapping deleted successfully.');
    }

    /**
     * Delete a doctor mapping.
     */
    public function destroyDoctorMapping(AdtDoctorMapping $doctorMapping)
    {
        $doctorMapping->delete();

        return redirect()->route('adt.index')
            ->with('success', 'Doctor mapping deleted successfully.');
    }

    /**
     * Get message logs via AJAX.
     */
    public function getLogs(Request $request)
    {
        $query = AdtMessageLog::with(['patient', 'bed.ward']);

        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->event_type) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mrn', 'like', "%{$search}%")
                    ->orWhere('patient_id', 'like', "%{$search}%")
                    ->orWhere('message_control_id', 'like', "%{$search}%");
            });
        }

        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest()->paginate(50);

        return response()->json($logs);
    }

    /**
     * Clear message logs.
     */
    public function clearLogs(Request $request)
    {
        $query = AdtMessageLog::query();

        if ($request->older_than_days) {
            $query->where('created_at', '<', now()->subDays($request->older_than_days));
        }

        $deleted = $query->delete();

        return redirect()->route('adt.index')
            ->with('success', "Cleared {$deleted} log entries.");
    }

    /**
     * View a specific log entry.
     */
    public function viewLog(AdtMessageLog $log)
    {
        $log->load(['patient', 'bed.ward', 'adtConfiguration']);

        return response()->json($log);
    }

    /**
     * Test ADT listener connection status.
     */
    public function testConnection()
    {
        $configuration = AdtConfiguration::first();

        if (!$configuration) {
            return response()->json([
                'success' => false,
                'message' => 'No ADT configuration found.',
            ]);
        }

        // Determine the host to connect to:
        // 1. If ADT_HOST env is set (Docker), use that (connects to the 'adt' service container)
        // 2. Otherwise, use listener_host from config, defaulting 0.0.0.0 to 127.0.0.1
        $adtHost = config('services.adt.host');
        $adtPort = config('services.adt.port') ?: $configuration->listener_port;
        
        if ($adtHost) {
            // Docker environment - use the configured ADT service host
            $connectHost = $adtHost;
            $connectPort = $adtPort;
        } else {
            // Local development - use the configured listener settings
            $connectHost = $configuration->listener_host === '0.0.0.0' ? '127.0.0.1' : $configuration->listener_host;
            $connectPort = $configuration->listener_port;
        }

        // Try to connect to the listener port
        $socket = @fsockopen(
            $connectHost,
            $connectPort,
            $errno,
            $errstr,
            2 // 2 second timeout
        );

        if ($socket) {
            fclose($socket);
            return response()->json([
                'success' => true,
                'message' => "ADT Listener is running on port {$configuration->listener_port}",
                'host' => $configuration->listener_host,
                'port' => $configuration->listener_port,
                'connected_to' => "{$connectHost}:{$connectPort}",
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Cannot connect to ADT Listener. Error: {$errstr}",
            'host' => $configuration->listener_host,
            'port' => $configuration->listener_port,
            'tried_connecting_to' => "{$connectHost}:{$connectPort}",
        ]);
    }

    /**
     * Read log files from the HL7 listener.
     */
    public function readLogFiles()
    {
        $logDir = base_path('HL7/logs');
        $logs = [];

        // Read the main log file
        $allLogFile = $logDir . '/hl7_adt_all.log';
        if (file_exists($allLogFile)) {
            // Read last 100 lines
            $lines = file($allLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logs['all'] = array_slice($lines, -100);
        }

        // Read error log
        $errorLogFile = $logDir . '/hl7_errors.log';
        if (file_exists($errorLogFile)) {
            $lines = file($errorLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logs['errors'] = array_slice($lines, -50);
        }

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Display the ADT Test page.
     */
    public function testPage()
    {
        $configuration = AdtConfiguration::first();
        
        // Determine the default host for the test form
        // In Docker, use ADT_HOST env var (service name 'adt')
        // Otherwise, use the configuration or fall back to localhost
        $adtHost = config('services.adt.host');
        if ($adtHost) {
            $defaultHost = $adtHost;
        } elseif ($configuration && $configuration->listener_host && $configuration->listener_host !== '0.0.0.0') {
            $defaultHost = $configuration->listener_host;
        } else {
            $defaultHost = 'localhost';
        }
        
        $defaultPort = config('services.adt.port') ?: ($configuration->listener_port ?? 3000);
        
        // Sample ADT messages based on adt_sample_sender.py
        $sampleMessages = [
            'A01' => [
                'name' => 'ADT^A01 - Admit/Register Patient',
                'description' => 'Creates a new patient visit and assigns the patient to the designated location/ward.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117080000||ADT^A01^ADT_A01|50690.0|T|2.4\r\nEVN|A01|20251117080000||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWC7^C706^C706|||WWC7^WARD C7 (EXECUTIVE WARD)|DALEXLHR^ALEX LEOW HWONG RUEY|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||||20251117080000|\r\nNK1|1||||||||||||||||||||||||||||||||||||||",
            ],
            'A02' => [
                'name' => 'ADT^A02 - Transfer Patient',
                'description' => 'Transfers a patient to another ward, room, or bed.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117100000||ADT^A02^ADT_A02|50691.0|T|2.4\r\nEVN|A02|20251117100000|||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||||20251117080000|||||||",
            ],
            'A03' => [
                'name' => 'ADT^A03 - Discharge/End Visit',
                'description' => 'Indicates the patient has been discharged and the visit is considered closed.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117160000||ADT^A03^ADT_A03|50695.0|T|2.4\r\nEVN|A03|20251117160000|||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^|||||||||||||||||||||||||||20251117080000|20251117160000||||||||",
            ],
            'A08' => [
                'name' => 'ADT^A08 - Update Patient Details',
                'description' => 'Updates patient demographics, contact details, diet, or isolation status.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117120000||ADT^A08^ADT_A08|50692.0|T|2.4\r\nEVN|A08|20251117120000|||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||DMD, REGD^DIABETIC DIET, REGULAR DIET|||||||||\r\nNK1|1|^AHMAD|40^Son|Test^^Ayer Hitam^Johor^N/A^MYS|60123456789||||||||||||||||||||||||||||||||||||111103149999\r\nRMI|0|||CI^Contact Isolation||||||||||||||||||||||||||||||||||",
            ],
            'A11' => [
                'name' => 'ADT^A11 - Cancel Admit/Cancel Visit',
                'description' => 'Reverses a previously sent A01 message.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117180000||ADT^A11^ADT_A11|50697.0|T|2.4\r\nEVN|A11|20251117180000||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWC7^C706^C706|||WWC7^WARD C7 (EXECUTIVE WARD)|DALEXLHR^ALEX LEOW HWONG RUEY|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||||||||||||",
            ],
            'A13' => [
                'name' => 'ADT^A13 - Cancel Discharge',
                'description' => 'Reopens a visit by cancelling a prior discharge event.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117170000||ADT^A13^ADT_A13|50696.0|T|2.4\r\nEVN|A13|20251117170000|||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||20251117080000||||||||",
            ],
            'A16' => [
                'name' => 'ADT^A16 - Pending Discharge',
                'description' => 'Indicates the patient is awaiting discharge.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117140000||ADT^A16^ADT_A16|50693.0|T|2.4\r\nEVN|A16|20251117140000|||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||20251117080000||||||||",
            ],
            'A25' => [
                'name' => 'ADT^A25 - Cancel Pending Discharge',
                'description' => 'Reverses a pending discharge previously flagged by an A16.',
                'message' => "MSH|^~\\&|CEREBRALPLUS|PHKL|IWARD|IWARD|20251117143000||ADT^A25^ADT_A25|50694.0|T|2.4\r\nEVN|A25|20251117143000||||\r\nPID|1||3300746940^^^^MR|PP^N7356938|TEST SST PATIENT||19920409|M||00|Test^^Ayer Hitam^Johor^N/A^MYS|MYS|0^06128764|||0|99||||||||||||IND|\r\nPV1|1|I|WWD6^D610^D610|||WWD6^WARD D6 (MEDICAL \\& SURGICAL)|DKAMJIT^KAMALJIT KAUR D/O HARBAN SINGH|||||||||||||PHKL25IP11000009|15^4^1^C000020027~15^1^99^||||||||||||||||||||||||||20251117080000|||||||||",
            ],
        ];

        // Get diet types and isolation types for A08 dropdowns
        $dietTypes = DietType::where('is_active', true)->orderBy('name')->get();
        $isolationTypes = IsolationType::where('is_active', true)->orderBy('name')->get();

        // Get wards with their beds for location dropdowns (with error handling)
        try {
            $wards = Ward::where('is_active', true)
                ->orderBy('ward_name')
                ->get();
        } catch (\Exception $e) {
            $wards = collect();
        }
        
        // Get beds with ward information for cascading dropdown
        try {
            $beds = Bed::with('ward')
                ->where('is_active', true)
                ->orderBy('bed_number')
                ->get()
                ->map(function ($bed) {
                    return [
                        'id' => $bed->id,
                        'ward_id' => $bed->ward_id,
                        'bed_number' => $bed->bed_number,
                        'bed_id' => $bed->bed_id ?? $bed->bed_number,
                        'bed_display_name' => $bed->bed_display_name ?? $bed->bed_number,
                        'ward_code' => $bed->ward->ward_code ?? '',
                    ];
                });
        } catch (\Exception $e) {
            $beds = collect();
        }

        // Get consultants (doctors) for attending physician dropdown
        try {
            $consultants = Consultant::where('is_active', true)
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            $consultants = collect();
        }

        // Get nurses for nurse dropdown
        try {
            $nurses = Nurse::where('is_active', true)
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            $nurses = collect();
        }

        // Get anaesthetists for anaesthetist dropdown
        try {
            $anaesthetists = Anaesthetist::where('is_active', true)
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            $anaesthetists = collect();
        }

        // Get patients for patient selection dropdown
        try {
            $patients = Patient::with(['ward', 'bed', 'consultant', 'nurse', 'anaesthetist'])
                ->whereIn('status', [Patient::STATUS_ADMITTED, Patient::STATUS_PENDING_DISCHARGE, Patient::STATUS_PREBOOK])
                ->orderBy('name')
                ->get()
                ->map(function ($patient) {
                    // Format address for HL7
                    $addressStr = '';
                    if ($patient->address) {
                        if (is_array($patient->address)) {
                            $addr = $patient->address;
                            $parts = array_filter([
                                $addr['street'] ?? '',
                                $addr['city'] ?? '',
                                $addr['state'] ?? '',
                                $addr['postal_code'] ?? '',
                                $addr['country'] ?? 'MYS'
                            ]);
                            $addressStr = implode('^', $parts);
                        } else {
                            $addressStr = $patient->address;
                        }
                    }
                    
                    return [
                        'id' => $patient->id,
                        'mrn' => $patient->mrn ?? '',
                        'rn' => $patient->rn ?? '',
                        'ic_passport' => $patient->ic_passport ?? '',
                        'name' => $patient->name ?? '',
                        'date_of_birth' => $patient->date_of_birth ? $patient->date_of_birth->format('Ymd') : '',
                        'gender' => $patient->gender ?? 'U',
                        'phone' => $patient->phone ?? '',
                        'address' => $addressStr,
                        'race' => $patient->race ?? '',
                        'visit_number' => $patient->visit_number ?? '',
                        'status' => $patient->status,
                        'ward_id' => $patient->ward_id,
                        'ward_code' => $patient->ward->ward_code ?? '',
                        'ward_name' => $patient->ward->ward_name ?? '',
                        'bed_code' => $patient->bed->bed_id ?? $patient->bed_number ?? '',
                        'consultant_code' => $patient->consultant->personnel_code ?? '',
                        'consultant_name' => $patient->consultant->name ?? '',
                        'nurse_code' => $patient->nurse->personnel_code ?? '',
                        'nurse_name' => $patient->nurse->name ?? '',
                        'anaesthetist_code' => $patient->anaesthetist->personnel_code ?? '',
                        'anaesthetist_name' => $patient->anaesthetist->name ?? '',
                        'admitted_at' => $patient->admitted_at ? $patient->admitted_at->format('YmdHis') : '',
                    ];
                });
        } catch (\Exception $e) {
            $patients = collect();
        }

        return view('integration.adt.test', compact(
            'configuration', 
            'sampleMessages', 
            'defaultHost', 
            'defaultPort', 
            'dietTypes', 
            'isolationTypes',
            'wards',
            'beds',
            'consultants',
            'nurses',
            'anaesthetists',
            'patients'
        ));
    }

    /**
     * Send a test ADT message to the HL7 listener.
     * Fire and forget - don't wait for ACK response.
     * Check ADT Config logs for results.
     */
    public function sendTestMessage(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer|min:1|max:65535',
            'message' => 'required|string',
        ]);

        $host = $validated['host'];
        $port = $validated['port'];
        $message = $validated['message'];

        // MLLP framing constants
        $MLLP_START_BLOCK = chr(0x0B); // VT (Vertical Tab)
        $MLLP_END_BLOCK = chr(0x1C);   // FS (File Separator)
        $MLLP_CARRIAGE_RETURN = chr(0x0D); // CR

        // Normalize line endings to \r (carriage return) as per HL7 standard
        $message = str_replace(["\r\n", "\n"], "\r", $message);

        // Create MLLP wrapped message
        $mllpMessage = $MLLP_START_BLOCK . $message . $MLLP_END_BLOCK . $MLLP_CARRIAGE_RETURN;

        try {
            // Create socket connection
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);

            if (!$socket) {
                return response()->json([
                    'success' => false,
                    'message' => "Connection failed: {$errstr} (Error {$errno}). Host: {$host}:{$port}",
                    'error' => $errstr,
                ], 400);
            }

            // Set blocking mode for reliable transmission
            stream_set_blocking($socket, true);

            // Send the MLLP message
            $bytesSent = fwrite($socket, $mllpMessage);
            
            if ($bytesSent === false) {
                fclose($socket);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message to listener',
                    'error' => 'Write failed',
                ], 500);
            }

            // Flush the output buffer to ensure data is sent
            fflush($socket);

            // Wait a moment for the data to be transmitted and processed
            // This prevents the connection from being closed before the receiver processes the data
            usleep(100000); // 100ms delay

            // Close the connection
            fclose($socket);

            return response()->json([
                'success' => true,
                'message' => 'Message sent to listener. Check ADT Config logs for processing result.',
                'bytes_sent' => $bytesSent,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending message: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}












