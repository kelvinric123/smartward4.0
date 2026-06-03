<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDeletePassphrase
{
    /**
     * The passphrase required for all DELETE operations.
     */
    private const PASSPHRASE = 'askdrtai';

    /**
     * Handle an incoming request.
     * All HTTP DELETE requests must include a valid 'delete_passphrase' field.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE')) {
            $passphrase = $request->input('delete_passphrase');

            if (!$passphrase || $passphrase !== self::PASSPHRASE) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Invalid or missing delete passphrase.',
                    ], 403);
                }

                return redirect()->back()->with('error', 'Delete failed: Invalid or missing passphrase. Please enter the correct passphrase to delete.');
            }
        }

        return $next($request);
    }
}
