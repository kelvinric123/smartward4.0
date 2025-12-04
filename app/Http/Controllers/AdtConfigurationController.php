<?php

namespace App\Http\Controllers;

use App\Models\AdtConfiguration;
use App\Models\AdtHospitalMapping;
use App\Models\AdtWardMapping;
use App\Models\AdtBedMapping;
use App\Models\AdtMessageLog;
use App\Models\Hospital;
use App\Models\Ward;
use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdtConfigurationController extends Controller
{
    /**
     * Display the ADT Configuration page.
     */
    public function index()
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

        // Get recent logs
        $recentLogs = AdtMessageLog::with(['patient', 'bed'])
            ->latest()
            ->limit(50)
            ->get();

        // Get statistics
        $stats = [
            'total_messages' => AdtMessageLog::count(),
            'processed' => AdtMessageLog::where('status', 'processed')->count(),
            'failed' => AdtMessageLog::where('status', 'failed')->count(),
            'today' => AdtMessageLog::whereDate('created_at', today())->count(),
        ];

        // Get available resources for mapping
        $hospitals = Hospital::where('is_active', true)->orderBy('name')->get();
        $wards = Ward::with('hospital')->where('is_active', true)->orderBy('ward_name')->get();
        $beds = Bed::with('ward.hospital')->where('is_active', true)->orderBy('bed_number')->get();

        // Event types for filter
        $eventTypes = AdtMessageLog::EVENT_TYPES;

        return view('integration.adt.index', compact(
            'configuration',
            'hospitalMappings',
            'wardMappings',
            'bedMappings',
            'recentLogs',
            'stats',
            'hospitals',
            'wards',
            'beds',
            'eventTypes'
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

        // Try to connect to the listener port
        $socket = @fsockopen(
            $configuration->listener_host === '0.0.0.0' ? '127.0.0.1' : $configuration->listener_host,
            $configuration->listener_port,
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
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Cannot connect to ADT Listener on port {$configuration->listener_port}. Error: {$errstr}",
            'host' => $configuration->listener_host,
            'port' => $configuration->listener_port,
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
}


