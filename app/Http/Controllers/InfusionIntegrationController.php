<?php

namespace App\Http\Controllers;

use App\Models\Infusion;
use App\Models\InfusionApiLog;
use App\Models\InfusionApiUser;
use App\Models\InfusionPump;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InfusionIntegrationController extends Controller
{
    /**
     * Display the Infusion Integration page.
     */
    public function index(): View
    {
        $apiUsers = InfusionApiUser::latest()->get();
        
        $recentLogs = InfusionApiLog::with('apiUser')
            ->latest()
            ->limit(20)
            ->get();

        $pumps = InfusionPump::with('ward')->latest()->get();

        $stats = [
            'total_pumps' => InfusionPump::count(),
            'active_pumps' => InfusionPump::where('is_active', true)->count(),
            'active_infusions' => Infusion::active()->count(),
            'warnings' => Infusion::running()->withWarnings()->count(),
            'alarms' => Infusion::alarming()->count(),
        ];

        return view('integration.infusion.index', compact('apiUsers', 'recentLogs', 'pumps', 'stats'));
    }

    /**
     * Store a new API user.
     */
    public function storeApiUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:infusion_api_users,username',
            'password' => 'required|string|min:8',
            'description' => 'nullable|string|max:1000',
        ]);

        InfusionApiUser::create($validated);

        return redirect()->route('infusion-integration.index')
            ->with('success', 'Infusion API user created successfully.');
    }

    /**
     * Update an existing API user.
     */
    public function updateApiUser(Request $request, InfusionApiUser $apiUser)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:infusion_api_users,username,' . $apiUser->id,
            'password' => 'nullable|string|min:8',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $validated['is_active'] = $request->has('is_active');

        $apiUser->update($validated);

        return redirect()->route('infusion-integration.index')
            ->with('success', 'Infusion API user updated successfully.');
    }

    /**
     * Delete an API user.
     */
    public function destroyApiUser(InfusionApiUser $apiUser)
    {
        $apiUser->delete();

        return redirect()->route('infusion-integration.index')
            ->with('success', 'Infusion API user deleted successfully.');
    }

    /**
     * Ward Infusion Overview (iframe content).
     */
    public function wardOverview(Request $request): View
    {
        $wardId = $request->get('ward_id');
        $filter = $request->get('filter', 'active'); // active, completed, all, warnings

        $query = Infusion::with(['patient', 'infusionPump']);

        if ($wardId) {
            $query->inWard($wardId);
        }

        switch ($filter) {
            case 'active':
                $query->active();
                break;
            case 'completed':
                $query->completed();
                break;
            case 'warnings':
                $query->running()->withWarnings();
                break;
            case 'alarms':
                $query->alarming();
                break;
        }

        $infusions = $query->latest('last_updated_at')->get();

        // Get summary stats
        $stats = [
            'running' => Infusion::when($wardId, fn($q) => $q->inWard($wardId))->running()->count(),
            'paused' => Infusion::when($wardId, fn($q) => $q->inWard($wardId))->where('status', 'paused')->count(),
            'completed' => Infusion::when($wardId, fn($q) => $q->inWard($wardId))->completed()->count(),
            'warnings' => Infusion::when($wardId, fn($q) => $q->inWard($wardId))->running()->withWarnings()->count(),
            'alarms' => Infusion::when($wardId, fn($q) => $q->inWard($wardId))->alarming()->count(),
        ];

        return view('wards.infusion-overview', compact('infusions', 'stats', 'filter', 'wardId'));
    }

    /**
     * Patient Infusion Details (iframe content).
     */
    public function patientInfusions(Request $request): View
    {
        $patientId = $request->get('patient_id');
        $patient = Patient::find($patientId);

        $infusions = $patient 
            ? Infusion::with('infusionPump')
                ->where('patient_id', $patientId)
                ->latest('last_updated_at')
                ->get()
            : collect();

        $activeInfusions = $infusions->filter(fn($i) => in_array($i->status, ['running', 'paused', 'alarming']));
        $completedInfusions = $infusions->filter(fn($i) => $i->status === 'completed');

        return view('wards.patient-infusions', compact('patient', 'infusions', 'activeInfusions', 'completedInfusions'));
    }

    // ============================================
    // API Endpoints (for Infusion Pump Gateway)
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

        $apiUser = InfusionApiUser::where('username', $request->username)
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

        $this->logApiRequest($apiUser, '/api/infusion/login', 'POST', 
            ['username' => $request->username], 
            $responseData, 
            200, 
            $startTime
        );

        return response()->json($responseData);
    }

    /**
     * API: Receive HL7 infusion status update.
     * Supports HL7 ORU (Observation Result) messages for pump status.
     */
    public function apiReceiveStatus(Request $request)
    {
        $startTime = microtime(true);

        $apiUser = $this->validateBearerToken($request);
        
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'patient_mrn' => 'required|string',
            'device_id' => 'required|string',
            'medication_name' => 'required|string',
            'medication_code' => 'nullable|string',
            'total_volume' => 'nullable|numeric|min:0',
            'infused_volume' => 'nullable|numeric|min:0',
            'remaining_volume' => 'nullable|numeric|min:0',
            'flow_rate' => 'nullable|numeric|min:0',
            'dose_rate' => 'nullable|numeric|min:0',
            'dose_unit' => 'nullable|string',
            'remaining_minutes' => 'nullable|integer|min:0',
            'elapsed_minutes' => 'nullable|integer|min:0',
            'status' => 'required|in:pending,running,paused,completed,stopped,alarming',
            'alarm_type' => 'nullable|string',
            'alarm_message' => 'nullable|string',
            'timestamp' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            $responseData = [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ];

            $this->logApiRequest($apiUser, '/api/infusion/status', 'POST', 
                $request->all(), 
                $responseData, 
                422, 
                $startTime,
                'ORU'
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

            $this->logApiRequest($apiUser, '/api/infusion/status', 'POST', 
                $request->all(), 
                $responseData, 
                404, 
                $startTime,
                'ORU'
            );

            return response()->json($responseData, 404);
        }

        // Find or create pump
        $pump = InfusionPump::findOrCreateByDeviceId($request->device_id, [
            'ward_id' => $patient->ward_id,
        ]);
        $pump->updateLastSeen();

        // Find existing active infusion or create new one
        $infusion = Infusion::where('patient_id', $patient->id)
            ->where('infusion_pump_id', $pump->id)
            ->where('medication_name', $request->medication_name)
            ->whereIn('status', ['pending', 'running', 'paused', 'alarming'])
            ->first();

        $infusionData = [
            'patient_id' => $patient->id,
            'infusion_pump_id' => $pump->id,
            'medication_name' => $request->medication_name,
            'medication_code' => $request->medication_code,
            'total_volume' => $request->total_volume,
            'infused_volume' => $request->infused_volume ?? 0,
            'remaining_volume' => $request->remaining_volume,
            'flow_rate' => $request->flow_rate,
            'dose_rate' => $request->dose_rate,
            'dose_unit' => $request->dose_unit,
            'elapsed_minutes' => $request->elapsed_minutes ?? 0,
            'remaining_minutes' => $request->remaining_minutes,
            'status' => $request->status,
            'alarm_type' => $request->alarm_type,
            'alarm_message' => $request->alarm_message,
            'last_updated_at' => $request->timestamp ?? now(),
        ];

        // Calculate warning status
        $warningThreshold = 15; // minutes
        $infusionData['is_warning'] = ($request->status === 'running' && 
                                       $request->remaining_minutes !== null && 
                                       $request->remaining_minutes <= $warningThreshold);

        if ($infusion) {
            // Update existing infusion
            $infusion->update($infusionData);
            
            if ($request->status === 'running' && !$infusion->started_at) {
                $infusion->update(['started_at' => now()]);
            }
            if ($request->status === 'completed' && !$infusion->completed_at) {
                $infusion->update(['completed_at' => now()]);
            }
        } else {
            // Create new infusion
            $infusionData['started_at'] = $request->status === 'running' ? now() : null;
            $infusion = Infusion::create($infusionData);
        }

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'message' => 'Infusion status updated successfully',
            'data' => [
                'infusion_id' => $infusion->id,
                'patient_name' => $patient->name,
                'status' => $infusion->status,
                'is_warning' => $infusion->is_warning,
            ],
        ];

        $this->logApiRequest($apiUser, '/api/infusion/status', 'POST', 
            $request->all(), 
            $responseData, 
            200, 
            $startTime,
            'ORU'
        );

        return response()->json($responseData);
    }

    /**
     * API: Receive batch infusion updates.
     */
    public function apiReceiveBatchStatus(Request $request)
    {
        $startTime = microtime(true);

        $apiUser = $this->validateBearerToken($request);
        
        if (!$apiUser) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'infusions' => 'required|array|min:1',
            'infusions.*.patient_mrn' => 'required|string',
            'infusions.*.device_id' => 'required|string',
            'infusions.*.medication_name' => 'required|string',
            'infusions.*.status' => 'required|in:pending,running,paused,completed,stopped,alarming',
        ]);

        if ($validator->fails()) {
            $responseData = [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ];

            $this->logApiRequest($apiUser, '/api/infusion/batch-status', 'POST', 
                $request->all(), 
                $responseData, 
                422, 
                $startTime,
                'ORU'
            );

            return response()->json($responseData, 422);
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($request->infusions as $index => $data) {
            $patient = Patient::where('mrn', $data['patient_mrn'])->first();

            if (!$patient) {
                $results[] = [
                    'index' => $index,
                    'patient_mrn' => $data['patient_mrn'],
                    'status' => 'failed',
                    'message' => 'Patient not found',
                ];
                $failCount++;
                continue;
            }

            $pump = InfusionPump::findOrCreateByDeviceId($data['device_id'], [
                'ward_id' => $patient->ward_id,
            ]);
            $pump->updateLastSeen();

            $infusion = Infusion::where('patient_id', $patient->id)
                ->where('infusion_pump_id', $pump->id)
                ->where('medication_name', $data['medication_name'])
                ->whereIn('status', ['pending', 'running', 'paused', 'alarming'])
                ->first();

            $infusionData = [
                'patient_id' => $patient->id,
                'infusion_pump_id' => $pump->id,
                'medication_name' => $data['medication_name'],
                'medication_code' => $data['medication_code'] ?? null,
                'total_volume' => $data['total_volume'] ?? null,
                'infused_volume' => $data['infused_volume'] ?? 0,
                'remaining_volume' => $data['remaining_volume'] ?? null,
                'flow_rate' => $data['flow_rate'] ?? null,
                'remaining_minutes' => $data['remaining_minutes'] ?? null,
                'status' => $data['status'],
                'alarm_type' => $data['alarm_type'] ?? null,
                'alarm_message' => $data['alarm_message'] ?? null,
                'last_updated_at' => now(),
            ];

            $warningThreshold = 15;
            $infusionData['is_warning'] = ($data['status'] === 'running' && 
                                           isset($data['remaining_minutes']) && 
                                           $data['remaining_minutes'] <= $warningThreshold);

            if ($infusion) {
                $infusion->update($infusionData);
            } else {
                $infusionData['started_at'] = $data['status'] === 'running' ? now() : null;
                $infusion = Infusion::create($infusionData);
            }

            $results[] = [
                'index' => $index,
                'patient_mrn' => $data['patient_mrn'],
                'status' => 'success',
                'infusion_id' => $infusion->id,
            ];
            $successCount++;
        }

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'message' => "Processed {$successCount} infusions successfully, {$failCount} failed",
            'data' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ],
        ];

        $this->logApiRequest($apiUser, '/api/infusion/batch-status', 'POST', 
            $request->all(), 
            $responseData, 
            200, 
            $startTime,
            'ORU'
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

        $this->logApiRequest($apiUser, '/api/infusion/logout', 'POST', 
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
    private function validateBearerToken(Request $request): ?InfusionApiUser
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !Str::startsWith($authHeader, 'Bearer ')) {
            return null;
        }

        $token = Str::after($authHeader, 'Bearer ');
        
        return InfusionApiUser::findByToken($token);
    }

    /**
     * Log API request.
     */
    private function logApiRequest(
        InfusionApiUser $apiUser, 
        string $endpoint, 
        string $method, 
        array $requestData, 
        array $responseData, 
        int $statusCode,
        float $startTime,
        ?string $hl7MessageType = null
    ): void {
        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

        // Mask sensitive data
        if (isset($requestData['password'])) {
            $requestData['password'] = '***';
        }

        InfusionApiLog::create([
            'infusion_api_user_id' => $apiUser->id,
            'endpoint' => $endpoint,
            'method' => $method,
            'hl7_message_type' => $hl7MessageType,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status_code' => $statusCode,
            'ip_address' => request()->ip(),
            'response_time_ms' => $responseTimeMs,
        ]);
    }

    /**
     * Clear API logs.
     */
    public function clearLogs(Request $request)
    {
        if ($request->api_user_id) {
            InfusionApiLog::where('infusion_api_user_id', $request->api_user_id)->delete();
        } else {
            InfusionApiLog::truncate();
        }

        return redirect()->route('infusion-integration.index')
            ->with('success', 'Infusion API logs cleared successfully.');
    }
}














