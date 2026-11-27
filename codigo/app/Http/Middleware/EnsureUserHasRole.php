<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de roles. Uso en rutas: ->middleware('role:administrador')
 * o ->middleware('role:administrador,asistente').
 * Complementa (no reemplaza) a las Policies.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->is_active) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }

        $tieneAlgunRol = collect($roles)->contains(fn ($rol) => $usuario->hasRole($rol));

        if (! $tieneAlgunRol) {
            abort(403, 'No tiene permisos para realizar esta acción.');
        }

        return $next($request);
    }
}
