<?php

namespace App\Http\Controllers;

use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Models\EkadResponseLog;
use App\Models\EkadTemplate;
use App\Models\Bed;
use App\Models\Ward;
use App\Services\EkadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EkadController extends Controller
{
    /**
     * SEEKINK Cloud API base URL
     */
    protected string $baseUrl = 'http://iot.seekink.com/cloud/prod-api';

    /**
     * Display the EKad integration page.
     */
    public function index(): View
    {
        $config = EkadConfiguration::getActive() ?? new EkadConfiguration([
            'base_url' => $this->baseUrl,
            'username' => env('EKAD_USERNAME', 'moe'),
            'password' => env('EKAD_PASSWORD', 'moe123456'),
            'template_id' => env('EKAD_TEMPLATE_ID', '2000477842757914624'),
        ]);

        $wards = Ward::with([
            'beds' => function ($query) {
                $query->orderBy('bed_number');
            }
        ])->orderBy('ward_name')->get();

        $bedMappings = EkadBedMapping::with('bed.ward')
            ->orderBy('created_at', 'desc')
            ->get();

        // Templates whose fields have been edited; the rest get the default fields
        $templates = EkadTemplate::orderBy('name')->orderBy('template_id')->get(['template_id', 'name', 'fields']);
        $templateSources = collect(EkadTemplate::SOURCES)
            ->map(fn (string $label, string $source) => ['source' => $source, 'label' => $label])
            ->values();
        $defaultFields = EkadTemplate::DEFAULT_FIELDS;

        return view('integration.ekad.index', compact('config', 'wards', 'bedMappings', 'templates', 'templateSources', 'defaultFields'));
    }

    /**
     * Save a SEEKINK template's fields: the field names the template was
     * designed with, and what SmartWard puts in each (EkadTemplate::SOURCES)
     */
    public function saveTemplate(Request $request, string $templateId)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'fields' => 'required|array|min:1|max:40',
            'fields.*.key' => 'required|string|max:64|distinct',
            'fields.*.source' => ['required', 'string', Rule::in(array_keys(EkadTemplate::SOURCES))],
            'fields.*.value' => 'nullable|string|max:255',
        ], [
            'fields.*.key.distinct' => 'Each field name can only be used once.',
        ]);

        $template = EkadTemplate::updateOrCreate(['template_id' => $templateId], [
            'name' => $validated['name'] ?? null,
            'fields' => collect($validated['fields'])
                ->map(fn (array $field) => ['key' => trim($field['key']), 'source' => $field['source']]
                    + ($field['source'] === 'text' ? ['value' => (string) ($field['value'] ?? '')] : []))
                ->values()
                ->all(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fields saved for template ' . ($template->name ?: $template->template_id),
            'template' => $template->only(['template_id', 'name', 'fields']),
        ]);
    }

    /**
     * Get current configuration
     */
    public function getConfiguration()
    {
        $config = EkadConfiguration::getActive();

        return response()->json([
            'success' => true,
            'config' => $config,
            'token_valid' => $config?->isTokenValid() ?? false,
        ]);
    }

    /**
     * Save configuration
     */
    public function saveConfiguration(Request $request)
    {
        $validated = $request->validate([
            'base_url' => 'required|string|url',
            'username' => 'required|string',
            'password' => 'required|string',
            'template_id' => 'required|string',
            'auto_push_enabled' => 'boolean',
            'mask_patient_name' => 'boolean',
            'mask_style' => 'in:partial,full,initials',
        ]);

        $config = EkadConfiguration::getActive();

        if ($config) {
            $config->update(array_merge($validated, ['is_active' => true]));
        } else {
            $config = EkadConfiguration::create(array_merge($validated, ['is_active' => true]));
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuration saved successfully',
            'config' => $config,
        ]);
    }

    /**
     * Test login to SEEKINK API and return token.
     */
    public function testLogin(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'base_url' => 'nullable|string',
        ]);

        $baseUrl = $validated['base_url'] ?? $this->baseUrl;

        // Create or update config
        $config = EkadConfiguration::getActive();
        if (!$config) {
            $config = EkadConfiguration::create([
                'base_url' => $baseUrl,
                'username' => $validated['username'],
                'password' => $validated['password'],
                'template_id' => env('EKAD_TEMPLATE_ID', '2000477842757914624'),
                'is_active' => true,
            ]);
        } else {
            $config->update([
                'base_url' => $baseUrl,
                'username' => $validated['username'],
                'password' => $validated['password'],
                'is_active' => true,  // Ensure config is active
            ]);
        }

        $service = new EkadService($config);
        $result = $service->login();

        return response()->json($result);
    }

    /**
     * Get bed mappings
     */
    public function getBedMappings()
    {
        $mappings = EkadBedMapping::with('bed.ward')->get();

        return response()->json([
            'success' => true,
            'mappings' => $mappings,
        ]);
    }

    /**
     * Store bed mapping
     */
    public function storeBedMapping(Request $request)
    {
        $validated = $request->validate([
            'bed_id' => 'required|exists:beds,id',
            'mac_address' => 'required|string|max:20',
            // The screen's own template; left out or blank, it uses the one under Configuration & Login
            'template_id' => 'nullable|string|max:64',
            'device_name' => 'nullable|string|max:255',
        ]);

        // Check if mapping already exists
        $existing = EkadBedMapping::where('bed_id', $validated['bed_id'])->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This bed already has a mapping. Please update or delete the existing one.',
            ], 400);
        }

        $mapping = EkadBedMapping::create($validated);
        $mapping->load('bed.ward');

        return response()->json([
            'success' => true,
            'message' => 'Bed mapping created successfully',
            'mapping' => $mapping,
        ]);
    }

    /**
     * Update bed mapping
     */
    public function updateBedMapping(Request $request, EkadBedMapping $mapping)
    {
        $validated = $request->validate([
            'mac_address' => 'sometimes|required|string|max:20',
            'template_id' => 'nullable|string|max:64',
            'device_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            // Give every mapped screen in this bed's ward the same template
            'apply_to_ward' => 'boolean',
        ]);

        $mapping->update(collect($validated)->except('apply_to_ward')->all());
        $mapping->load('bed.ward');

        $updatedIds = [$mapping->id];
        if ($request->boolean('apply_to_ward') && array_key_exists('template_id', $validated) && $mapping->bed) {
            $wardMappings = EkadBedMapping::whereHas('bed', fn ($query) => $query->where('ward_id', $mapping->bed->ward_id))->get();
            $wardMappings->each(fn (EkadBedMapping $wardMapping) => $wardMapping->update(['template_id' => $mapping->template_id]));
            $updatedIds = $wardMappings->pluck('id')->all();
        }

        $screens = count($updatedIds) === 1 ? '1 screen' : count($updatedIds) . ' screens';

        return response()->json([
            'success' => true,
            'message' => array_key_exists('template_id', $validated)
                ? "Template updated for {$screens}"
                : 'Bed mapping updated successfully',
            'mapping' => $mapping,
            'updated_ids' => $updatedIds,
        ]);
    }

    /**
     * Delete bed mapping
     */
    public function destroyBedMapping(EkadBedMapping $mapping)
    {
        $mapping->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bed mapping deleted successfully',
        ]);
    }

    /**
     * Push patient info via template to e-ink device.
     */
    public function pushPatientInfo(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'base_url' => 'nullable|string',
            'template_id' => 'required|string',
            'mac_list' => 'required|array',
            'mac_list.*' => 'required|string',
            'mrn' => 'nullable|string',
            'patient_name' => 'required|string',
            'bed_no' => 'nullable|string',
            'diet_type' => 'nullable|string',
            'doctor' => 'nullable|string',
            'nurse' => 'nullable|string',
            'anaesthetist' => 'nullable|string',
            'isolation_type' => 'nullable|string',
            'apply_masking' => 'boolean',
        ]);

        $config = EkadConfiguration::getActive();
        $baseUrl = $validated['base_url'] ?? $config?->base_url ?? $this->baseUrl;

        // Apply masking if enabled
        $patientName = $validated['patient_name'];
        if ($request->input('apply_masking', false) && $config && $config->mask_patient_name) {
            $patientName = $config->maskPatientName($patientName);
        }

        try {
            $payload = [
                'id' => $validated['template_id'],
                'macList' => array_map(fn($mac) => EkadBedMapping::formatMac($mac), $validated['mac_list']),
                'data' => [
                    [
                        'bed no' => $validated['bed_no'] ?? '-',
                        'MRN' => $validated['mrn'] ?? '-',
                        'patient_name' => $patientName,
                        'diet_type' => $validated['diet_type'] ?? '-',
                        'doctor' => $validated['doctor'] ?? '-',
                        'nurse' => $validated['nurse'] ?? '-',
                        'anaesthetist' => $validated['anaesthetist'] ?? '-',
                        'isolation_type' => $validated['isolation_type'] ?? '-',
                    ],
                ],
            ];

            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $validated['token'],
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/api/v1/template/batchPaintingByJson", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['code']) && $data['code'] === 200) {
                Log::info('EKad: Push patient info successful', [
                    'mac' => $validated['mac_list'],
                    'patient' => $patientName,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Patient info pushed successfully!',
                    'data' => $data['data'] ?? null,
                    'payload' => $payload,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $data['msg'] ?? 'Failed to push patient info',
                    'code' => $data['code'] ?? null,
                    'payload' => $payload,
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('EKad: Push patient info error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview masking for patient name
     */
    public function previewMasking(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'style' => 'in:partial,full,initials',
        ]);

        $config = new EkadConfiguration([
            'mask_patient_name' => true,
            'mask_style' => $validated['style'] ?? 'partial',
        ]);

        return response()->json([
            'success' => true,
            'original' => $validated['name'],
            'masked' => $config->maskPatientName($validated['name']),
            'style' => $validated['style'],
        ]);
    }

    /**
     * Get EKad activity logs from Laravel log file
     */
    public function getActivityLogs()
    {
        $logs = [];
        $logPath = storage_path('logs/laravel.log');

        if (file_exists($logPath)) {
            // Read last 100 lines of log file
            $lines = [];
            $fp = fopen($logPath, 'r');
            if ($fp) {
                // Get file size and seek near the end
                fseek($fp, -50000, SEEK_END); // Read last ~50KB
                fgets($fp); // Skip partial line

                while (!feof($fp)) {
                    $lines[] = fgets($fp);
                }
                fclose($fp);
            }

            // Filter for EKad related logs
            foreach ($lines as $line) {
                if (strpos($line, 'EKad') !== false) {
                    // Parse the log line
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\.(DEBUG|INFO|WARNING|ERROR):\s*(.+)/', $line, $matches)) {
                        $logs[] = [
                            'time' => $matches[1],
                            'level' => $matches[2],
                            'message' => trim($matches[3]),
                        ];
                    }
                }
            }

            // Get last 20 logs, most recent first
            $logs = array_slice(array_reverse($logs), 0, 20);
        }

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Get EKAD API response logs from database
     */
    public function getResponseLogs(Request $request)
    {
        $query = EkadResponseLog::with(['bed', 'patient'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('success') && $request->input('success') !== '') {
            $query->where('success', $request->boolean('success'));
        }

        if ($request->has('duration') && $request->input('duration') !== 'all') {
            $duration = $request->input('duration');
            $minutes = match ($duration) {
                '30m' => 30,
                '1h' => 60,
                '2h' => 120,
                '6h' => 360,
                '12h' => 720,
                '24h' => 1440,
                '48h' => 2880,
                '7d' => 10080,
                '30d' => 43200,
                default => 1440,
            };
            $query->where('created_at', '>=', now()->subMinutes($minutes));
        }

        if ($request->has('bed_id')) {
            $query->where('bed_id', $request->input('bed_id'));
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        if ($request->has('triggered_by')) {
            $query->where('triggered_by', $request->input('triggered_by'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->input('start_date'),
                $request->input('end_date')
            ]);
        }

        // Paginate results
        $perPage = $request->input('per_page', 50);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Export API response logs to CSV
     */
    public function exportResponseLogs(Request $request)
    {
        $fileName = 'ekad_response_logs_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($request) {
            $file = fopen('php://output', 'w');

            // BOM for Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                'Time',
                'Action',
                'Success',
                'Message',
                'Triggered By',
                'MAC Address',
                'Bed',
                'Patient',
                'HTTP Code',
                'Error'
            ]);

            $query = EkadResponseLog::with(['bed', 'patient'])
                ->orderBy('created_at', 'desc');

            // Apply filters (same as getResponseLogs)
            if ($request->has('success') && $request->input('success') !== '') {
                // Handle 'true'/'false' strings properly if coming from query params
                $successVal = $request->input('success');
                if ($successVal === 'true' || $successVal === '1')
                    $success = true;
                else if ($successVal === 'false' || $successVal === '0')
                    $success = false;
                else
                    $success = null;

                if ($success !== null) {
                    $query->where('success', $success);
                }
            }

            if ($request->has('duration') && $request->input('duration') !== 'all') {
                $duration = $request->input('duration');
                $minutes = match ($duration) {
                    '30m' => 30,
                    '1h' => 60,
                    '2h' => 120,
                    '6h' => 360,
                    '12h' => 720,
                    '24h' => 1440,
                    '48h' => 2880,
                    '7d' => 10080,
                    '30d' => 43200,
                    default => 1440,
                };
                $query->where('created_at', '>=', now()->subMinutes($minutes));
            }

            // Limit for safety if no duration
            if (!$request->has('duration') || $request->input('duration') === 'all') {
                $query->limit(5000);
            }

            $query->chunk(100, function ($logs) use ($file) {
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->action,
                        $log->success ? 'Yes' : 'No',
                        $log->message,
                        $log->triggered_by,
                        $log->mac_address,
                        $log->bed ? $log->bed->bed_number : '-',
                        $log->patient ? $log->patient->name : '-',
                        $log->response_code,
                        $log->error_message
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Sync all bed mappings - push patient info or vacant status to all mapped devices
     */
    public function syncAll(Request $request)
    {
        $config = EkadConfiguration::getActive();
        if (!$config || !$config->isTokenValid()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid EKAD configuration or token. Please login first.',
            ], 400);
        }

        $service = new EkadService($config);
        $mappings = EkadBedMapping::active()->with(['bed.patient', 'bed.ward'])->get();

        $results = [
            'total' => $mappings->count(),
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($mappings as $mapping) {
            $bed = $mapping->bed;
            if (!$bed) {
                $results['failed']++;
                $results['details'][] = [
                    'mac' => $mapping->mac_address,
                    'success' => false,
                    'message' => 'Bed not found',
                ];
                continue;
            }

            // Sync bed status first to ensure correct patient priority
            $this->syncBedWithPatients($bed);
            $bed->refresh();

            $patient = $bed->patient;
            if ($patient && $patient->isAdmitted()) {
                // Push patient info
                $result = $service->pushPatientInfo($patient, $bed, [], 'sync_all');
            } else {
                // Push vacant status
                $result = $service->pushVacant($bed, 'sync_all');
            }

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'mac' => $mapping->mac_address,
                'bed' => $bed->bed_display_name ?? $bed->bed_number,
                'patient' => $patient?->name ?? 'Vacant',
                'success' => $result['success'],
                'message' => $result['message'],
            ];
        }

        Log::info('EKad: Sync all completed', [
            'total' => $results['total'],
            'success' => $results['success'],
            'failed' => $results['failed'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Sync complete: {$results['success']}/{$results['total']} successful",
            'results' => $results,
        ]);
    }

    /**
     * Get preview data for Sync Modal
     */
    public function syncPreview()
    {
        $mappings = EkadBedMapping::with([
            'bed.ward',
            'bed.patient' => function ($query) {
                $query->where('is_active', true);
            },
            'bed.patient.nurse',
            'bed.patient.consultant',
            'bed.patient.anaesthetist'
        ])
            ->where('is_active', true)
            ->get();

        $config = EkadConfiguration::getActive();
        $service = new EkadService($config);

        $previewData = $mappings->map(function ($mapping) use ($service, $config) {
            $bed = $mapping->bed;

            if (!$bed) {
                return null;
            }

            // Sync bed status first
            $this->syncBedWithPatients($bed);
            $bed->refresh();

            // Get payload using the service logic (handles occupied/vacant/prebook), in the screen's template's fields
            $payload = $service->getBedPayload($bed, $mapping->template_id);
            $patient = $bed->patient;
            $occupied = $patient && $patient->is_active && $patient->isAdmitted();

            return [
                'mapping_id' => $mapping->id,
                'ward_name' => $bed->ward->ward_name ?? '-',
                'bed_id' => $bed->id,
                'bed_number' => $bed->bed_number, // Normalized in payload but kept raw here for display if needed
                'mac_address' => $mapping->mac_address,
                'template_id' => $mapping->templateIdOr($config?->template_id),
                'template_is_default' => $mapping->template_id === null,
                'payload' => $payload,
                'patient' => $occupied ? $service->displayName($patient) : '-',
                'status' => $occupied ? 'Occupied' : 'Vacant',
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'data' => $previewData
        ]);
    }

    /**
     * Sync selected beds
     */
    public function syncSelected(Request $request)
    {
        $validated = $request->validate([
            'bed_ids' => 'required|array',
            'bed_ids.*' => 'integer|exists:beds,id',
        ]);

        $bedIds = $validated['bed_ids'];
        $results = [];
        $successCount = 0;
        $failCount = 0;

        $config = EkadConfiguration::getActive();
        $service = new EkadService($config);

        // Login once
        if (!$service->getToken()) {
            $service->login();
        }

        foreach ($bedIds as $bedId) {
            $bed = Bed::with([
                'patient' => function ($q) {
                    $q->where('is_active', true);
                }
            ])->find($bedId);

            if (!$bed)
                continue;

            // Sync bed status first to ensure correct patient priority
            $this->syncBedWithPatients($bed);
            $bed->refresh();

            $result = [];

            // Check occupancy and push appropriate data (prebook is treated as vacant)
            $patient = $bed->patient;
            if ($patient && $patient->is_active && $patient->isAdmitted()) {
                // Pass 'Manual Sync' as event type
                $result = $service->pushPatientInfo($patient, $bed, [], 'Manual Sync');
            } else {
                $result = $service->pushVacant($bed, 'Manual Sync');
            }

            $results[] = [
                'bed_id' => $bedId,
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sync completed: {$successCount} success, {$failCount} failed",
            'results' => $results,
        ]);
    }

    /**
     * Sync single bed with its assigned patient (Local helper).
     * Updates the bed's status and patient_id based on active patients.
     * Prioritizes 'admitted' > 'pending_discharge' > 'prebook'.
     */
    private function syncBedWithPatients(Bed $bed)
    {
        // Check if bed has a patient assigned through the patient table
        // Prioritize admitted > pending_discharge > prebook
        $patient = \App\Models\Patient::where('ward_id', $bed->ward_id)
            ->where('bed_number', $bed->bed_number)
            ->where('is_active', true)
            ->whereIn('status', ['admitted', 'pending_discharge', 'prebook'])
            ->orderByRaw("FIELD(status, 'admitted', 'pending_discharge', 'prebook')")
            ->first();

        if ($patient) {
            // Update bed status based on patient status
            // pending_discharge is still considered occupied
            $bedStatus = $patient->status === 'prebook' ? 'reserved' : 'occupied';

            // Only update if changed to avoid unnecessary DB writes and Observer triggers
            if ($bed->status !== $bedStatus || $bed->patient_id !== $patient->id) {
                $bed->update([
                    'status' => $bedStatus,
                    'patient_id' => $patient->id,
                ]);
            }
        } else {
            // No ACTIVE patient assigned.
            // Check if there is a PENDING PREBOOK waiting for this bed
            $pendingPrebook = \App\Models\Patient::where('ward_id', $bed->ward_id)
                ->where('target_bed_number', $bed->bed_number)
                ->where('is_active', true)
                ->where('status', 'prebook_pending')
                ->first();

            if ($pendingPrebook) {
                // Activate the prebook!
                $pendingPrebook->update([
                    'status' => 'prebook',
                    'bed_number' => $bed->bed_number,
                ]);

                $bed->update([
                    'status' => 'reserved',
                    'patient_id' => $pendingPrebook->id,
                ]);
            } else if ($bed->status !== 'maintenance') {
                // No patient assigned and not maintenance, mark bed as available
                if ($bed->status !== 'available' || $bed->patient_id !== null) {
                    $bed->update([
                        'status' => 'available',
                        'patient_id' => null,
                    ]);
                }
            }
        }
    }
}

