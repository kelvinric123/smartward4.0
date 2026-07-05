<?php

namespace App\Http\Controllers;

use App\Models\ApiUser;
use App\Models\VitalSign;
use App\Models\VitalSignApiLog;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * API V1 Controller for Vital Sign Integration
 * 
 * This controller matches the authentication flow expected by the 
 * Raspberry Pi gateway (comennc5/main.py):
 * - Uses X-Passphrase header for basic authentication
 * - Uses username/password in request body for user authentication
 * - No separate login step required
 */
class VitalSignApiV1Controller extends Controller
{
    /**
     * Receive vital signs data from Gateway.
     * 
     * Expected request format from Python client:
     * Headers: X-Passphrase: <passphrase>
     * Body: {
     *   "username": "...",
     *   "password": "...",
     *   "patient_code": "...",
     *   "measured_at": "YYYY-MM-DD HH:MM:SS",
     *   "blood_pressure_systolic": 120,
     *   "blood_pressure_diastolic": 80,
     *   "pulse_rate": 72,
     *   "heart_rate": 72,
     *   "spo2": 98,
     *   "temperature": 36.5,
     *   "respiratory_rate": 16,
     *   "weight": 70,
     *   "height": 170,
     *   "notes": "..."
     * }
     */
    public function receiveVitalSigns(Request $request)
    {
        $startTime = microtime(true);
        $debugData = [
            'processing_steps' => [],
            'parsing_details' => [],
            'patient_lookup' => [],
            'binding_info' => [],
            'vital_sign_creation' => [],
        ];

        // Log incoming request for debugging
        Log::info('[API V1] Vital Signs Request', [
            'ip' => $request->ip(),
            'passphrase_present' => $request->hasHeader('X-Passphrase'),
            'username' => $request->input('username'),
            'patient_code' => $request->input('patient_code'),
        ]);

        $debugData['processing_steps'][] = 'Started vital signs processing';

        try {

            // Validate passphrase
            $passphraseError = $this->validatePassphrase($request);
            if ($passphraseError) {
                $debugData['processing_steps'][] = 'FAILED: Invalid/missing passphrase';
                return $passphraseError;
            }
            $debugData['processing_steps'][] = 'Passphrase validated';

            // Validate and authenticate user
            $apiUser = $this->authenticateUser($request);
            if (!$apiUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }
            $debugData['processing_steps'][] = 'User authenticated: ' . $apiUser->name;

            // Idempotency: if this gateway event was already recorded, acknowledge
            // it again instead of inserting a duplicate. Makes Pi-side retries safe
            // when a write succeeded but the ACK was lost over flaky Wi-Fi.
            $eventId = $request->header('X-Idempotency-Key') ?: $request->input('gateway_event_id');
            if ($eventId) {
                $existing = VitalSign::withTrashed()->where('gateway_event_id', $eventId)->first();
                if ($existing) {
                    $debugData['processing_steps'][] = 'Idempotent replay of event ' . $eventId;
                    return $this->ackDuplicate($apiUser, $request, $startTime, $debugData, $existing, $eventId);
                }
            }

            // Validate vital signs data
            $validator = Validator::make($request->all(), [
                'patient_code' => 'required|string',
                'gateway_event_id' => 'nullable|string|max:64',
                'gateway_id' => 'nullable|string|max:255',
                'measured_at' => 'nullable|date',
                'blood_pressure_systolic' => 'nullable|numeric|min:0|max:300',
                'blood_pressure_diastolic' => 'nullable|numeric|min:0|max:200',
                'pulse_rate' => 'nullable|numeric|min:0|max:300',
                'pulse_rate_min' => 'nullable|numeric|min:0|max:300',
                'pulse_rate_max' => 'nullable|numeric|min:0|max:300',
                'heart_rate' => 'nullable|numeric|min:0|max:300',
                'spo2' => 'nullable|numeric|min:0|max:100',
                'spo2_min' => 'nullable|numeric|min:0|max:100',
                'spo2_max' => 'nullable|numeric|min:0|max:100',
                'temperature' => 'nullable|numeric|min:20|max:50',
                'respiratory_rate' => 'nullable|numeric|min:0|max:100',
                'weight' => 'nullable|numeric|min:0|max:500',
                'height' => 'nullable|numeric|min:0|max:300',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                $debugData['processing_steps'][] = 'FAILED: Validation errors';
                $debugData['parsing_details']['validation_errors'] = $validator->errors()->toArray();

                $responseData = [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ];

                $this->logApiRequest(
                    $apiUser,
                    '/api/v1/vital-signs',
                    'POST',
                    $this->maskSensitiveData($request->all()),
                    $responseData,
                    422,
                    $startTime,
                    $debugData
                );

                return response()->json($responseData, 422);
            }

            $debugData['processing_steps'][] = 'Request validation passed';
            $debugData['parsing_details']['received_fields'] = array_keys(array_filter($request->only([
                'patient_code',
                'measured_at',
                'blood_pressure_systolic',
                'blood_pressure_diastolic',
                'pulse_rate',
                'heart_rate',
                'spo2',
                'temperature',
                'respiratory_rate',
                'weight',
                'height',
                'notes'
            ]), fn($v) => $v !== null && $v !== ''));

            // Find patient by patient_code (could be MRN or visit_number)
            // Sanitize patient_code to remove invisible characters, null bytes, control characters
            $rawPatientCode = $request->patient_code;
            $patientCode = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', trim($rawPatientCode)); // Remove control chars and nbsp
            $patientCode = preg_replace('/\s+/', '', $patientCode); // Remove any whitespace

            $debugData['patient_lookup']['search_code'] = $patientCode;
            $debugData['patient_lookup']['raw_code'] = $rawPatientCode;
            $debugData['patient_lookup']['raw_code_length'] = strlen($rawPatientCode);
            $debugData['patient_lookup']['clean_code_length'] = strlen($patientCode);

            // Log if there was a difference (debugging invisible chars)
            if ($rawPatientCode !== $patientCode) {
                $debugData['patient_lookup']['sanitized'] = true;
                $debugData['patient_lookup']['removed_chars'] = bin2hex($rawPatientCode) . ' -> ' . bin2hex($patientCode);
                Log::warning('[API V1] Patient code contained invisible characters', [
                    'raw' => bin2hex($rawPatientCode),
                    'clean' => $patientCode,
                    'raw_length' => strlen($rawPatientCode),
                    'clean_length' => strlen($patientCode),
                ]);
            }

            // Search by MRN first
            $patient = Patient::where('mrn', $patientCode)->first();
            if ($patient) {
                $debugData['patient_lookup']['found_by'] = 'mrn';
            } else {
                // Try RN (Registration Number) - this is what vital sign devices typically send
                $patient = Patient::where('rn', $patientCode)->first();
                if ($patient) {
                    $debugData['patient_lookup']['found_by'] = 'rn';
                } else {
                    // Try visit_number
                    $patient = Patient::where('visit_number', $patientCode)->first();
                    if ($patient) {
                        $debugData['patient_lookup']['found_by'] = 'visit_number';
                    } else {
                        // Try ic_passport
                        $patient = Patient::where('ic_passport', $patientCode)->first();
                        if ($patient) {
                            $debugData['patient_lookup']['found_by'] = 'ic_passport';
                        }
                    }
                }
            }

            if (!$patient) {
                $debugData['processing_steps'][] = 'FAILED: Patient not found';
                $debugData['patient_lookup']['result'] = 'not_found';
                $debugData['patient_lookup']['searched_tables'] = ['mrn', 'rn', 'visit_number', 'ic_passport'];

                $responseData = [
                    'success' => false,
                    'message' => 'Patient not found',
                ];

                $this->logApiRequest(
                    $apiUser,
                    '/api/v1/vital-signs',
                    'POST',
                    $this->maskSensitiveData($request->all()),
                    $responseData,
                    404,
                    $startTime,
                    $debugData
                );

                return response()->json($responseData, 404);
            }

            $debugData['processing_steps'][] = 'Patient found: ' . $patient->name;
            $debugData['patient_lookup']['result'] = 'found';
            $debugData['patient_lookup']['patient_id'] = $patient->id;
            $debugData['patient_lookup']['patient_name'] = $patient->name;
            $debugData['patient_lookup']['patient_mrn'] = $patient->mrn;
            $debugData['patient_lookup']['patient_visit_number'] = $patient->visit_number;

            // Log patient bed/ward info for debugging (admission no longer required)
            $debugData['patient_lookup']['bed_id'] = $patient->bed_id;
            $debugData['patient_lookup']['has_bed'] = !empty($patient->bed);
            $debugData['patient_lookup']['ward_id'] = $patient->ward_id;
            $debugData['patient_lookup']['is_admitted'] = !empty($patient->bed);

            if ($patient->bed) {
                $debugData['processing_steps'][] = 'Patient is admitted (has bed)';
            } else {
                $debugData['processing_steps'][] = 'Patient not admitted (no bed) - proceeding anyway';
            }

            // Build notes field
            $notes = [];
            if ($request->notes) {
                $notes[] = $request->notes;
            }
            if ($request->weight) {
                $notes[] = "Weight: {$request->weight} kg";
            }
            if ($request->height) {
                $notes[] = "Height: {$request->height} cm";
            }

            // Check for active nurse binding
            $activeBinding = \App\Models\GatewayNurseBinding::where('api_user_id', $apiUser->id)
                ->where('start_at', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('end_at')
                        ->orWhere('end_at', '>=', now());
                })
                ->latest()
                ->first();

            $debugData['binding_info']['has_active_binding'] = $activeBinding !== null;
            if ($activeBinding) {
                $debugData['binding_info']['binding_id'] = $activeBinding->id;
                $debugData['binding_info']['nurse_id'] = $activeBinding->nurse_id;
                $debugData['binding_info']['start_at'] = $activeBinding->start_at?->toIso8601String();
                $debugData['binding_info']['end_at'] = $activeBinding->end_at?->toIso8601String();
                $debugData['processing_steps'][] = 'Active nurse binding found, nurse_id: ' . $activeBinding->nurse_id;
            } else {
                $debugData['processing_steps'][] = 'No active nurse binding found';
            }

            // Parse vital sign values
            $debugData['parsing_details']['raw_values'] = [
                'blood_pressure_systolic' => $request->blood_pressure_systolic,
                'blood_pressure_diastolic' => $request->blood_pressure_diastolic,
                'pulse_rate' => $request->pulse_rate,
                'heart_rate' => $request->heart_rate,
                'spo2' => $request->spo2,
                'temperature' => $request->temperature,
                'respiratory_rate' => $request->respiratory_rate,
                'measured_at' => $request->measured_at,
            ];

            $parsedValues = [
                'systolic_bp' => $request->blood_pressure_systolic ?? null,
                'diastolic_bp' => $request->blood_pressure_diastolic ?? null,
                'pulse_rate' => $request->pulse_rate ?? $request->heart_rate ?? null,
                'temperature' => $request->temperature ?? null,
                'spo2' => $request->spo2 ? round($request->spo2) : null,
                'respiratory_rate' => $request->respiratory_rate ?? null,
                'recorded_at' => $request->measured_at ?? now(),
            ];
            $debugData['parsing_details']['parsed_values'] = $parsedValues;

            // Create vital sign record
            $vitalSignData = [
                'patient_id' => $patient->id,
                'admission_id' => $patient->id, // Using patient ID as admission reference
                'gateway_event_id' => $eventId ?: null,
                'gateway_id' => $request->input('gateway_id') ?: null,
                'recorded_by' => null, // Gateway submission
                'operator_id' => $activeBinding?->nurse_id, // Link to bound nurse
                'systolic_bp' => $parsedValues['systolic_bp'],
                'diastolic_bp' => $parsedValues['diastolic_bp'],
                'pulse_rate' => $parsedValues['pulse_rate'],
                'pulse_rate_min' => $request->pulse_rate_min !== null ? (int) round($request->pulse_rate_min) : null,
                'pulse_rate_max' => $request->pulse_rate_max !== null ? (int) round($request->pulse_rate_max) : null,
                'temperature' => $parsedValues['temperature'],
                'spo2' => $parsedValues['spo2'],
                'spo2_min' => $request->spo2_min !== null ? (int) round($request->spo2_min) : null,
                'spo2_max' => $request->spo2_max !== null ? (int) round($request->spo2_max) : null,
                'respiratory_rate' => $parsedValues['respiratory_rate'],
                'reading_type' => 'single', // 'single' or 'full' - 'gateway' tracked in notes
                'notes' => 'Gateway API v1' . (count($notes) > 0 ? '; ' . implode('; ', $notes) : ''),
                'recorded_at' => $parsedValues['recorded_at'],
            ];

            $debugData['vital_sign_creation']['input_data'] = $vitalSignData;
            $debugData['processing_steps'][] = 'Creating vital sign record...';

            try {
                $vitalSign = VitalSign::create($vitalSignData);
            } catch (\Illuminate\Database\QueryException $e) {
                // Unique-constraint race: a concurrent retry inserted the same
                // event between our check above and this insert. Treat as duplicate.
                if ($eventId && ($existing = VitalSign::withTrashed()->where('gateway_event_id', $eventId)->first())) {
                    $debugData['processing_steps'][] = 'Idempotent replay (insert race) for event ' . $eventId;
                    return $this->ackDuplicate($apiUser, $request, $startTime, $debugData, $existing, $eventId);
                }
                throw $e;
            }

            $debugData['vital_sign_creation']['created'] = true;
            $debugData['vital_sign_creation']['vital_sign_id'] = $vitalSign->id;
            $debugData['processing_steps'][] = 'Vital sign created with ID: ' . $vitalSign->id;

            $apiUser->incrementRequestCount();

            $responseData = [
                'success' => true,
                'status' => 'success', // Required by Vital Signs Service
                'ack' => true,  // Explicit ACK for machines to confirm receipt
                'message' => 'Vital sign recorded successfully',
                'data' => [
                    'vital_sign_id' => $vitalSign->id,
                    'patient_name' => $patient->name,
                    'patient_mrn' => $patient->mrn,
                    'recorded_at' => $vitalSign->recorded_at?->toIso8601String(),
                ],
            ];

            $debugData['processing_steps'][] = 'SUCCESS: Vital sign recorded successfully';

            $this->logApiRequest(
                $apiUser,
                '/api/v1/vital-signs',
                'POST',
                $this->maskSensitiveData($request->all()),
                $responseData,
                201,
                $startTime,
                $debugData
            );

            Log::info('[API V1] Vital sign recorded', ['vital_sign_id' => $vitalSign->id, 'patient' => $patient->name]);

            return response()->json($responseData, 201);

        } catch (\Exception $e) {
            Log::error('[API V1] Error in receiveVitalSigns', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $this->maskSensitiveData($request->all()),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle Ping service from Raspberry Pi.
     * 
     * Expected request format:
     * {
     *   "username": "...",
     *   "password": "...",
     *   "timestamp": "...",
     *   "device_ip": "192.168.0.22"
     * }
     */
    public function ping(Request $request)
    {
        $startTime = microtime(true);

        // Validate passphrase
        $passphraseError = $this->validatePassphrase($request);
        if ($passphraseError) {
            return $passphraseError;
        }

        // Validate and authenticate user
        $apiUser = $this->authenticateUser($request);
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Identify Gateway
        $gateway = null;
        $ip = $request->input('device_ip') ?? $request->ip();

        if ($request->filled('mac_address')) {
            $gateway = \App\Models\QmedGateway::where('mac_address', $request->input('mac_address'))->first();
        }

        // Fallback: If no MAC, try to find a gateway associated with this API User?
        // Or if we strictly require MAC for identification?
        // Note: For now, if no MAC, and if we can't identify, we just log it.
        // But if the user only has one gateway linked to this API user, we might guess.

        if (!$gateway && $request->filled('device_ip')) {
            // Try to find by last known IP? Or maybe we can't.
        }

        // If gateway found, update it.
        if ($gateway) {
            $gateway->updatePing($ip);
        } else {
            // Try to find any active gateway linked to this user?
            // This might be risky if multiple gateways use same user.
            // But if specific gateway logic is needed, let's assume MAC or unique user.
            // For this task, "Gateway" section allows adding gateways and selecting users.

            // Let's try to search by IP if it was previously recorded?
            $gateway = \App\Models\QmedGateway::where('last_ping_ip', $ip)->first();
            if ($gateway) {
                $gateway->updatePing($ip);
            }
        }

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'status' => 'success',
            'message' => 'Ping received',
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/v1/ping',
            'POST',
            $this->maskSensitiveData($request->all()),
            $responseData,
            200,
            $startTime
        );

        return response()->json($responseData, 200);
    }

    /**
     * List active wards so setup.sh can offer a ward choice during provisioning.
     * GET with X-Passphrase header + username/password (query or body).
     */
    public function wards(Request $request)
    {
        $passphraseError = $this->validatePassphrase($request);
        if ($passphraseError) {
            return $passphraseError;
        }
        $apiUser = $this->authenticateUser($request);
        if (!$apiUser) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $wards = \App\Models\Ward::where('is_active', true)
            ->orderBy('ward_name')
            ->get(['id', 'ward_code', 'ward_name']);

        return response()->json([
            'success' => true,
            'data' => ['wards' => $wards],
        ], 200);
    }

    /**
     * Register a gateway and obtain a server-assigned name.
     *
     * The Pi calls this once during setup with its hardware fingerprint. Laravel
     * is the master of naming: the same board always maps to the same gateway_id
     * (idempotent), and a fresh board is assigned the next sequential name. This
     * is what makes SD-card cloning safe — identity is tied to hardware and issued
     * by the server, not baked into the image.
     *
     * Body: { username, password, cpu_serial, mac_address?, hostname? }
     * Returns: { data: { gateway_id, name, assigned } }
     */
    public function registerGateway(Request $request)
    {
        $startTime = microtime(true);

        $passphraseError = $this->validatePassphrase($request);
        if ($passphraseError) {
            return $passphraseError;
        }
        $apiUser = $this->authenticateUser($request);
        if (!$apiUser) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $cpuSerial = trim((string) $request->input('cpu_serial'));
        if ($cpuSerial === '') {
            return response()->json([
                'success' => false,
                'message' => 'cpu_serial is required to register a gateway',
            ], 422);
        }
        $mac = trim((string) $request->input('mac_address')) ?: null;
        $reportedHost = trim((string) $request->input('hostname')) ?: null;
        $sshUser = trim((string) $request->input('ssh_user')) ?: null;
        $sshPort = (int) $request->input('ssh_port') ?: null;
        $prefix = config('services.vital_sign_api.gateway_prefix', 'GW-');

        // Resolve the ward chosen during setup (ignored if it doesn't exist).
        $wardId = $request->input('ward_id');
        $wardId = ($wardId !== null && $wardId !== '' && \App\Models\Ward::whereKey($wardId)->exists())
            ? (int) $wardId : null;

        $result = \Illuminate\Support\Facades\DB::transaction(function () use ($cpuSerial, $mac, $reportedHost, $sshUser, $sshPort, $prefix, $wardId) {
            // Same board re-provisioned -> keep its name; allow ward/ssh/mac to update.
            $existing = \App\Models\QmedGateway::where('cpu_serial', $cpuSerial)->first();
            if ($existing) {
                $dirty = false;
                if ($mac && !$existing->mac_address) { $existing->mac_address = $mac; $dirty = true; }
                if ($wardId !== null && $existing->ward_id !== $wardId) { $existing->ward_id = $wardId; $dirty = true; }
                if (!$existing->hostname && $existing->gateway_id) { $existing->hostname = strtolower($existing->gateway_id); $dirty = true; }
                if ($sshUser && $existing->ssh_user !== $sshUser) { $existing->ssh_user = $sshUser; $dirty = true; }
                if ($sshPort && $existing->ssh_port !== $sshPort) { $existing->ssh_port = $sshPort; $dirty = true; }
                if ($dirty) { $existing->save(); }
                return [$existing, false];
            }

            // New board -> create, then derive the sequential name from its id.
            $gateway = new \App\Models\QmedGateway();
            $gateway->cpu_serial = $cpuSerial;
            $gateway->mac_address = $mac;
            $gateway->location = $reportedHost;
            $gateway->ward_id = $wardId;
            $gateway->ssh_user = $sshUser;
            $gateway->ssh_port = $sshPort;
            $gateway->is_active = true;
            $gateway->name = 'pending';
            $gateway->save();

            // Server-assigned identity: sequential name + matching hostname.
            $gateway->gateway_id = sprintf('%s%04d', $prefix, $gateway->id);
            $gateway->hostname = strtolower($gateway->gateway_id);
            $gateway->name = $gateway->gateway_id;
            $gateway->save();

            return [$gateway, true];
        });

        [$gateway, $assigned] = $result;
        // Link the registering API user so the gateway shows it on the dashboard.
        $gateway->apiUsers()->syncWithoutDetaching([$apiUser->id]);
        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'status' => 'success',
            'message' => $assigned ? 'Gateway registered' : 'Gateway already registered',
            'data' => [
                'gateway_id' => $gateway->gateway_id,
                'name' => $gateway->name,
                'assigned' => $assigned,
                'ward_id' => $gateway->ward_id,
                'ward' => $gateway->ward?->ward_name,
                'hostname' => $gateway->hostname,
                'ssh_user' => $gateway->ssh_user,
                'ssh_port' => $gateway->ssh_port,
                'ssh_command' => $gateway->ssh_command,
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/v1/gateway/register',
            'POST',
            $this->maskSensitiveData($request->all()),
            $responseData,
            200,
            $startTime
        );

        return response()->json($responseData, 200);
    }

    /**
     * Receive a gateway heartbeat from a Raspberry Pi cart.
     *
     * Body: {
     *   "username", "password", "gateway_id", "cpu_serial", "app_version",
     *   "uptime_s", "ip", "monitor": {...}, "queue": {...}, "power": {...},
     *   "clock_synced": bool, "disk_free_pct": int
     * }
     */
    public function heartbeat(Request $request)
    {
        $startTime = microtime(true);

        $passphraseError = $this->validatePassphrase($request);
        if ($passphraseError) {
            return $passphraseError;
        }

        $apiUser = $this->authenticateUser($request);
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $gatewayId = trim((string) $request->input('gateway_id'));
        if ($gatewayId === '') {
            return response()->json([
                'success' => false,
                'message' => 'gateway_id is required',
            ], 422);
        }

        // Only the telemetry belongs in storage; never persist creds. The heavy
        // "stats" block is stored separately so it survives between the frequent
        // live beats and the ~30-minute extended sends.
        $stats = $request->input('stats');
        $payload = $request->except(['username', 'password', 'stats']);
        $ip = $request->input('ip') ?: $request->ip();
        $incomingSerial = trim((string) $request->input('cpu_serial')) ?: null;

        $gateway = \App\Models\QmedGateway::firstOrNew(['gateway_id' => $gatewayId]);
        if (!$gateway->exists) {
            $gateway->name = $request->input('name', $gatewayId);
            $gateway->is_active = true;
        }

        // Detect a cloned / mis-provisioned Pi: this gateway_id is being used by
        // different hardware than it was registered to. Flag it (critical) and
        // tell the Pi to re-register instead of clobbering the real cart's serial.
        $conflict = false;
        if ($incomingSerial) {
            $ownerOfSerial = \App\Models\QmedGateway::where('cpu_serial', $incomingSerial)->first();
            if ($gateway->cpu_serial && $gateway->cpu_serial !== $incomingSerial) {
                $conflict = true;
            } elseif ($ownerOfSerial && $ownerOfSerial->gateway_id !== $gatewayId) {
                $conflict = true;
            } elseif (!$gateway->cpu_serial) {
                $gateway->cpu_serial = $incomingSerial;
            }
        }
        if ($request->filled('mac_address') && !$conflict && !$gateway->mac_address) {
            $gateway->mac_address = $request->input('mac_address');
        }
        if ($conflict) {
            $payload['identity_conflict'] = true;
        }
        if (is_array($stats)) {
            $gateway->last_stats = $stats;
            $gateway->last_stats_at = now();
        }

        $gateway->recordHeartbeat($payload, $ip);
        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'status' => 'success',
            'message' => $conflict ? 'Heartbeat received (identity conflict)' : 'Heartbeat received',
            'data' => [
                'gateway_id' => $gatewayId,
                'health_status' => $gateway->health_status,
                'action' => $conflict ? 'reregister' : null,
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/v1/gateway/heartbeat',
            'POST',
            $this->maskSensitiveData($request->all()),
            $responseData,
            200,
            $startTime
        );

        return response()->json($responseData, 200);
    }

    /**
     * Search for patient by patient_code.
     *
     * Expected request format from Python client:
     * Headers: X-Passphrase: <passphrase>
     * Query params: username, password
     */
    public function searchPatient(Request $request, string $patientCode)
    {
        $startTime = microtime(true);

        // Validate passphrase
        $passphraseError = $this->validatePassphrase($request);
        if ($passphraseError) {
            return $passphraseError;
        }

        // Validate and authenticate user (from query params)
        $apiUser = $this->authenticateUser($request);
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Find patient by patient_code (could be MRN, RN, visit_number, or IC/passport)
        $patient = Patient::where('mrn', $patientCode)
            ->orWhere('rn', $patientCode)
            ->orWhere('visit_number', $patientCode)
            ->orWhere('ic_passport', $patientCode)
            ->first();

        if (!$patient) {
            $responseData = [
                'success' => false,
                'message' => 'Patient not found',
            ];

            $this->logApiRequest(
                $apiUser,
                "/api/v1/patients/{$patientCode}",
                'GET',
                ['patient_code' => $patientCode],
                $responseData,
                404,
                $startTime
            );

            return response()->json($responseData, 404);
        }

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'message' => 'Patient found',
            'data' => [
                'patient_code' => $patient->mrn,
                'name' => $patient->name,
                'date_of_birth' => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('Y-m-d') : null,
                'gender' => $patient->gender,
                'mrn' => $patient->mrn,
                'visit_number' => $patient->visit_number,
                'ic_passport' => $patient->ic_passport,
                'is_admitted' => $patient->bed !== null,
                'ward_name' => $patient->ward?->name,
                'bed_number' => $patient->bed?->bed_number,
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            "/api/v1/patients/{$patientCode}",
            'GET',
            ['patient_code' => $patientCode],
            $responseData,
            200,
            $startTime
        );

        return response()->json($responseData, 200);
    }

    /**
     * Device login endpoint - for compatibility, returns success immediately
     * since we use per-request authentication.
     */
    public function deviceLogin(Request $request)
    {
        $startTime = microtime(true);

        // Validate passphrase
        $passphraseError = $this->validatePassphrase($request);
        if ($passphraseError) {
            return $passphraseError;
        }

        // Validate and authenticate user
        $apiUser = $this->authenticateUser($request);
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        $responseData = [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $apiUser->name,
                'note' => 'This API uses per-request authentication. Include username/password with each request.',
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/v1/device/login',
            'POST',
            $this->maskSensitiveData($request->all()),
            $responseData,
            200,
            $startTime
        );

        return response()->json($responseData, 200);
    }

    /**
     * Acknowledge an idempotent replay: the reading already exists, so return a
     * success response referencing the original record instead of inserting again.
     */
    private function ackDuplicate($apiUser, Request $request, float $startTime, array $debugData, $existing, string $eventId)
    {
        $responseData = [
            'success' => true,
            'status' => 'success',
            'ack' => true,
            'duplicate' => true,
            'message' => 'Vital sign already recorded (idempotent replay)',
            'data' => [
                'vital_sign_id' => $existing->id,
                'gateway_event_id' => $eventId,
                'recorded_at' => $existing->recorded_at?->toIso8601String(),
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/v1/vital-signs',
            'POST',
            $this->maskSensitiveData($request->all()),
            $responseData,
            200,
            $startTime,
            $debugData
        );

        return response()->json($responseData, 200);
    }

    /**
     * Validate X-Passphrase header.
     */
    private function validatePassphrase(Request $request)
    {
        $passphrase = $request->header('X-Passphrase');
        $expectedPassphrase = config('services.vital_sign_api.passphrase', 'qmedno1');

        if (!$passphrase) {
            return response()->json([
                'success' => false,
                'message' => 'Missing X-Passphrase header',
            ], 401);
        }

        if ($passphrase !== $expectedPassphrase) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid passphrase',
            ], 401);
        }

        return null; // Passphrase is valid
    }

    /**
     * Authenticate user by username/password from request body or query params.
     */
    private function authenticateUser(Request $request): ?ApiUser
    {
        $username = $request->input('username') ?? $request->query('username');
        $password = $request->input('password') ?? $request->query('password');

        if (!$username || !$password) {
            return null;
        }

        $apiUser = ApiUser::where('username', $username)
            ->where('is_active', true)
            ->first();

        if (!$apiUser || !$apiUser->verifyPassword($password)) {
            return null;
        }

        return $apiUser;
    }

    /**
     * Mask sensitive data for logging.
     */
    private function maskSensitiveData(array $data): array
    {
        if (isset($data['password'])) {
            $data['password'] = '***';
        }
        return $data;
    }

    /**
     * Log API request.
     */
    private function logApiRequest(
        ApiUser $apiUser,
        string $endpoint,
        string $method,
        array $requestData,
        array $responseData,
        int $statusCode,
        float $startTime,
        ?array $debugData = null
    ): void {
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        VitalSignApiLog::create([
            'api_user_id' => $apiUser->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status_code' => $statusCode,
            'ip_address' => request()->ip(),
            'response_time_ms' => $responseTimeMs,
            'debug_data' => $debugData,
        ]);
    }
}























