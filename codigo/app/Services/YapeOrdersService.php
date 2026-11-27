<?php

namespace App\Services;

use App\Models\PagoTramite;
use Illuminate\Support\Facades\{DB, Http};
use Illuminate\Support\Str;

class YapeOrdersService
{
    private function http() {
        return Http::acceptJson()->withOptions(PortalHttpOptions::trusted())->timeout(20)->connectTimeout(5);
    }

    public function disponible(): bool {
        return app(MercadoPagoService::class)->disponible()
            && preg_match('/^(APP_USR-|TEST-)[A-Za-z0-9_-]+$/D', (string) config('mercadopago.public_key'));
    }

    public function ejecutar(PagoTramite $pago, string $celular, string $otp = '123456'): string {
        abort_unless($this->disponible() && preg_match('/^11111111[1-8]$/D', $celular) && $otp === '123456'
            && $pago->importe_centimos === (int) config('portal.tarifa_centimos'), 422);
        $http = $this->http()->withToken(config('mercadopago.access_token'));
        // Verify the seller on every attempt: a prefix alone is not proof of test credentials.
        $seller = $http->get('https://api.mercadopago.com/users/me');
        throw_unless($seller->successful() && $seller->json('site_id') === 'MPE'
            && (string) $seller->json('id') === (string) config('mercadopago.collector_id')
            && in_array('test_user', (array) $seller->json('tags'), true), \RuntimeException::class, 'Cuenta de prueba no verificada.');
        if (!$pago->yape_order_id) {
            $token =$this->http()->post('https://api.mercadopago.com/platforms/pci/yape/v1/payment?'.http_build_query([
                'public_key' => config('mercadopago.public_key'),
            ]), ['phoneNumber' => $celular, 'otp' => $otp, 'requestId' => (string) Str::uuid()]);
            throw_unless($token->successful() && is_string($token->json('id'))
                && preg_match('/^[A-Za-z0-9_-]{1,200}$/D', $token->json('id')), \RuntimeException::class, 'No se pudo generar el token de prueba.');
            // Persist the idempotency key BEFORE sending: after a timeout the next click
            // resends the same key, so Mercado Pago returns the same order instead of a new one.
            if (!$pago->yape_intento) $pago->update(['yape_intento' => (string) Str::uuid()]);
            $amount = number_format($pago->importe_centimos / 100, 2, '.', '');
            $response = $http->withHeaders(['X-Idempotency-Key' => $pago->yape_intento])->post('https://api.mercadopago.com/v1/orders', [
                'type' => 'online', 'external_reference' => $pago->idempotencia,
                'processing_mode' => 'automatic', 'total_amount' => $amount,
                'payer' => ['email' => 'test_payer@example.com', 'entity_type' => 'individual',
                    'identification' => ['type' => 'DNI', 'number' => '12345678'],
                    'phone' => ['area_code' => '51', 'number' => $celular]],
                'transactions' => ['payments' => [['amount' => $amount,
                    'payment_method' => ['id' => 'yape', 'type' => 'debit_card', 'token' => $token->json('id')]]]],
            ]);
            // A rejected Yape (e.g. insufficient balance) answers 402 with the failed order in "data".
            $id = $response->successful() ? $response->json('id') : $response->json('data.id');
            if (!is_string($id) || !preg_match('/^ORD[A-Za-z0-9]{1,100}$/D', $id)) {
                // A 4xx with no order means nothing was created: allow a clean new attempt.
                if ($response->clientError()) $pago->update(['yape_intento' => null]);
                throw new \RuntimeException('Orden pendiente de reconciliar.');
            }
            $pago->update(['yape_order_id' => $id]);
        }
        throw_unless($pago->yape_order_id, \RuntimeException::class, 'Intento pendiente de reconciliar.');
        // Never approve from browser data or just the create response: read the order back.
        $result = $http->get('https://api.mercadopago.com/v1/orders/'.$pago->yape_order_id);
        $data = $result->json();
        throw_unless($result->successful() && is_array($data), \RuntimeException::class, 'No se pudo comprobar la orden.');
        abort_unless(($data['id'] ?? '') === $pago->yape_order_id
            && ($data['external_reference'] ?? '') === $pago->idempotencia
            && (string) ($data['user_id'] ?? '') === (string) config('mercadopago.collector_id')
            && ($data['currency'] ?? '') === 'PEN' && ($data['country_code'] ?? '') === 'PER'
            && $this->centimos($data['total_amount'] ?? null) === $pago->importe_centimos, 422);
        if (($data['status'] ?? '') === 'failed') {
            // A rejected Yape (e.g. insufficient balance) must not lock the request:
            // forget this attempt so the citizen can try again or pay by card.
            $pago->update(['yape_intento' => null, 'yape_order_id' => null]);
            return 'rechazado';
        }
        if (($data['status'] ?? '') !== 'processed' || ($data['status_detail'] ?? '') !== 'accredited') return 'pendiente';
        $payments = $data['transactions']['payments'] ?? [];
        abort_unless(count($payments) === 1
            && ($payments[0]['payment_method']['id'] ?? '') === 'yape'
            && ($payments[0]['status'] ?? '') === 'processed'
            && ($payments[0]['status_detail'] ?? '') === 'accredited'
            && $this->centimos($payments[0]['paid_amount'] ?? null) === $pago->importe_centimos
            && $this->centimos($data['total_paid_amount'] ?? null) === $pago->importe_centimos, 422);
        DB::transaction(function () use ($pago) {
            $actual = PagoTramite::lockForUpdate()->findOrFail($pago->id);
            if ($actual->estado === 'PAGADO') return;
            $tramite = $actual->tramite()->lockForUpdate()->firstOrFail();
            abort_unless($tramite->estado === 'PENDIENTE_PAGO', 409);
            $actual->update(['estado' => 'PAGADO', 'pagado_at' => now()]);
            $tramite->transicionar('PENDIENTE', 'Pago con Yape confirmado. Tu solicitud pasó a revisión del Registro Civil.');
        });
        return 'aprobado';
    }

    private function centimos($value): ?int {
        if (!is_scalar($value) || !preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/D', (string) $value)) return null;
        return (int) round((float) $value * 100);
    }
}
