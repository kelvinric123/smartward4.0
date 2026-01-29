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

            // Validate vital signs data
            $validator = Validator::make($request->all(), [
                'patient_code' => 'required|string',
                'measured_at' => 'nullable|date',
                'blood_pressure_systolic' => 'nullable|numeric|min:0|max:300',
                'blood_pressure_diastolic' => 'nullable|numeric|min:0|max:200',
                'pulse_rate' => 'nullable|numeric|min:0|max:300',
                'heart_rate' => 'nullable|numeric|min:0|max:300',
                'spo2' => 'nullable|numeric|min:0|max:100',
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
                'recorded_by' => null, // Gateway submission
                'operator_id' => $activeBinding?->nurse_id, // Link to bound nurse
                'systolic_bp' => $parsedValues['systolic_bp'],
                'diastolic_bp' => $parsedValues['diastolic_bp'],
                'pulse_rate' => $parsedValues['pulse_rate'],
                'temperature' => $parsedValues['temperature'],
                'spo2' => $parsedValues['spo2'],
                'respiratory_rate' => $parsedValues['respiratory_rate'],
                'reading_type' => 'single', // 'single' or 'full' - 'gateway' tracked in notes
                'notes' => 'Gateway API v1' . (count($notes) > 0 ? '; ' . implode('; ', $notes) : ''),
                'recorded_at' => $parsedValues['recorded_at'],
            ];

            $debugData['vital_sign_creation']['input_data'] = $vitalSignData;
            $debugData['processing_steps'][] = 'Creating vital sign record...';

            $vitalSign = VitalSign::create($vitalSignData);

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























