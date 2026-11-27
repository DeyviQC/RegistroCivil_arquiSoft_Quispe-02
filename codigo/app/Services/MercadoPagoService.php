<?php

namespace App\Services;

use App\Models\PagoTramite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};

class MercadoPagoService
{
    public function disponible(): bool
    {
        return config('mercadopago.mode') === 'test'
            && preg_match('/^(TEST-|APP_USR-)[A-Za-z0-9_-]+$/D', (string) config('mercadopago.access_token'))
            && trim((string) config('mercadopago.webhook_secret')) !== ''
            && preg_match('/^[1-9][0-9]*$/D', (string) config('mercadopago.collector_id'))
            && $this->urlPublicaValida((string) config('mercadopago.public_url'));
    }

    private function urlPublicaValida(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && !empty($parts['host'])
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
            && !in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true);
    }

    private function http()
    {
        return Http::withToken(config('mercadopago.access_token'))->acceptJson()
            ->timeout(15)->connectTimeout(5)->withOptions(PortalHttpOptions::trusted());
    }

    public function checkoutValido(string $url): bool
    {
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https'
            && ($p['host'] ?? '') === 'sandbox.mercadopago.com.pe'
            && !isset($p['user']) && !isset($p['pass']) && !isset($p['port']);
    }

    public function crear(PagoTramite $pago): array
    {
        abort_unless($this->disponible() && $pago->importe_centimos === (int) config('portal.tarifa_centimos'), 503);
        if (str_starts_with(config('mercadopago.access_token'), 'APP_USR-')) {
            // Checkout Pro test sellers now use APP_USR credentials too. Check the account,
            // rather than mistaking the prefix for proof of sandbox credentials.
            $seller = $this->http()->get('https://api.mercadopago.com/users/me');
            if (!$seller->successful() || (string) $seller->json('id') !== (string) config('mercadopago.collector_id')
                || $seller->json('site_id') !== 'MPE' || !in_array('test_user', (array) $seller->json('tags'), true)) {
                throw new \RuntimeException('Se requiere la cuenta de vendedor de prueba de Perú.');
            }
        }
        $base = rtrim(config('mercadopago.public_url'), '/');
        // No DNI, acta content, names or citizen email are sent to the checkout.
        $response = $this->http()->post('https://api.mercadopago.com/checkout/preferences', [
            'items' => [['id' => 'copia-acta', 'title' => 'Solicitud de copia de acta', 'quantity' => 1,
                'currency_id' => 'PEN', 'unit_price' => $pago->importe_centimos / 100]],
            'external_reference' => $pago->idempotencia,
            'back_urls' => array_fill_keys(['success', 'failure', 'pending'], $base.'/pagos/mercadopago/retorno'),
            'auto_return' => 'approved',
            'notification_url' => $base.'/pagos/mercadopago/notificacion',
        ]);
        $url = $response->json('sandbox_init_point');
        $id = $response->json('id');
        if (!$response->successful() || !is_string($id) || !preg_match('/^[a-zA-Z0-9-]{1,200}$/D', $id)
            || !is_string($url) || !$this->checkoutValido($url)) {
            throw new \RuntimeException('No se pudo preparar el checkout de prueba.');
        }
        return ['mercadopago_preferencia_id' => $id, 'mercadopago_checkout_url' => $url, 'estado' => 'PENDIENTE'];
    }

    public function idNotificacion(Request $request): string
    {
        // PHP normalizes dots in query keys. Read only the query, never a conflicting JSON id.
        $id = $request->query('data_id') ?? $request->query('data.id');
        abort_unless(is_string($id) && preg_match('/^[1-9][0-9]{0,29}$/D', $id), 422);
        return $id;
    }

    public function firmaValida(Request $request, string $id): bool
    {
        if (!$this->disponible()) return false;
        $header = (string) $request->header('x-signature');
        $requestId = (string) $request->header('x-request-id');
        if (!preg_match('/^[a-zA-Z0-9-]{1,200}$/D', $requestId)) return false;
        $parts = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) $parts[trim($pair[0])] = trim($pair[1]);
        }
        if (!preg_match('/^[0-9]{10,13}$/D', $parts['ts'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D', $parts['v1'] ?? '')) return false;
        // Retries are idempotent; do not discard delayed legitimate deliveries by a short clock window.
        $manifest = 'id:'.$id.';request-id:'.$requestId.';ts:'.$parts['ts'].';';
        return hash_equals(hash_hmac('sha256', $manifest, config('mercadopago.webhook_secret')), $parts['v1']);
    }

    public function confirmar(string $id, ?int $tramiteEsperado = null): ?string
    {
        abort_unless($this->disponible() && preg_match('/^[1-9][0-9]{0,29}$/D', $id), 422);
        $response = $this->http()->get('https://api.mercadopago.com/v1/payments/'.$id);
        abort_unless($response->successful(), 503);
        $data = $response->json();
        abort_unless(is_array($data) && (string) ($data['id'] ?? '') === $id
            && ($data['live_mode'] ?? null) === false
            && (string) ($data['collector_id'] ?? '') === (string) config('mercadopago.collector_id'), 422);
        return DB::transaction(function () use ($data, $id, $tramiteEsperado) {
            $pago = PagoTramite::where('idempotencia', $data['external_reference'] ?? '')
                ->where('proveedor', 'MERCADOPAGO')->lockForUpdate()->firstOrFail();
            abort_unless($tramiteEsperado === null || $pago->tramite_publico_id === $tramiteEsperado, 403);
            $amount = $data['transaction_amount'] ?? null;
            abort_unless((is_int($amount) || is_float($amount) || is_string($amount))
                && preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', (string) $amount)
                && (int) round((float) $amount * 100) === $pago->importe_centimos
                && ($data['currency_id'] ?? '') === 'PEN'
                && (float) ($data['transaction_amount_refunded'] ?? 0) === 0.0, 422);
            if (($data['status'] ?? '') !== 'approved') return null;
            if ($pago->estado === 'PAGADO') {
                abort_unless($pago->mercadopago_pago_id === $id, 409);
                return $pago->tramite->codigo;
            }
            $tramite = $pago->tramite()->lockForUpdate()->firstOrFail();
            abort_unless($tramite->estado === 'PENDIENTE_PAGO', 409);
            $pago->update(['estado' => 'PAGADO', 'mercadopago_pago_id' => $id, 'pagado_at' => now()]);
            $tramite->transicionar('PENDIENTE', 'Pago confirmado. Tu solicitud pasó a revisión del Registro Civil.');
            return $tramite->codigo;
        });
    }

    /**
     * Asks Mercado Pago directly for the payments made against this order's reference.
     * Works without a public URL, tunnel or webhook (e.g. on a laptop): any approved
     * payment found is confirmed through confirmar(), which re-validates everything.
     *
     * @return string 'aprobado' | 'rechazado' | 'en_proceso' | 'sin_pagos'
     */
    public function comprobarPorReferencia(PagoTramite $pago): string
    {
        abort_unless($this->disponible() && $pago->proveedor === 'MERCADOPAGO', 422);
        if ($pago->estado === 'PAGADO') return 'aprobado';
        $response = $this->http()->get('https://api.mercadopago.com/v1/payments/search', [
            'external_reference' => $pago->idempotencia, 'sort' => 'date_created', 'criteria' => 'desc', 'limit' => 20,
        ]);
        abort_unless($response->successful(), 503);
        $pagos = collect((array) $response->json('results'))->filter(fn ($p) => is_array($p)
            && ($p['external_reference'] ?? null) === $pago->idempotencia
            && (string) ($p['collector_id'] ?? '') === (string) config('mercadopago.collector_id'));
        foreach ($pagos->where('status', 'approved') as $aprobado) {
            if ($this->confirmar((string) $aprobado['id'], $pago->tramite_publico_id)) return 'aprobado';
        }
        if ($pagos->isEmpty()) return 'sin_pagos';
        return $pagos->contains(fn ($p) => in_array($p['status'] ?? '', ['pending', 'in_process', 'authorized'], true))
            ? 'en_proceso' : 'rechazado';
    }
}
