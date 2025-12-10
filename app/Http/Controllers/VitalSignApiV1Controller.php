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
        
        // Log incoming request for debugging
        Log::info('[API V1] Vital Signs Request', [
            'ip' => $request->ip(),
            'passphrase_present' => $request->hasHeader('X-Passphrase'),
            'username' => $request->input('username'),
            'patient_code' => $request->input('patient_code'),
        ]);

        try {
        
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
            $responseData = [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ];

            $this->logApiRequest($apiUser, '/api/v1/vital-signs', 'POST', 
                $this->maskSensitiveData($request->all()), 
                $responseData, 
                422, 
                $startTime
            );

            return response()->json($responseData, 422);
        }

        // Find patient by patient_code (could be MRN or visit_number)
        $patientCode = $request->patient_code;
        $patient = Patient::where('mrn', $patientCode)
            ->orWhere('visit_number', $patientCode)
            ->orWhere('ic_passport', $patientCode)
            ->first();

        if (!$patient) {
            $responseData = [
                'success' => false,
                'message' => 'Patient not found',
            ];

            $this->logApiRequest($apiUser, '/api/v1/vital-signs', 'POST', 
                $this->maskSensitiveData($request->all()), 
                $responseData, 
                404, 
                $startTime
            );

            return response()->json($responseData, 404);
        }

        // Check if patient is admitted (has a bed assigned)
        if (!$patient->bed) {
            $responseData = [
                'success' => false,
                'message' => 'Patient is not currently admitted',
            ];

            $this->logApiRequest($apiUser, '/api/v1/vital-signs', 'POST', 
                $this->maskSensitiveData($request->all()), 
                $responseData, 
                400, 
                $startTime
            );

            return response()->json($responseData, 400);
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
        
        // Create vital sign record
        // Map field names from Python client to database schema
        $vitalSign = VitalSign::create([
            'patient_id' => $patient->id,
            'admission_id' => $patient->id, // Using patient ID as admission reference
            'recorded_by' => null, // Gateway submission
            'systolic_bp' => $request->blood_pressure_systolic ?? null,
            'diastolic_bp' => $request->blood_pressure_diastolic ?? null,
            'pulse_rate' => $request->pulse_rate ?? $request->heart_rate ?? null,
            'temperature' => $request->temperature ?? null,
            'spo2' => $request->spo2 ? round($request->spo2) : null,
            'respiratory_rate' => $request->respiratory_rate ?? null,
            'reading_type' => 'single', // 'single' or 'full' - 'gateway' tracked in notes
            'notes' => 'Gateway API v1' . (count($notes) > 0 ? '; ' . implode('; ', $notes) : ''),
            'recorded_at' => $request->measured_at ?? now(),
        ]);

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'message' => 'Vital sign recorded successfully',
            'data' => [
                'vital_sign_id' => $vitalSign->id,
                'patient_name' => $patient->name,
                'patient_mrn' => $patient->mrn,
                'recorded_at' => $vitalSign->recorded_at->toIso8601String(),
            ],
        ];

        $this->logApiRequest($apiUser, '/api/v1/vital-signs', 'POST', 
            $this->maskSensitiveData($request->all()), 
            $responseData, 
            201, 
            $startTime
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

        // Find patient by patient_code (could be MRN, visit_number, or IC/passport)
        $patient = Patient::where('mrn', $patientCode)
            ->orWhere('visit_number', $patientCode)
            ->orWhere('ic_passport', $patientCode)
            ->first();

        if (!$patient) {
            $responseData = [
                'success' => false,
                'message' => 'Patient not found',
            ];

            $this->logApiRequest($apiUser, "/api/v1/patients/{$patientCode}", 'GET', 
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
                'date_of_birth' => $patient->date_of_birth ? $patient->date_of_birth->format('Y-m-d') : null,
                'gender' => $patient->gender,
                'mrn' => $patient->mrn,
                'visit_number' => $patient->visit_number,
                'ic_passport' => $patient->ic_passport,
                'is_admitted' => $patient->bed !== null,
                'ward_name' => $patient->ward?->name,
                'bed_number' => $patient->bed?->bed_number,
            ],
        ];

        $this->logApiRequest($apiUser, "/api/v1/patients/{$patientCode}", 'GET', 
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

        $this->logApiRequest($apiUser, '/api/v1/device/login', 'POST', 
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
        float $startTime
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
        ]);
    }
}








