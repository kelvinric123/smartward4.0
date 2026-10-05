<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets in a system calling SmartWard's API (e.g. the C+ RPA) with the bearer
 * token of an active Integration User, generated on Users -> Edit. These
 * endpoints have no user session, so this is their only gate.
 */
class AuthenticateIntegrationUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::findByApiToken($request->bearerToken());

        if (! $user) {
            return response()->json([
                'message' => 'A valid API token of an active Integration User is required (Users > Edit > Generate token).',
            ], 401);
        }

        $user->forceFill(['api_token_last_used_at' => now()])->saveQuietly();
        $request->attributes->set('integration_user', $user);

        return $next($request);
    }
}
