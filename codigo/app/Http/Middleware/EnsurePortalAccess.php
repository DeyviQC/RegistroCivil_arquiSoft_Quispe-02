<?php

namespace App\Http\Middleware;

use App\Models\TramitePublico;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Citizen portal access: the session must hold a verified, unexpired access to one request.
 * The request is shared with the controller as $request->attributes->get('tramite').
 */
class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $acceso = $request->session()->get('portal_acceso');
        if (! is_array($acceso) || ($acceso['expira'] ?? 0) <= now()->timestamp) {
            // Pages the citizen opens (status, payment form, download) send them back to
            // tracking with an explanation instead of an error page.
            if ($request->isMethod('GET')) {
                return redirect()->route('portal.seguimiento')
                    ->with('aviso', 'Tu acceso venció o no está verificado. Ingresa tu DNI y correo para recibir un nuevo código.');
            }
            abort(403, 'Verifica nuevamente tu correo desde Seguimiento.');
        }
        $tramite = TramitePublico::where('codigo', $acceso['codigo'] ?? null)->whereNotNull('email_verificado_at')->firstOrFail();
        $request->attributes->set('tramite', $tramite);

        return $next($request);
    }

    public static function tramite(Request $request): TramitePublico
    {
        return $request->attributes->get('tramite') ?? abort(403);
    }
}
