<?php

namespace App\Http\Controllers;

use App\Models\EkadConfiguration;
use App\Services\EkadScreenStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Screen Status on /ekad: each screen's battery, signal, online state and last
 * paint, as SEEKINK reports them
 */
class EkadScreenStatusController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->fromSeekink(fn (EkadScreenStatusService $status) => $status->report());
    }

    public function paints(string $mac): JsonResponse
    {
        return $this->fromSeekink(fn (EkadScreenStatusService $status) => ['paints' => $status->paintHistory($mac)]);
    }

    protected function fromSeekink(callable $read): JsonResponse
    {
        $config = EkadConfiguration::getActive();
        if (! $config || ! $config->username || ! $config->password) {
            return response()->json(['success' => false, 'message' => 'Set up Configuration & Login first.']);
        }

        try {
            return response()->json(['success' => true] + $read(new EkadScreenStatusService($config)));
        } catch (\Throwable $e) {
            Log::warning('EKad: Screen status unavailable', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
