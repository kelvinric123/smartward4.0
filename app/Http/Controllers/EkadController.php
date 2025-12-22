<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        $config = [
            'base_url' => $this->baseUrl,
            'username' => env('EKAD_USERNAME', 'moe'),
            'password' => env('EKAD_PASSWORD', 'moe123456'),
            'test_mac' => env('EKAD_TEST_MAC', 'D4:3D:39:3C:C0:2C'),
        ];

        return view('integration.ekad.index', compact('config'));
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

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/api/v1/user/login", [
                    'username' => $validated['username'],
                    'password' => $validated['password'],
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['code']) && $data['code'] === 200) {
                Log::info('EKad: Login successful', ['username' => $validated['username']]);
                return response()->json([
                    'success' => true,
                    'message' => 'Login successful!',
                    'token' => $data['data'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $data['msg'] ?? 'Login failed',
                    'code' => $data['code'] ?? null,
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('EKad: Login error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query available e-ink labels.
     */
    public function getLabels(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'base_url' => 'nullable|string',
            'page_num' => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
        ]);

        $baseUrl = $validated['base_url'] ?? $this->baseUrl;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $validated['token'],
                ])
                ->get("{$baseUrl}/api/v1/label/list", [
                    'pageNum' => $validated['page_num'] ?? 1,
                    'pageSize' => $validated['page_size'] ?? 10,
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['code']) && $data['code'] === 200) {
                return response()->json([
                    'success' => true,
                    'message' => 'Labels retrieved successfully!',
                    'data' => $data['data'] ?? [],
                    'total' => $data['total'] ?? 0,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $data['msg'] ?? 'Failed to retrieve labels',
                    'code' => $data['code'] ?? null,
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('EKad: Get labels error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
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
            'mac_list' => 'required|string',
            'patient_name' => 'required|string',
            'room_number' => 'nullable|string',
            'doctor' => 'nullable|string',
        ]);

        $baseUrl = $validated['base_url'] ?? $this->baseUrl;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $validated['token'],
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/api/v1/template/batchPaintingByJson", [
                    'id' => $validated['template_id'],
                    'macList' => $validated['mac_list'],
                    'data' => [
                        [
                            'patient_name' => $validated['patient_name'],
                            'room_number' => $validated['room_number'] ?? '',
                            'doctor' => $validated['doctor'] ?? '',
                        ],
                    ],
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['code']) && $data['code'] === 200) {
                Log::info('EKad: Push patient info successful', [
                    'mac' => $validated['mac_list'],
                    'patient' => $validated['patient_name'],
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Patient info pushed successfully!',
                    'data' => $data['data'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $data['msg'] ?? 'Failed to push patient info',
                    'code' => $data['code'] ?? null,
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
     * Send emergency text message to e-ink device.
     */
    public function sendTextMessage(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'base_url' => 'nullable|string',
            'mac_list' => 'required|array',
            'mac_list.*' => 'required|string',
            'priority' => 'nullable|integer|min:1|max:5',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $baseUrl = $validated['base_url'] ?? $this->baseUrl;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $validated['token'],
                    'Content-Type' => 'application/json',
                ])
                ->post("{$baseUrl}/api/v1/label/sendTextMessage", [
                    'macList' => $validated['mac_list'],
                    'priority' => $validated['priority'] ?? 1,
                    'title' => $validated['title'],
                    'content' => $validated['content'],
                ]);

            $data = $response->json();

            if ($response->successful() && isset($data['code']) && $data['code'] === 200) {
                Log::info('EKad: Text message sent', [
                    'macs' => $validated['mac_list'],
                    'title' => $validated['title'],
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Text message sent successfully!',
                    'data' => $data['data'] ?? null,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $data['msg'] ?? 'Failed to send text message',
                    'code' => $data['code'] ?? null,
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('EKad: Send text message error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
