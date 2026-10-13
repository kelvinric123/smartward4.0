<?php

namespace App\Http\Middleware;

use App\Models\Bed;
use App\Models\TerminalDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bedside terminal API (/api/terminal/beds/{bed}/...): identifies the
 * terminal by the bearer token it got from POST /api/terminal/pair and keeps
 * it to the bed it was paired with. A terminal that sends no token is let in
 * as unpaired, and the controller then masks the patient's identity, unless
 * config('terminal.require_device_token') turns unpaired terminals away.
 */
class AuthenticateTerminalDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            if (config('terminal.require_device_token')) {
                return $this->deny('This terminal is not paired. Pair it with its bed in Admin settings (nurse login).', 401);
            }

            return $next($request);
        }

        $device = TerminalDevice::findByToken($token);

        if (! $device) {
            return $this->deny('This terminal\'s pairing is no longer valid. Pair it again in Admin settings.', 401);
        }

        $bed = $request->route('bed');
        $bedId = $bed instanceof Bed ? $bed->id : $bed;

        if ($bedId !== null && (int) $bedId !== $device->bed_id) {
            return $this->deny('This terminal is paired with another bed. Pair it again to change beds.', 403, 'terminal_wrong_bed');
        }

        // Last seen, written at most once a minute per terminal
        if (! $device->last_seen_at || $device->last_seen_at->lt(now()->subMinute()) || $device->last_ip !== $request->ip()) {
            $device->forceFill(['last_seen_at' => now(), 'last_ip' => $request->ip()])->saveQuietly();
        }

        $request->attributes->set('terminal_device', $device);

        return $next($request);
    }

    private function deny(string $message, int $status, string $code = 'terminal_not_paired'): Response
    {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
