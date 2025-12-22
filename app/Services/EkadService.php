<?php

namespace App\Services;

use App\Models\EkadConfiguration;
use App\Models\EkadBedMapping;
use App\Models\Patient;
use App\Models\Bed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EkadService
{
    protected EkadConfiguration $config;

    public function __construct(?EkadConfiguration $config = null)
    {
        $this->config = $config ?? EkadConfiguration::getActive() ?? new EkadConfiguration();
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
        if (!$this->config->isTokenValid()) {
            $result = $this->login();
            if (!$result['success']) {
                return null;
            }
        }

        return $this->config->bearer_token;
    }

    /**
     * Push patient info to E-Ink device
     */
    public function pushPatientInfo(Patient $patient, Bed $bed): array
    {
        // Get mapping for this bed
        $mapping = EkadBedMapping::getForBed($bed->id);
        if (!$mapping) {
            return [
                'success' => false,
                'message' => 'No E-Ink device mapped to this bed',
            ];
        }

        // Build patient data
        $patientName = $this->config->mask_patient_name
            ? $this->config->maskPatientName($patient->name)
            : $patient->name;

        // Get diet type(s)
        $dietType = '-';
        if ($patient->diet_types) {
            if (is_array($patient->diet_types)) {
                $dietType = implode(', ', $patient->diet_types);
            } else {
                $dietType = $patient->diet_types;
            }
        }

        // Get doctor info
        $doctor = '-';
        if ($patient->consultant) {
            $doctor = $patient->consultant->name ?? '-';
        }

        // Get nurse info
        $nurse = '-';
        if ($patient->nurse) {
            $nurse = $patient->nurse->name ?? '-';
        }

        // Get anaesthetist info
        $anaesthetist = '-';
        if ($patient->anaesthetist) {
            $anaesthetist = $patient->anaesthetist->name ?? '-';
        }

        // Get bed number from the patient's bed_number field (same as ward dashboard bed box)
        $bedNo = $patient->bed_number ?? '-';

        // Build data array in the exact order and format required by E-Ink API
        $data = [
            'bed no' => $bedNo,
            'MRN' => $patient->mrn ?? '-',
            'patient_name' => $patientName,
            'diet_type' => $dietType,
            'doctor' => $doctor,
            'nurse' => $nurse,
            'anaesthetist' => $anaesthetist,
        ];

        return $this->pushToBed($mapping->mac_address, $data);
    }

    /**
     * Push data to a specific MAC address
     */
    public function pushToBed(string $mac, array $data): array
    {
        $token = $this->getToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to obtain authentication token',
            ];
        }

        try {
            $payload = [
                'id' => $this->config->template_id,
                'macList' => [EkadBedMapping::formatMac($mac)],
                'data' => [$data],
            ];

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->config->base_url}/api/v1/template/batchPaintingByJson", $payload);

            $result = $response->json();

            if ($response->successful() && isset($result['code']) && $result['code'] === 200) {
                Log::info('EKad: Push successful', [
                    'mac' => $mac,
                    'patient_name' => $data['patient_name'] ?? 'unknown',
                ]);

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

                // Retry once with fresh token
                return $this->pushToBed($mac, $data);
            }

            Log::warning('EKad: Push failed', ['response' => $result]);
            return [
                'success' => false,
                'message' => $result['msg'] ?? 'Push failed',
                'payload' => $payload,
            ];
        } catch (\Exception $e) {
            Log::error('EKad: Push error', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Push patient to their assigned bed
     */
    public function pushPatientToBed(Patient $patient): array
    {
        $bed = $patient->bed;
        if (!$bed) {
            return [
                'success' => false,
                'message' => 'Patient has no bed assigned',
            ];
        }

        return $this->pushPatientInfo($patient, $bed);
    }

    /**
     * Check if auto-push is enabled
     */
    public function isAutoPushEnabled(): bool
    {
        return $this->config->is_active && $this->config->auto_push_enabled;
    }
}
