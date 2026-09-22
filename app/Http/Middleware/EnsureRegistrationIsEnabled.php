<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public sign-up is off unless ALLOW_PUBLIC_REGISTRATION=true.
 *
 * Checked here rather than by registering the routes conditionally: route
 * definitions get baked into `route:cache`, so a conditional definition would
 * keep the old answer until routes were re-cached. A middleware reads config at
 * request time and is honest either way.
 */
class EnsureRegistrationIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.allow_registration'), 404);

        return $next($request);
    }
}
