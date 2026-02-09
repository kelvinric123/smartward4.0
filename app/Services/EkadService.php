<?php

namespace App\Services;

use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Models\EkadResponseLog;
use App\Models\Patient;
use App\Models\Bed;
use App\Models\ShiftSetting;
use App\Models\WardScheduleAssignment;
use App\Models\DietType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EkadService
{
    protected EkadConfiguration $config;

    public function __construct(?EkadConfiguration $config = null)
    {
        if ($config) {
            $this->config = $config;
        } else {
            $activeConfig = EkadConfiguration::getActive();
            if (!$activeConfig) {
                Log::warning('EKad: No active configuration found in database');
                // Create a blank config but log the issue
                $this->config = new EkadConfiguration();
            } else {
                $this->config = $activeConfig;
                Log::debug('EKad: Loaded active config', [
                    'has_username' => !empty($activeConfig->username),
                    'has_password' => !empty($activeConfig->password),
                    'has_token' => !empty($activeConfig->bearer_token),
                    'token_valid' => $activeConfig->isTokenValid(),
                ]);
            }
        }
    }

    /**
     * Login to SEEKINK API and store token
     */
    public function login(): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->config->base_url}/api/v1/user/login", [
                    'username' => $this->config->username,
                    'password' => $this->config->password,
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['code']) && $data['code'] === 200) {
                $token = $data['data'] ?? '';
                $this->config->setToken($token);

                Log::info('EKad: Login successful', ['username' => $this->config->username]);

                return [
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                ];
            }

            Log::warning('EKad: Login failed', ['response' => $data]);
            return [
                'success' => false,
                'message' => $data['msg'] ?? 'Login failed',
            ];
        } catch (\Exception $e) {
            Log::error('EKad: Login error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get valid token, refreshing if needed
     */
    public function getToken(): ?string
    {
        // Check if we have credentials before attempting login
        if (empty($this->config->username) || empty($this->config->password)) {
            Log::error('EKad: Cannot authenticate - missing credentials', [
                'has_username' => !empty($this->config->username),
                'has_password' => !empty($this->config->password),
                'config_id' => $this->config->id ?? null,
            ]);
            return null;
        }

        if (!$this->config->isTokenValid()) {
            Log::info('EKad: Token invalid or expired, attempting auto-login', [
                'has_token' => !empty($this->config->bearer_token),
                'expires_at' => $this->config->token_expires_at?->toIso8601String(),
            ]);

            $result = $this->login();
            if (!$result['success']) {
                Log::error('EKad: Auto-login failed', [
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                return null;
            }

            Log::info('EKad: Auto-login successful');
        }

        return $this->config->bearer_token;
    }

    /**
     * Normalize empty values to "-" to prevent EKAD from using cached data
     * All values are converted to uppercase for consistent display
     */
    protected function normalizeValue($value): string
    {
        if (is_null($value) || $value === '' || (is_string($value) && trim($value) === '')) {
            return '-';
        }
        // Convert to uppercase for EKAD display
        return mb_strtoupper((string) $value);
    }

    /**
     * Get patient info payload (without pushing)
     */
    public function getPatientInfoPayload(Patient $patient, Bed $bed, array $overrides = []): array
    {
        // Build patient data
        // Check for patient_name override first (e.g., 'vacant' for discharge)
        if (isset($overrides['patient_name'])) {
            $patientName = $overrides['patient_name'];
        } else {
            $patientName = $this->config->mask_patient_name
                ? $this->config->maskPatientName($patient->name)
                : $patient->name;
        }

        // Handle prebook patients - show "-" for patient name (will check status later)
        // MRN will be handled separately below
        if ($patient->status === 'prebook') {
            $patientName = '-';
        }

        $patientName = $this->normalizeValue($patientName);

        // Get diet type(s) - convert codes to names for display
        $dietType = '-';
        if (isset($overrides['diet_type'])) {
            $dietType = $overrides['diet_type'];
        } elseif ($patient->diet_types) {
            if (is_array($patient->diet_types)) {
                // Convert each diet code to its display name
                $dietNames = array_map(function ($code) {
                    return DietType::getDisplayName($code);
                }, $patient->diet_types);
                $dietType = implode(', ', $dietNames);
            } else {
                // Single diet type (legacy string format)
                $dietType = DietType::getDisplayName((string) $patient->diet_types);
            }
        }
        $dietType = $this->normalizeValue($dietType);

        // Get doctor info (Consultant)
        $doctor = '-';
        if (isset($overrides['doctor'])) {
            $doctor = $overrides['doctor'];
        } elseif ($patient->consultant) {
            $doctor = $patient->consultant->name;
        } else {
            // Fallback to active attending provider
            $attending = $patient->attendingDoctors()->first();
            if ($attending) {
                $doctor = $attending->display_name;
            }
        }
        $doctor = $this->normalizeValue($doctor);

        // Get nurse info
        $nurse = '-';
        if (isset($overrides['nurse'])) {
            $nurse = $overrides['nurse'];
        } elseif ($patient->nurse) {
            $nurse = $patient->nurse->name;
        } else {
            // Fallback to Ward Schedule (Roster) for today
            try {
                $wardId = $patient->ward_id;
                $currentShift = ShiftSetting::getCurrentShift($wardId);

                if ($currentShift) {
                    $assignment = WardScheduleAssignment::where('ward_id', $wardId)
                        ->where('bed_id', $bed->id)
                        ->where('scheduled_date', now()->toDateString())
                        ->where('shift', $currentShift->shift_code)
                        ->with('nurse')
                        ->first();

                    if ($assignment && $assignment->nurse) {
                        $nurse = $assignment->nurse->name;
                    }
                }
            } catch (\Exception $e) {
                // Ignore roster errors safely
                Log::warning('EkadService: Failed to fetch roster nurse', ['error' => $e->getMessage()]);
            }
        }
        $nurse = $this->normalizeValue($nurse);

        // Get anaesthetist info
        $anaesthetist = '-';
        if (isset($overrides['anaesthetist'])) {
            $anaesthetist = $overrides['anaesthetist'];
        } elseif ($patient->anaesthetist) {
            // Direct anaesthetist relationship (legacy)
            $anaesthetist = $patient->anaesthetist->name;
        } else {
            // Check care providers for referring/consulting doctors who are anaesthetists
            $anaesthetistProvider = $patient->careProviders()
                ->active()
                ->whereIn('role', ['referring', 'consulting'])
                ->whereNotNull('anaesthetist_id')
                ->with('anaesthetist')
                ->first();

            if ($anaesthetistProvider && $anaesthetistProvider->anaesthetist) {
                $anaesthetist = $anaesthetistProvider->anaesthetist->name;
            } else {
                // Fallback: Check for anaesthetist referrals (latest active one)
                $referral = $patient->referrals()
                    ->where('referral_type', 'anaesthetist')
                    ->where('status', 'active')
                    ->latest()
                    ->first();

                if ($referral && $referral->anaesthetist) {
                    $anaesthetist = $referral->anaesthetist->name;
                }
            }
        }
        $anaesthetist = $this->normalizeValue($anaesthetist);

        // Get bed number
        $bedNo = $overrides['bed_no'] ?? $patient->bed_number ?? '-';
        $bedNo = $this->normalizeValue($bedNo);

        // Get MRN
        $mrn = $overrides['mrn'] ?? $patient->mrn ?? '-';

        // Handle prebook patients - show "PREBOOK" for MRN
        if ($patient->status === 'prebook') {
            $mrn = 'PREBOOK';
        }

        $mrn = $this->normalizeValue($mrn);

        // Build data array in the exact order and format required by E-Ink API
        // Order matches the "Update Card" payload from Ekad Real.postman_collection.json
        return [
            'bed no' => $bedNo,
            'MRN' => $mrn,
            'patient_name' => $patientName,
            'diet_type' => $dietType,
            'doctor' => $doctor,
            'nurse' => $nurse,
            'anaesthetist' => $anaesthetist,
        ];
    }

    /**
     * Get payload for a bed (handles admitted, vacant, prebook logic)
     */
    public function getBedPayload(Bed $bed): array
    {
        // Check if bed is occupied
        $patient = $bed->patient;

        if ($patient && $patient->is_active && $patient->isAdmitted()) {
            // Bed Occupied - only real admissions (admitted/pending_discharge), not prebook
            return $this->getPatientInfoPayload($patient, $bed);
        } else {
            // Bed Vacant
            return [
                'bed no' => $this->normalizeValue($bed->bed_number),
                'MRN' => 'VACANT',
                'patient_name' => '-',
                'diet_type' => '-',
                'doctor' => '-',
                'nurse' => '-',
                'anaesthetist' => '-',
            ];
        }
    }

    /**
     * Push patient info to E-Ink device
     * 
     * @param Patient $patient
     * @param Bed $bed
     * @param array $overrides Optional array to override patient data (e.g. ['bed_no' => '-', 'mrn' => '-'])
     * @param string $eventType Description of the event (e.g., 'Admission', 'Discharge')
     */
    public function pushPatientInfo(Patient $patient, Bed $bed, array $overrides = [], string $eventType = 'observer'): array
    {
        // Get mapping for this bed
        $mapping = EkadBedMapping::getForBed($bed->id);
        if (!$mapping) {
            return [
                'success' => false,
                'message' => 'No E-Ink device mapped to this bed',
            ];
        }

        // Generate payload using the extracted method
        $data = $this->getPatientInfoPayload($patient, $bed, $overrides);

        return $this->pushToBed($mapping->mac_address, $data, $bed->id, $patient->id, $eventType);
    }

    /**
     * Push data to a specific MAC address
     */
    public function pushToBed(string $mac, array $data, ?int $bedId = null, ?int $patientId = null, string $eventType = 'observer'): array
    {
        $token = $this->getToken();
        if (!$token) {
            // Log failed authentication to database
            $this->logResponse($mac, null, null, null, false, 'Failed to obtain authentication token', $bedId, $patientId, $eventType);

            return [
                'success' => false,
                'message' => 'Failed to obtain authentication token',
            ];
        }

        $payload = [
            'id' => $this->config->template_id,
            'macList' => [EkadBedMapping::formatMac($mac)],
            'data' => [$data],
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->config->base_url}/api/v1/template/batchPaintingByJson", $payload);

            $result = $response->json();
            $responseCode = $response->status();

            if ($response->successful() && isset($result['code']) && $result['code'] === 200) {
                Log::info('EKad: Push successful', [
                    'mac' => $mac,
                    'patient_name' => $data['patient_name'] ?? 'unknown',
                ]);

                // Log successful response to database
                $this->logResponse($mac, $payload, $result, $responseCode, true, null, $bedId, $patientId, $eventType);

                return [
                    'success' => true,
                    'message' => 'Data pushed successfully',
                    'payload' => $payload,
                    'response' => $result,
                ];
            }

            // Check if token expired
            if (isset($result['code']) && $result['code'] === 401) {
                Log::info('EKad: Token expired, refreshing...');
                $this->config->clearToken();

                // Retry once with fresh token (don't log this attempt, the retry will log)
                return $this->pushToBed($mac, $data, $bedId, $patientId, $eventType);
            }

            Log::warning('EKad: Push failed', ['response' => $result]);

            // Log failed response to database
            $errorMessage = $result['msg'] ?? 'Push failed';
            $this->logResponse($mac, $payload, $result, $responseCode, false, $errorMessage, $bedId, $patientId, $eventType);

            return [
                'success' => false,
                'message' => $errorMessage,
                'payload' => $payload,
            ];
        } catch (\Exception $e) {
            Log::error('EKad: Push error', ['error' => $e->getMessage()]);

            // Log exception to database
            $this->logResponse($mac, $payload, null, null, false, 'Connection failed: ' . $e->getMessage(), $bedId, $patientId, $eventType);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Log API request/response to database
     */
    protected function logResponse(
        string $mac,
        ?array $requestPayload,
        ?array $responsePayload,
        ?int $responseCode,
        bool $success,
        ?string $errorMessage,
        ?int $bedId = null,
        ?int $patientId = null,
        string $triggeredBy = 'observer'
    ): void {
        try {
            EkadResponseLog::create([
                'bed_id' => $bedId,
                'patient_id' => $patientId,
                'mac_address' => $mac,
                'request_payload' => $requestPayload,
                'response_payload' => $responsePayload,
                'response_code' => $responseCode,
                'success' => $success,
                'error_message' => $errorMessage,
                'triggered_by' => $triggeredBy,
            ]);
        } catch (\Exception $e) {
            // Don't let logging failures break the main flow
            Log::error('EKad: Failed to log response to database', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Push patient to their assigned bed
     */
    public function pushPatientToBed(Patient $patient, string $eventType = 'observer'): array
    {
        $bed = $patient->bed;
        if (!$bed) {
            return [
                'success' => false,
                'message' => 'Patient has no bed assigned',
            ];
        }

        return $this->pushPatientInfo($patient, $bed, [], $eventType);
    }

    /**
     * Check if auto-push is enabled
     */
    public function isAutoPushEnabled(): bool
    {
        return $this->config->is_active && $this->config->auto_push_enabled;
    }
    /**
     * Push vacant status to bed (Discharge)
     */
    public function pushVacant(Bed $bed, string $eventType = 'discharge'): array
    {
        // Get mapping for this bed
        $mapping = EkadBedMapping::getForBed($bed->id);
        if (!$mapping) {
            return [
                'success' => false,
                'message' => 'No E-Ink device mapped to this bed',
            ];
        }

        $data = [
            'bed no' => $bed->bed_number,
            'MRN' => 'Vacant',
            'patient_name' => '-',
            'diet_type' => '-',
            'doctor' => '-',
            'nurse' => '-',
            'anaesthetist' => '-',
        ];

        return $this->pushToBed($mapping->mac_address, $data, $bed->id, null, $eventType);
    }
}
