<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The REST API always answers JSON: validation errors (422), missing token (401),
 * forbidden (403) and not found (404) come back as JSON instead of HTML pages or
 * redirects, even if the client forgets the "Accept: application/json" header.
 */
class ForzarRespuestaJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
