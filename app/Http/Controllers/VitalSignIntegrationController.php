<?php

namespace App\Http\Controllers;

use App\Models\ApiUser;
use App\Models\VitalSign;
use App\Models\VitalSignApiLog;
use App\Models\VitalSignApiLogSummary;
use App\Models\QmedGateway;
use App\Models\Patient;
use App\Services\VitalSignApiLogPruner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VitalSignIntegrationController extends Controller
{
    /**
     * Display the Vital Sign Integration page.
     */
    public function index(Request $request)
    {
        $apiUsers = ApiUser::latest()->get();

        $recentLogs = collect();
        $isFiltered = false;

        // Filter Logic
        if ($request->has('api_user_id') || $request->has('duration')) {
            $isFiltered = true;
            $query = VitalSignApiLog::with('apiUser')->latest();

            if ($request->api_user_id && $request->api_user_id !== 'all') {
                $query->where('api_user_id', $request->api_user_id);
            }

            if ($request->duration) {
                switch ($request->duration) {
                    case '24h':
                        $query->where('created_at', '>=', now()->subHours(24));
                        break;
                    case '7d':
                        $query->where('created_at', '>=', now()->subDays(7));
                        break;
                    case '30d':
                        $query->where('created_at', '>=', now()->subDays(30));
                        break;
                    // 'all' case doesn't need a where clause
                }
            }

            $recentLogs = $query->paginate(50)->withQueryString();
        }
        // Default view: logs are not queried here at all — the Logs tab
        // lazy-loads them via the logs.table endpoint when it is opened.

        // Get Qmed gateways
        $gateways = QmedGateway::with('apiUsers', 'ward')->latest()->get();

        // Get gateway configuration for display
        $serverInfo = $this->getServerInfo();
        $gatewayConfig = [
            'passphrase' => config('services.vital_sign_api.passphrase', 'qmedno1'),
            'api_base_url' => url('/api/v1'),
            'server_url' => url('/'),
            'server_ip' => $serverInfo['ip'],
            'server_port' => $serverInfo['port'],
        ];

        return view('integration.vital-sign.index', compact('apiUsers', 'recentLogs', 'gatewayConfig', 'gateways', 'isFiltered'));
    }

    /**
     * Get server IP and port from APP_URL or request.
     */
    private function getServerInfo(): array
    {
        // First try to parse from APP_URL (most reliable)
        $appUrl = config('app.url', '');
        if ($appUrl) {
            $parsed = parse_url($appUrl);
            $ip = $parsed['host'] ?? '127.0.0.1';
            $port = $parsed['port'] ?? ($parsed['scheme'] === 'https' ? '443' : '80');

            // If host is localhost, try to get actual IP
            if ($ip === 'localhost') {
                $ip = $this->getLocalIp();
            }

            return ['ip' => $ip, 'port' => (string) $port];
        }

        // Fallback to request info
        $ip = request()->server('SERVER_ADDR') ?: $this->getLocalIp();
        $port = request()->server('SERVER_PORT') ?: '80';

        return ['ip' => $ip, 'port' => (string) $port];
    }

    /**
     * Store a new gateway-nurse binding.
     */
    public function storeBinding(Request $request)
    {
        $request->validate([
            'api_user_id' => 'required|exists:api_users,id',
            'nurse_id' => 'required|exists:nurses,id',
            'duration_hours' => 'required|integer|min:1|max:24',
        ]);

        $apiUser = ApiUser::findOrFail($request->api_user_id);

        // Close any existing active binding for this gateway
        $activeBinding = $apiUser->getActiveBinding();
        if ($activeBinding) {
            $activeBinding->update(['end_at' => now()]);
        }

        // Create new binding
        \App\Models\GatewayNurseBinding::create([
            'api_user_id' => $request->api_user_id,
            'nurse_id' => $request->nurse_id,
            'start_at' => now(),
            'end_at' => now()->addHours((int) $request->duration_hours),
        ]);

        return back()->with('success', 'Gateway successfully bound to nurse.');
    }

    /**
     * Unbind a gateway (end the binding immediately).
     */
    public function unbindGateway(\App\Models\GatewayNurseBinding $binding)
    {
        $binding->update(['end_at' => now()]);

        return back()->with('success', 'Gateway unbound successfully.');
    }

    /**
     * Get local IP address.
     */
    private function getLocalIp(): string
    {
        // Try to get from hostname
        $hostname = gethostname();
        $ip = gethostbyname($hostname);

        if ($ip !== $hostname && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        // Try socket connection method (works better on some systems)
        if (function_exists('socket_create')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock) {
                @socket_connect($sock, "8.8.8.8", 53);
                @socket_getsockname($sock, $localIp);
                @socket_close($sock);
                if ($localIp && filter_var($localIp, FILTER_VALIDATE_IP)) {
                    return $localIp;
                }
            }
        }

        return '127.0.0.1';
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
        $this->logApiRequest(
            $apiUser,
            '/api/vital-sign/login',
            'POST',
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
            'readings.*.patient_mrn' => 'nullable|string|required_without:readings.*.patient_rn',
            'readings.*.patient_rn' => 'nullable|string|required_without:readings.*.patient_mrn',
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

            $this->logApiRequest(
                $apiUser,
                '/api/vital-sign/readings',
                'POST',
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
            // Find patient by MRN or RN
            $patient = null;
            $mrn = $reading['patient_mrn'] ?? null;
            $rn = $reading['patient_rn'] ?? null;

            if ($mrn) {
                $patient = Patient::where('mrn', $mrn)->first();
            }
            if (!$patient && $rn) {
                $patient = Patient::where('rn', $rn)->first();
            }

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
            if (!$patient->isAdmitted()) {
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
                'reading_type' => 'single', // gateway source tracked in notes
                'notes' => 'Received via Gateway API' . (isset($reading['device_id']) ? ' (Device: ' . $reading['device_id'] . ')' : ''),
                'recorded_at' => isset($reading['recorded_at']) ? $reading['recorded_at'] : now(),
                'operator_id' => $apiUser->getActiveBinding()?->nurse_id,
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
            'ack' => true,  // Explicit ACK for machines to confirm receipt
            'message' => "Processed {$successCount} readings successfully, {$failCount} failed",
            'data' => [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'results' => $results,
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/vital-sign/readings',
            'POST',
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
            'patient_mrn' => 'nullable|string|required_without:patient_rn',
            'patient_rn' => 'nullable|string|required_without:patient_mrn',
            'patient_name' => 'nullable|string', // Ignored, but allowed in payload
            'systolic_bp' => 'nullable|integer|min:0|max:300',
            'diastolic_bp' => 'nullable|integer|min:0|max:200',
            'pulse_rate' => 'nullable|integer|min:0|max:300',
            'temperature' => 'nullable|numeric|min:30|max:45',
            'spo2' => 'nullable|integer|min:0|max:100',
            'respiratory_rate' => 'nullable|integer|min:0|max:100',
            'recorded_at' => 'nullable|date',
            'device_id' => 'nullable|string|max:100',
        ], [
            'patient_mrn.required_without' => 'Either Patient MRN or Patient RN is required',
            'patient_rn.required_without' => 'Either Patient MRN or Patient RN is required',
        ]);

        if ($validator->fails()) {
            $responseData = [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ];

            $this->logApiRequest(
                $apiUser,
                '/api/vital-sign/reading',
                'POST',
                $request->all(),
                $responseData,
                422,
                $startTime
            );

            return response()->json($responseData, 422);
        }

        // Find patient by MRN or RN
        $patient = null;
        if ($request->patient_mrn) {
            $patient = Patient::where('mrn', $request->patient_mrn)->first();
        }
        if (!$patient && $request->patient_rn) {
            $patient = Patient::where('rn', $request->patient_rn)->first();
        }

        if (!$patient) {
            $responseData = [
                'success' => false,
                'message' => 'Patient not found',
            ];

            $this->logApiRequest(
                $apiUser,
                '/api/vital-sign/reading',
                'POST',
                $request->all(),
                $responseData,
                404,
                $startTime
            );

            return response()->json($responseData, 404);
        }

        // Check if patient has an active admission
        if (!$patient->isAdmitted()) {
            $responseData = [
                'success' => false,
                'message' => 'Patient is not currently admitted',
            ];

            $this->logApiRequest(
                $apiUser,
                '/api/vital-sign/reading',
                'POST',
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
            'reading_type' => 'single', // gateway source tracked in notes
            'notes' => 'Received via Gateway API' . ($request->device_id ? ' (Device: ' . $request->device_id . ')' : ''),
            'recorded_at' => $request->recorded_at ?? now(),
            'operator_id' => $apiUser->getActiveBinding()?->nurse_id,
        ]);

        $apiUser->incrementRequestCount();

        $responseData = [
            'success' => true,
            'ack' => true,  // Explicit ACK for machines to confirm receipt
            'message' => 'Vital sign recorded successfully',
            'data' => [
                'vital_sign_id' => $vitalSign->id,
                'patient_name' => $patient->name,
                'recorded_at' => $vitalSign->recorded_at->toIso8601String(),
            ],
        ];

        $this->logApiRequest(
            $apiUser,
            '/api/vital-sign/reading',
            'POST',
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

        $this->logApiRequest(
            $apiUser,
            '/api/vital-sign/logout',
            'POST',
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
     * Render the lazy-loaded logs table (recent raw logs + daily summaries).
     */
    public function logsTable()
    {
        // latest('id') = insertion order over the primary key: fast on any table size.
        $recentLogs = VitalSignApiLog::with('apiUser')
            ->latest('id')
            ->limit(100)
            ->get();

        $summaries = VitalSignApiLogSummary::with('apiUser')
            ->where('summary_date', '>=', now()->subDays(30)->toDateString())
            ->orderByDesc('summary_date')
            ->orderByDesc('total_requests')
            ->limit(200)
            ->get();

        return view('integration.vital-sign.partials.logs-table', [
            'recentLogs' => $recentLogs,
            'isFiltered' => false,
            'summaries' => $summaries,
        ]);
    }

    /**
     * Clear API logs (daily summaries are saved before deleting).
     */
    public function clearLogs(Request $request, VitalSignApiLogPruner $pruner)
    {
        $result = $pruner->summarizeAndDelete(null, $request->api_user_id ?: null);

        return redirect()->route('vital-sign-integration.index')
            ->with('success', "API logs cleared ({$result['deleted']} deleted). Daily summaries saved.");
    }

    /**
     * Export API Logs to CSV.
     */
    public function exportLogs(Request $request)
    {
        $fileName = 'api_logs_' . date('Y-m-d_H-i-s') . '.csv';

        $query = VitalSignApiLog::with('apiUser')->latest();

        if ($request->api_user_id && $request->api_user_id !== 'all') {
            $query->where('api_user_id', $request->api_user_id);
        }

        if ($request->duration) {
            switch ($request->duration) {
                case '24h':
                    $query->where('created_at', '>=', now()->subHours(24));
                    break;
                case '7d':
                    $query->where('created_at', '>=', now()->subDays(7));
                    break;
                case '30d':
                    $query->where('created_at', '>=', now()->subDays(30));
                    break;
            }
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Time', 'User', 'Endpoint', 'Method', 'Status Code', 'Response Time (ms)', 'IP Address']);

            $query->chunk(500, function ($logs) use ($handle) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->apiUser->name ?? 'Unknown',
                        $log->endpoint,
                        $log->method,
                        $log->status_code,
                        $log->response_time_ms,
                        $log->ip_address,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Print API Logs view.
     */
    public function printLogs(Request $request)
    {
        $query = VitalSignApiLog::with('apiUser')->latest();

        if ($request->api_user_id && $request->api_user_id !== 'all') {
            $query->where('api_user_id', $request->api_user_id);
        }

        if ($request->duration) {
            switch ($request->duration) {
                case '24h':
                    $query->where('created_at', '>=', now()->subHours(24));
                    break;
                case '7d':
                    $query->where('created_at', '>=', now()->subDays(7));
                    break;
                case '30d':
                    $query->where('created_at', '>=', now()->subDays(30));
                    break;
            }
        }

        $logs = $query->limit(500)->get(); // Limit to 500 for printing to avoid crash

        return view('integration.vital-sign.print', compact('logs'));
    }

    // ============================================
    // Qmed Gateway Management
    // ============================================

    /**
     * Check whether a gateway is reachable (TCP connect to its SSH port).
     * Used by the "Ping" button on the dashboard.
     */
    public function pingGateway(QmedGateway $gateway)
    {
        $ip = $gateway->last_ping_ip;
        if (!$ip) {
            return response()->json([
                'reachable' => false,
                'message' => 'No known IP yet (waiting for first heartbeat).',
            ]);
        }
        $port = (int) ($gateway->ssh_port ?: 22);
        $start = microtime(true);
        $conn = @fsockopen($ip, $port, $errno, $errstr, 3);
        if ($conn) {
            fclose($conn);
            return response()->json([
                'reachable' => true,
                'ip' => $ip,
                'port' => $port,
                'ms' => (int) round((microtime(true) - $start) * 1000),
            ]);
        }
        return response()->json([
            'reachable' => false,
            'ip' => $ip,
            'port' => $port,
            'message' => "SSH port {$port} unreachable" . ($errstr ? " ({$errstr})" : ''),
        ]);
    }

    /**
     * Store a new Qmed gateway.
     */
    public function storeGateway(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:255|unique:qmed_gateways,mac_address',
            'api_users' => 'nullable|array',
            'api_users.*' => 'exists:api_users,id',
        ]);

        $gateway = QmedGateway::create($validated);

        if (!empty($validated['api_users'])) {
            $gateway->apiUsers()->sync($validated['api_users']);
        }

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'Qmed Gateway created successfully.');
    }

    /**
     * Update an existing Qmed gateway.
     */
    public function updateGateway(Request $request, QmedGateway $gateway)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:255|unique:qmed_gateways,mac_address,' . $gateway->id,
            'is_active' => 'boolean',
            'api_users' => 'nullable|array',
            'api_users.*' => 'exists:api_users,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $gateway->update($validated);

        if (isset($validated['api_users'])) {
            $gateway->apiUsers()->sync($validated['api_users']);
        } else {
            $gateway->apiUsers()->detach();
        }

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'Qmed Gateway updated successfully.');
    }

    /**
     * Delete a Qmed gateway.
     */
    public function destroyGateway(QmedGateway $gateway)
    {
        $gateway->delete();

        return redirect()->route('vital-sign-integration.index')
            ->with('success', 'Qmed Gateway deleted successfully.');
    }
}









