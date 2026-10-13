<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ValidateSignature;

/**
 * Lets in a logged-in user, or anyone holding a valid signed URL that a page
 * for logged-in users generated (URL::temporarySignedRoute). For files that
 * open in iframes or new tabs, such as ECG PDFs, where the viewer may not
 * carry the session. Takes the same arguments as the `signed` middleware,
 * e.g. SignedUrlOrAuthenticated::relative(['download']).
 */
class SignedUrlOrAuthenticated extends ValidateSignature
{
    public function handle($request, Closure $next, ...$args)
    {
        if ($request->user()) {
            return $next($request);
        }

        return parent::handle($request, $next, ...$args);
    }
}
