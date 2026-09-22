<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireDeletePassphrase
{
    /**
     * The passphrase required for all DELETE operations, and for the edits that reuse it.
     */
    private const PASSPHRASE = 'askdrtai';

    /**
     * Check a passphrase supplied by a non-DELETE request (e.g. correcting a vital sign).
     */
    public static function matches(?string $passphrase): bool
    {
        return $passphrase !== null && hash_equals(self::PASSPHRASE, $passphrase);
    }

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

                // Keep the tab the form was on (Patient Details reads it back from the old input)
                return redirect()->back()
                    ->withInput($request->only('active_tab'))
                    ->with('error', 'Delete failed: Invalid or missing passphrase. Please enter the correct passphrase to delete.');
            }
        }

        return $next($request);
    }
}
