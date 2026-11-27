<?php

namespace App\Http\Controllers;

use App\Models\{PagoTramite, TramitePublico};
use App\Services\MercadoPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

class MercadoPagoController extends Controller
{
    private function solicitud(Request $request): TramitePublico
    {
        // Set by the "portal.acceso" middleware; the gateway return URL has no middleware.
        if ($request->attributes->has('tramite')) return $request->attributes->get('tramite');
        $acceso = $request->session()->get('portal_acceso');
        abort_unless(is_array($acceso) && ($acceso['expira'] ?? 0) > now()->timestamp, 403);
        return TramitePublico::where('codigo', $acceso['codigo'])->whereNotNull('email_verificado_at')->firstOrFail();
    }

    public function pagar(Request $request, MercadoPagoService $mp)
    {
        $tramite = $this->solicitud($request);
        abort_unless($tramite->identidad_estado === 'DATOS_CONTRASTADOS', 409);
        if (!$mp->disponible()) return redirect()->route('portal.estado')->with('error', 'Mercado Pago aún no está habilitado para las pruebas. No se realizó ningún cobro.');
        try {
            $bloqueos = Cache::store('file')->getStore();
            if (! $bloqueos instanceof \Illuminate\Contracts\Cache\LockProvider) throw new \RuntimeException('El almacén de caché no admite bloqueos.');
            return $bloqueos->lock('mp-checkout:'.$tramite->id, 45)->block(3, function () use ($tramite, $mp) {
                $pago = DB::transaction(function () use ($tramite) {
                    $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
                    abort_unless($actual->estado === 'PENDIENTE_PAGO', 409);
                    $pago = PagoTramite::firstOrCreate(['tramite_publico_id' => $actual->id], [
                        'proveedor' => 'MERCADOPAGO', 'idempotencia' => (string) Str::uuid(),
                        'importe_centimos' => config('portal.tarifa_centimos'),
                    ]);
                    // Never switch an existing gateway or overwrite payment history.
                    abort_unless($pago->proveedor === 'MERCADOPAGO' && $pago->estado !== 'PAGADO', 409);
                    abort_unless(!$pago->yape_intento, 409);
                    return $pago;
                });
                if (!$pago->mercadopago_checkout_url) {
                    // A preference is not a charge: retrying after a failed attempt reuses the
                    // same external_reference, so any payment still reconciles to this record.
                    $pago->update($mp->crear($pago));
                }
                abort_unless($mp->checkoutValido($pago->mercadopago_checkout_url), 503);
                return redirect()->away($pago->mercadopago_checkout_url)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException|\Illuminate\Http\Client\ConnectionException|\RuntimeException $e) {
            // HttpException extends RuntimeException: retain access/state rejection codes.
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) throw $e;
            return redirect()->route('portal.estado')->with('error', 'No se pudo abrir Mercado Pago. La solicitud se conserva; si el problema continúa, contacta a Registro Civil antes de volver a pagar.');
        }
    }

    public function retorno(Request $request, MercadoPagoService $mp)
    {
        $acceso = $request->session()->get('portal_acceso');
        $conSesion = is_array($acceso) && ($acceso['expira'] ?? 0) > now()->timestamp;
        $tramite = $conSesion ? $this->solicitud($request) : null;
        $id = $request->query('payment_id');
        $confirmado = false;
        if (is_string($id) && preg_match('/^[1-9][0-9]{0,29}$/D', $id)) {
            try {
                // confirmar() re-reads the payment from Mercado Pago and matches it to its own
                // reference, so it is safe even when the browser lost the portal session
                // (e.g. returning through a different host than the one used to start).
                $confirmado = (bool) $mp->confirmar($id, $tramite?->id);
            } catch (\Throwable $e) {
                // A browser status parameter is never proof of payment; it can be checked later.
            }
        }
        if (! $tramite) {
            return redirect()->route('portal.seguimiento')->with('exito', $confirmado
                ? 'Pago confirmado. Ingresa con tu DNI y correo para ver tu solicitud.'
                : 'Volviste de Mercado Pago. Ingresa con tu DNI y correo para consultar el resultado del pago.');
        }
        return $confirmado
            ? redirect()->route('portal.estado')->with('pago_confirmado', true)
            : redirect()->route('portal.estado')->with('exito', 'Volviste de Mercado Pago. Si ya pagaste, pulsa «Ya pagué, comprobar pago».');
    }

    public function comprobar(Request $request, MercadoPagoService $mp)
    {
        $tramite = $this->solicitud($request);
        $pago = $tramite->pago;
        abort_unless($pago && $pago->proveedor === 'MERCADOPAGO', 409);
        try {
            $resultado = $mp->comprobarPorReferencia($pago);
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo consultar a Mercado Pago en este momento. Revisa tu conexión e inténtalo de nuevo; no realices otro pago.');
        }
        return match ($resultado) {
            'aprobado' => redirect()->route('portal.estado')->with('pago_confirmado', true),
            'en_proceso' => back()->with('exito', 'Mercado Pago aún está procesando tu pago. Vuelve a comprobar en unos minutos.'),
            'rechazado' => back()->with('error', 'Mercado Pago rechazó el pago. No se realizó ningún cobro; puedes volver a intentarlo con «Continuar con Mercado Pago».'),
            default => back()->with('exito', 'Todavía no hay un pago registrado en Mercado Pago para esta solicitud.'),
        };
    }

    public function notificacion(Request $request, MercadoPagoService $mp)
    {
        $id = $mp->idNotificacion($request);
        abort_unless($mp->firmaValida($request, $id), 401);
        abort_unless($request->input('type') === 'payment', 422);
        if ($request->input('data.id') !== null) abort_unless((string) $request->input('data.id') === $id, 422);
        $mp->confirmar($id);
        return response('OK')->header('Cache-Control', 'no-store');
    }
}
