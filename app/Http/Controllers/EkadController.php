<?php

namespace App\Http\Controllers;

use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Models\Bed;
use App\Models\Ward;
use App\Services\EkadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        return view('integration.ekad.index', compact('config', 'wards', 'bedMappings'));
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
            $config->update($validated);
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
            'mac_address' => 'required|string|max:20',
            'device_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $mapping->update($validated);
        $mapping->load('bed.ward');

        return response()->json([
            'success' => true,
            'message' => 'Bed mapping updated successfully',
            'mapping' => $mapping,
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
            'diet_type' => 'nullable|string',
            'doctor' => 'nullable|string',
            'nurse' => 'nullable|string',
            'anaesthetist' => 'nullable|string',
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
                        'mrn' => $validated['mrn'] ?? '-',
                        'patient_name' => $patientName,
                        'diet_type' => $validated['diet_type'] ?? '-',
                        'doctor' => $validated['doctor'] ?? '-',
                        'nurse' => $validated['nurse'] ?? '-',
                        'anaesthetist' => $validated['anaesthetist'] ?? '-',
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
}
