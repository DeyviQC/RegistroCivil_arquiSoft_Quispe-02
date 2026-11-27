<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same attempt limits as Laravel's "throttle", except for load tests (AC01/AC03):
 * a request carrying the secret X-Prueba-Carga header skips the limit, so k6 measures
 * the application instead of the rate limiter. Never active in production, and only
 * when PRUEBAS_CARGA_TOKEN is configured.
 */
class ThrottleConPruebasCarga extends ThrottleRequests
{
    public function handle($request, Closure $next, ...$parametros): Response
    {
        if (self::esPruebaDeCarga($request)) {
            return $next($request);
        }

        // Laravel keys inline limits ("throttle:5,1") only by IP unless a prefix is given, so every
        // route shared ONE counter: downloading a PDF used up search and tracking attempts (found
        // by the AC04 matrix). Give each inline limit its own counter, scoped to its route.
        if (isset($parametros[0]) && is_numeric($parametros[0]) && count($parametros) < 3) {
            $parametros = [$parametros[0], $parametros[1] ?? 1, ($request->route()?->getName() ?? $request->path()).'|'];
        }

        return parent::handle($request, $next, ...$parametros);
    }

    public static function esPruebaDeCarga(Request $request): bool
    {
        $token = (string) config('pruebas.carga_token');

        return ! app()->environment('production')
            && strlen($token) >= 16
            && hash_equals($token, (string) $request->header('X-Prueba-Carga'));
    }
}
