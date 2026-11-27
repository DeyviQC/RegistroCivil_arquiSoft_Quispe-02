<?php

namespace App\Http\Controllers;

use App\Models\{PagoTramite, TramitePublico};
use App\Services\YapeOrdersService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

class YapeOrdersController extends Controller
{
    private function solicitud(Request $request): TramitePublico {
        $t = \App\Http\Middleware\EnsurePortalAccess::tramite($request);
        abort_unless($t->identidad_estado === 'DATOS_CONTRASTADOS', 409);
        return $t;
    }

    public function formulario(Request $request) {
        $t = $this->solicitud($request);
        return response()->view('portal.yape', ['tramite' => $t, 'disponible' => app(YapeOrdersService::class)->disponible()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function pagar(Request $request, YapeOrdersService $yape) {
        $t = $this->solicitud($request);
        // Allow only documented dummy values. Do not collect real OTPs or citizen data.
        $input = $request->validate([
            'celular' => ['required', 'regex:/^11111111[1-8]$/D'],
            'otp' => ['required', 'in:123456'],
        ], [
            'celular.required' => 'Ingresa tu número de celular.',
            'celular.regex' => 'En modo de prueba usa el celular 111111111 (pago aprobado). No ingreses tu número real.',
            'otp.required' => 'Ingresa el código de aprobación.',
            'otp.in' => 'Código de aprobación incorrecto. En modo de prueba usa 123456.',
        ]);
        if (!$yape->disponible()) return back()->with('error', 'Falta configurar la Public Key de prueba de Mercado Pago.');
        try {
            $bloqueos = Cache::store('file')->getStore();
            if (! $bloqueos instanceof \Illuminate\Contracts\Cache\LockProvider) throw new \RuntimeException('El almacén de caché no admite bloqueos.');
            $resultado = $bloqueos->lock('mp-checkout:'.$t->id, 90)->block(3, function () use ($t, $yape, $input) {
                $pago = DB::transaction(function () use ($t) {
                    $actual = TramitePublico::lockForUpdate()->findOrFail($t->id);
                    abort_unless($actual->estado === 'PENDIENTE_PAGO', 409);
                    $p = PagoTramite::firstOrCreate(['tramite_publico_id' => $t->id], [
                        'proveedor' => 'MERCADOPAGO', 'idempotencia' => (string) Str::uuid(),
                        'importe_centimos' => config('portal.tarifa_centimos'),
                    ]);
                    abort_unless($p->proveedor === 'MERCADOPAGO' && !$p->mercadopago_preferencia_id
                        && !$p->mercadopago_checkout_url && $p->estado !== 'PAGADO', 409);
                    return $p;
                });
                return $yape->ejecutar($pago, $input['celular'], $input['otp']);
            });
            if ($resultado === 'aprobado') return redirect()->route('portal.estado')->with('pago_confirmado', true);
            return back()->with($resultado === 'rechazado' ? 'error' : 'exito', $resultado === 'rechazado'
                ? 'Yape rechazó el pago (por ejemplo, saldo insuficiente). No se te cobró nada. Puedes intentarlo de nuevo o pagar con tarjeta.'
                : 'La orden sigue pendiente. Pulsa comprobar para consultar la misma orden, sin crear otro pago.');
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException|\Illuminate\Http\Client\ConnectionException|\RuntimeException $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) throw $e;
            return back()->with('error', 'No pudimos confirmar el pago con Yape. Pulsa «Comprobar mi pago» en unos segundos; no se te cobrará dos veces.');
        }
    }
}
