<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers for every web response (AC04).
 *
 * The views still use inline scripts and style attributes, so the CSP allows
 * 'unsafe-inline' for them; it still blocks loading code from other sites,
 * framing by other sites (clickjacking), plugins and <base> hijacking.
 */
class CabecerasSeguridad
{
    private const CSP = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://code.jquery.com https://apps.firmaperu.gob.pe",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "worker-src 'self' blob:",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "base-uri 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Do not advertise the PHP version (set by expose_php in php.ini).
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $cabeceras = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => implode('; ', self::CSP),
        ];
        // HSTS and COOP are only honoured over HTTPS (browsers ignore them on plain HTTP).
        if ($request->isSecure()) {
            $cabeceras['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
            $cabeceras['Cross-Origin-Opener-Policy'] = 'same-origin';
        }
        foreach ($cabeceras as $nombre => $valor) {
            // Responses that already set a stricter policy (e.g. sandboxed downloads) keep theirs.
            if (! $response->headers->has($nombre)) {
                $response->headers->set($nombre, $valor);
            }
        }

        return $response;
    }
}
