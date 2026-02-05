<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SiemUserRestriction
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // If not logged in, pass through (let 'auth' middleware handle it if needed)
        if (!$user) {
            return $next($request);
        }

        // Only restrict if user has the specific SIEM auditor role
        if ($user->role === User::ROLE_SIEM_AUDITOR) {
            $routeName = $request->route() ? $request->route()->getName() : null;

            // Allowed routes list (wildcards supported manually)
            $allowedRoutes = [
                'dashboard', // Landing page often needed
                'profile.edit',
                'profile.update',
                'logout', // Basic account management
                'user-activities.index',
                'user-activities.export',
                'ekad.activity-logs',
                'ekad.response-logs',
                'ekad.response-logs.export',
                'adt.logs',
                'adt.logs.view',
                'adt.log-files',
                'vital-sign-integration.logs', // Added consistent with other logs
                'infusion-integration.logs', // Added consistent with other logs (if exists in route list)
            ];

            // Check exact match
            if (in_array($routeName, $allowedRoutes)) {
                return $next($request);
            }

            // Ideally we'd want to allow some read-only views if needed, but per requirement:
            // "only read only priviledge for the user activies and config changes log"

            // Allow basic dashboard access? The user explicitly asked for "user user activies and config changes log". 
            // Often dashboards have widgets. Let's assume strict compliance.
            // If the user tries to access anything else, abort.

            abort(403, 'Unauthorized access for SIEM Auditor.');
        }

        return $next($request);
    }
}
