<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response, for the hospital VAPT / DAST scans
 * (October 2026). A header a page already sets is left as it is.
 *
 * - X-Frame-Options / frame-ancestors: pages can only be framed by this
 *   same site (the app's own iframes keep working), not by other sites.
 * - X-Content-Type-Options: browsers trust the declared file type.
 * - Referrer-Policy: other sites see only this site's address, not the path.
 * - Permissions-Policy: location, payment and USB are off; camera and
 *   microphone are left alone.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "frame-ancestors 'self'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), payment=(), usb=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
