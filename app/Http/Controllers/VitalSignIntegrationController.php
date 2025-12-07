<?php

namespace App\Http\Controllers;

use App\Models\ApiUser;
use App\Models\VitalSign;
use App\Models\VitalSignApiLog;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VitalSignIntegrationController extends Controller
{
    /**
     * Display the Vital Sign Integration page.
     */
    public function index()
    {
        $apiUsers = ApiUser::withCount('apiLogs')
            ->latest()
            ->get();
        
        $recentLogs = VitalSignApiLog::with('apiUser')
            ->latest()
            ->limit(20)
            ->get();

        return view('integration.vital-sign.index', compact('apiUsers', 'recentLogs'));
    }

    /**
     * Store a new API user.
     */
    public function storeApiUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:api_users,username',
            'password' => 'required|string|min:8',
            'description' => 'nullable|string|max:1000',
        ]);

        ApiUser::create($validated);

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'API user created successfully.');
    }

    /**
     * Update an existing API user.
     */
    public function updateApiUser(Request $request, ApiUser $apiUser)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:api_users,username,' . $apiUser->id,
            'password' => 'nullable|string|min:8',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        $apiUser->update($validated);

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'API user updated successfully.');
    }

    /**
     * Delete an API user.
     */
    public function destroyApiUser(ApiUser $apiUser)
    {
        $apiUser->delete();

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'API user deleted successfully.');
    }

    /**
     * Regenerate token for an API user (for testing).
     */
    public function regenerateToken(ApiUser $apiUser)
    {
        $apiUser->invalidateToken();

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'Token invalidated. User needs to login again.');
    }

    // ============================================
    // API Endpoints (for Gateway)
    // ============================================

    /**
     * API: Authenticate and get bearer token.
     */
    public function apiLogin(Request $request)
    {
        $startTime = microtime(true);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $apiUser = ApiUser::where('username', $request->username)
            ->where('is_active', true)
            ->first();

        if (!$apiUser || !$apiUser->verifyPassword($request->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Generate new token (valid for 24 hours)
        $token = $apiUser->generateToken(24);

        $responseData = [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $apiUser->fresh()->token_expires_at->toIso8601String(),
            ],
        ];

        // Log this request
        $this->logApiRequest($apiUser, '/api/vital-sign/login', 'POST', 
            ['username' => $request->username], 
            $responseData, 
            200, 
            $startTime
        );

        return response()->json($responseData);
    }

    /**
     * API: Receive vital sign readings from Gateway.
     */
    public function apiReceiveVitalSigns(Request $request)
    {
        $startTime = microtime(true);

        // Validate bearer token
        $apiUser = $this->validateBearerToken($request);
        
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'readings' => 'required|array|min:1',
            'readings.*.patient_mrn' => 'required|string',
            'readings.*.systolic_bp' => 'nullable|integer|min:0|max:300',
            'readings.*.diastolic_bp' => 'nullable|integer|min:0|max:200',
            'readings.*.pulse_rate' => 'nullable|integer|min:0|max:300',
            'readings.*.temperature' => 'nullable|numeric|min:30|max:45',
            'readings.*.spo2' => 'nullable|integer|min:0|max:100',
            'readings.*.respiratory_rate' => 'nullable|integer|min:0|max:100',
            'readings.*.recorded_at' => 'nullable|date',
            'readings.*.device_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            $responseData = [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ];

            $this->logApiRequest($apiUser, '/api/vital-sign/readings', 'POST', 
                $request->all(), 
                $responseData, 
                422, 
                $startTime
            );

            return response()->json($responseData, 422);
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($request->readings as $index => $reading) {
            // Find patient by MRN
            $patient = Patient::where('mrn', $reading['patient_mrn'])->first();

            if (!$patient) {
                $results[] = [
                    'index' => $index,
                    'patient_mrn' => $reading['patient_mrn'],
                    'status' => 'failed',
                    'message' => 'Patient not found',
                ];
                $failCount++;
                continue;
            }

            // Check if patient has an active admission
            if (!$patient->bed_id) {
                $results[] = [
                    'index' => $index,
                    'patient_mrn' => $reading['patient_mrn'],
                    'status' => 'failed',
                    'message' => 'Patient is not currently admitted',
                ];
                $failCount++;
                continue;
            }

            // Create vital sign record
            $vitalSign = VitalSign::create([
                'patient_id' => $patient->id,
                'admission_id' => $patient->id, // Using patient ID as admission ID for now
                'recorded_by' => null, // Gateway submission
                'systolic_bp' => $reading['systolic_bp'] ?? null,
                'diastolic_bp' => $reading['diastolic_bp'] ?? null,
                'pulse_rate' => $reading['pulse_rate'] ?? null,
                'temperature' => $reading['temperature'] ?? null,
                'spo2' => $reading['spo2'] ?? null,
                'respiratory_rate' => $reading['respiratory_rate'] ?? null,
                'reading_type' => 'gateway',
                'notes' => 'Received via Gateway API' . (isset($reading['device_id']) ? ' (Device: ' . $reading['device_id'] . ')' : ''),
                'recorded_at' => isset($reading['recorded_at']) ? $reading['recorded_at'] : now(),
            ]);

            $results[] = [
                'index' => $index,
                'patient_mrn' => $reading['patient_mrn'],
                'status' => 'success',
                'vital_sign_id' => $vitalSign->id,
            ];
            $successCount++;
        }

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'message' => "Processed {$successCount} readings successfully, {$failCount} failed",
            'data' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ],
        ];

        $this->logApiRequest($apiUser, '/api/vital-sign/readings', 'POST', 
            $request->all(), 
            $responseData, 
            200, 
            $startTime
        );

        return response()->json($responseData);
    }

    /**
     * API: Receive a single vital sign reading.
     */
    public function apiReceiveSingleVitalSign(Request $request)
    {
        $startTime = microtime(true);

        // Validate bearer token
        $apiUser = $this->validateBearerToken($request);
        
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'patient_mrn' => 'required|string',
            'systolic_bp' => 'nullable|integer|min:0|max:300',
            'diastolic_bp' => 'nullable|integer|min:0|max:200',
            'pulse_rate' => 'nullable|integer|min:0|max:300',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'spo2' => 'nullable|integer|min:0|max:100',
            'respiratory_rate' => 'nullable|integer|min:0|max:100',
            'recorded_at' => 'nullable|date',
            'device_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            $responseData = [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ];

            $this->logApiRequest($apiUser, '/api/vital-sign/reading', 'POST', 
                $request->all(), 
                $responseData, 
                422, 
                $startTime
            );

            return response()->json($responseData, 422);
        }

        // Find patient by MRN
        $patient = Patient::where('mrn', $request->patient_mrn)->first();

        if (!$patient) {
            $responseData = [
                'success' => false,
                'message' => 'Patient not found',
            ];

            $this->logApiRequest($apiUser, '/api/vital-sign/reading', 'POST', 
                $request->all(), 
                $responseData, 
                404, 
                $startTime
            );

            return response()->json($responseData, 404);
        }

        // Check if patient has an active admission
        if (!$patient->bed_id) {
            $responseData = [
                'success' => false,
                'message' => 'Patient is not currently admitted',
            ];

            $this->logApiRequest($apiUser, '/api/vital-sign/reading', 'POST', 
                $request->all(), 
                $responseData, 
                400, 
                $startTime
            );

            return response()->json($responseData, 400);
        }

        // Create vital sign record
        $vitalSign = VitalSign::create([
            'patient_id' => $patient->id,
            'admission_id' => $patient->id,
            'recorded_by' => null,
            'systolic_bp' => $request->systolic_bp,
            'diastolic_bp' => $request->diastolic_bp,
            'pulse_rate' => $request->pulse_rate,
            'temperature' => $request->temperature,
            'spo2' => $request->spo2,
            'respiratory_rate' => $request->respiratory_rate,
            'reading_type' => 'gateway',
            'notes' => 'Received via Gateway API' . ($request->device_id ? ' (Device: ' . $request->device_id . ')' : ''),
            'recorded_at' => $request->recorded_at ?? now(),
        ]);

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'message' => 'Vital sign recorded successfully',
            'data' => [
                'vital_sign_id' => $vitalSign->id,
                'patient_name' => $patient->name,
                'recorded_at' => $vitalSign->recorded_at->toIso8601String(),
            ],
        ];

        $this->logApiRequest($apiUser, '/api/vital-sign/reading', 'POST', 
            $request->all(), 
            $responseData, 
            200, 
            $startTime
        );

        return response()->json($responseData);
    }

    /**
     * API: Logout (invalidate token).
     */
    public function apiLogout(Request $request)
    {
        $startTime = microtime(true);

        $apiUser = $this->validateBearerToken($request);
        
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $apiUser->invalidateToken();

        $responseData = [
            'success' => true,
            'message' => 'Logged out successfully',
        ];

        $this->logApiRequest($apiUser, '/api/vital-sign/logout', 'POST', 
            [], 
            $responseData, 
            200, 
            $startTime
        );

        return response()->json($responseData);
    }

    /**
     * Validate bearer token from request.
     */
    private function validateBearerToken(Request $request): ?ApiUser
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
            return null;
        }

        $token = Str::after($authHeader, 'Bearer ');
        
        return ApiUser::findByToken($token);
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

        // Mask sensitive data
        if (isset($requestData['password'])) {
            $requestData['password'] = '***';
        }

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

    /**
     * Get API logs for display.
     */
    public function getLogs(Request $request)
    {
        $logs = VitalSignApiLog::with('apiUser')
            ->when($request->api_user_id, fn($q) => $q->where('api_user_id', $request->api_user_id))
            ->latest()
            ->paginate(50);

        return response()->json($logs);
    }

    /**
     * Clear API logs.
     */
    public function clearLogs(Request $request)
    {
        if ($request->api_user_id) {
            VitalSignApiLog::where('api_user_id', $request->api_user_id)->delete();
        } else {
            VitalSignApiLog::truncate();
        }

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'API logs cleared successfully.');
    }
}






