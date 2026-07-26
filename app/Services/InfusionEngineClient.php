<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the Qmed Infusion Engine REST API.
 *
 * The engine is a standalone service (see infusion_engine/) that receives
 * pump HL7 over MLLP, stores + parses it, and serves the data as JSON.
 * SmartWard talks to it over HTTP only - no shared database.
 */
class InfusionEngineClient
{
    public const MODE_KEY = 'infusion.mode';         // 'local' | 'engine'
    public const CONFIG_KEY = 'infusion.engine';     // ['url', 'api_key', 'timeout']

    public const MODE_LOCAL = 'local';
    public const MODE_ENGINE = 'engine';

    public function __construct(
        protected string $baseUrl,
        protected string $apiKey = '',
        protected int $timeout = 5,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Build a client from the saved integration settings.
     */
    public static function fromSettings(): self
    {
        $config = IntegrationSetting::get(self::CONFIG_KEY, []);

        return new self(
            $config['url'] ?? 'http://127.0.0.1:6001',
            $config['api_key'] ?? '',
            (int) ($config['timeout'] ?? 5),
        );
    }

    /**
     * The currently selected integration mode ('local' or 'engine').
     */
    public static function mode(): string
    {
        return IntegrationSetting::get(self::MODE_KEY, self::MODE_LOCAL);
    }

    public static function engineModeActive(): bool
    {
        return static::mode() === self::MODE_ENGINE;
    }

    // ------------------------------------------------------------------ calls

    protected function request()
    {
        // Short connect timeout: a firewalled/unroutable engine URL fails in
        // ~2s instead of burning the full response timeout per call (the
        // integration page makes these calls synchronously on load).
        $request = Http::timeout($this->timeout)
            ->connectTimeout(min($this->timeout, 2))
            ->acceptJson();

        if ($this->apiKey !== '') {
            $request = $request->withHeaders(['X-API-Key' => $this->apiKey]);
        }

        return $request;
    }

    /**
     * Engine liveness + row counts. Never requires the API key.
     */
    public function health(): array
    {
        return $this->request()->get($this->baseUrl . '/health')->throw()->json();
    }

    /**
     * Latest state of every pump.
     */
    public function pumps(): array
    {
        return $this->request()->get($this->baseUrl . '/api/pumps')
            ->throw()->json('pumps', []);
    }

    /**
     * One pump with recent readings and alarms. Null when unknown.
     */
    public function pump(string $deviceId): ?array
    {
        $response = $this->request()->get($this->baseUrl . '/api/pumps/' . rawurlencode($deviceId));

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Patient infusion info by MRN. Null when the MRN has no infusion data.
     */
    public function patient(string $mrn): ?array
    {
        $response = $this->request()->get($this->baseUrl . '/api/patients/' . rawurlencode($mrn));

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Alarms across all pumps (state: 'active' | 'inactive' | null for all).
     */
    public function alarms(?string $state = null, int $limit = 100): array
    {
        return $this->request()->get($this->baseUrl . '/api/alarms', array_filter([
            'state' => $state,
            'limit' => $limit,
        ]))->throw()->json('alarms', []);
    }

    /**
     * Raw HL7 message log metadata.
     */
    public function messages(array $filters = []): array
    {
        return $this->request()->get($this->baseUrl . '/api/messages', $filters)
            ->throw()->json('messages', []);
    }

    /**
     * Try the connection and report a friendly result (never throws).
     * Checks /health first, then verifies the API key against /api/stats.
     */
    public function testConnection(): array
    {
        try {
            $health = $this->health();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => 'Cannot reach engine: ' . $e->getMessage(),
            ];
        }

        // /health is open - also verify the API key works on a protected endpoint
        $stats = $this->request()->get($this->baseUrl . '/api/stats');
        if ($stats->status() === 401) {
            return [
                'ok' => false,
                'health' => $health,
                'error' => 'Engine reachable, but the API key was rejected (401).',
            ];
        }

        return [
            'ok' => true,
            'health' => $health,
        ];
    }
}
