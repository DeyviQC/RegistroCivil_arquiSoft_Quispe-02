<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, TramitePublico};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MercadoPagoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mercadopago.mode' => 'test', 'mercadopago.access_token' => 'TEST-fictional',
            'mercadopago.webhook_secret' => 'fictional-secret', 'mercadopago.collector_id' => '777',
            'mercadopago.public_url' => 'https://municipio.example.test', 'portal.tarifa_centimos' => 1030]);
        Http::preventStrayRequests();
    }

    private function tramite(): TramitePublico
    {
        return TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(), 'estado' => 'PENDIENTE_PAGO',
            'identidad_estado' => 'DATOS_CONTRASTADOS', 'version' => 1, 'datos' => [],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
    }

    private function acceso(TramitePublico $t): static
    {
        return $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
    }

    private function pago(TramitePublico $t, string $proveedor = 'MERCADOPAGO'): PagoTramite
    {
        return PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => $proveedor,
            'idempotencia' => (string) Str::uuid(), 'importe_centimos' => 1030, 'estado' => 'PENDIENTE']);
    }

    private function respuesta(PagoTramite $p, array $changes = []): array
    {
        return array_replace(['id' => 123, 'live_mode' => false, 'collector_id' => 777,
            'external_reference' => $p->idempotencia, 'transaction_amount' => 10.30,
            'transaction_amount_refunded' => 0, 'currency_id' => 'PEN', 'status' => 'approved'], $changes);
    }

    private function aviso(array $body = ['type' => 'payment', 'data' => ['id' => '123']], bool $valid = true)
    {
        $ts = (string) now()->timestamp;
        $hash = hash_hmac('sha256', 'id:123;request-id:req-test;ts:'.$ts.';', 'fictional-secret');
        return $this->withHeaders(['x-request-id' => 'req-test', 'x-signature' => 'ts='.$ts.',v1='.($valid ? $hash : str_repeat('0', 64))])
            ->postJson('/pagos/mercadopago/notificacion?data.id=123', $body);
    }

    public function test_crea_checkout_de_prueba_y_reutiliza_una_sola_orden_sin_enviar_datos_personales(): void
    {
        $t = $this->tramite();
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => '777-pref-test', 'sandbox_init_point' => 'https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=777-pref-test',
        ])]);
        $this->acceso($t)->post(route('mercadopago.pagar'))->assertRedirect('https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=777-pref-test');
        $this->post(route('mercadopago.pagar'))->assertRedirect();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['items'][0]['unit_price'] === 10.30 && $r['items'][0]['currency_id'] === 'PEN'
            && !isset($r['payer']) && !str_contains($r->body(), $t->email)
            && $r['notification_url'] === 'https://municipio.example.test/pagos/mercadopago/notificacion');
        $this->assertSame(1, PagoTramite::count());
        $this->assertSame('PENDIENTE_PAGO', $t->fresh()->estado);
        $this->assertSame(0, $t->eventos()->count());
    }

    public function test_bloquea_sesion_vencida_identidad_proveedor_anterior_y_configuracion_productiva(): void
    {
        $t = $this->tramite();
        $this->post(route('mercadopago.pagar'))->assertForbidden();
        $t->update(['identidad_estado' => 'PENDIENTE']);
        $this->acceso($t)->post(route('mercadopago.pagar'))->assertStatus(409);
        $t->update(['identidad_estado' => 'DATOS_CONTRASTADOS']);
        $this->pago($t, 'IZIPAY');
        $this->post(route('mercadopago.pagar'))->assertStatus(409);
        config(['mercadopago.mode' => 'production']);
        $this->post(route('mercadopago.pagar'))->assertRedirect(route('portal.estado'))->assertSessionHas('error');
        $this->assertSame('IZIPAY', $t->fresh()->pago->proveedor);
        Http::assertNothingSent();
    }

    public function test_webhook_valida_firma_y_confirma_una_sola_vez(): void
    {
        $t = $this->tramite(); $p = $this->pago($t);
        Http::fake(['api.mercadopago.com/v1/payments/123' => Http::response($this->respuesta($p))]);
        $this->aviso(valid: false)->assertUnauthorized();
        Http::assertNothingSent();
        $this->aviso()->assertOk();
        $this->aviso()->assertOk();
        $this->assertSame('PAGADO', $p->fresh()->estado);
        $this->assertSame('123', $p->fresh()->mercadopago_pago_id);
        $this->assertSame('PENDIENTE', $t->fresh()->estado);
        $this->assertSame(2, $t->fresh()->version);
        $this->assertSame(1, $t->eventos()->count());
    }

    public function test_rechaza_importe_moneda_cuenta_produccion_y_reembolso_aunque_este_firmado(): void
    {
        $t = $this->tramite(); $p = $this->pago($t);
        $sequence = Http::sequence();
        foreach ([['transaction_amount' => 1], ['currency_id' => 'USD'], ['collector_id' => 999],
            ['live_mode' => true], ['transaction_amount_refunded' => 10.30], ['id' => 456]] as $bad) {
            $sequence->push($this->respuesta($p, $bad));
        }
        Http::fake(['api.mercadopago.com/v1/payments/123' => $sequence]);
        for ($i = 0; $i < 6; $i++) {
            $this->aviso()->assertStatus(422);
            $this->assertSame('PENDIENTE', $p->fresh()->estado);
        }
        $this->aviso(['type' => 'payment', 'data' => ['id' => 999]])->assertStatus(422);
        $this->assertSame(0, $t->eventos()->count());
    }

    public function test_retorno_no_confia_en_status_y_no_confirma_otra_solicitud(): void
    {
        $t = $this->tramite(); $p = $this->pago($t);
        $this->acceso($t)->get(route('mercadopago.retorno').'?status=approved')->assertRedirect(route('portal.estado'))->assertSessionMissing('pago_confirmado');
        Http::assertNothingSent();
        $other = $this->tramite(); $otherPay = $this->pago($other);
        Http::fake(['api.mercadopago.com/v1/payments/123' => Http::sequence()
            ->push($this->respuesta($p, ['status' => 'pending']))
            ->push($this->respuesta($otherPay))->push($this->respuesta($p))]);
        $this->get(route('mercadopago.retorno').'?payment_id=123&status=approved')->assertSessionMissing('pago_confirmado');
        $this->get(route('mercadopago.retorno').'?payment_id=123')->assertSessionMissing('pago_confirmado');
        $this->assertSame('PENDIENTE', $otherPay->fresh()->estado);
        $this->get(route('mercadopago.retorno').'?payment_id=123')->assertSessionHas('pago_confirmado', true);
    }

    public function test_comprobar_confirma_un_pago_aprobado_sin_webhook_ni_tunel(): void
    {
        $t = $this->tramite(); $p = $this->pago($t);
        $p->update(['mercadopago_preferencia_id' => 'pref-1']);
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [
                $this->respuesta($p, ['id' => 122, 'status' => 'rejected']), $this->respuesta($p),
            ]]),
            'api.mercadopago.com/v1/payments/123' => Http::response($this->respuesta($p)),
        ]);
        $this->acceso($t)->get(route('portal.estado'))->assertSee('Ya pagué, comprobar pago');
        $this->post(route('mercadopago.comprobar'))->assertRedirect(route('portal.estado'))->assertSessionHas('pago_confirmado', true);
        $this->assertSame('PAGADO', $p->fresh()->estado);
        $this->assertSame('PENDIENTE', $t->fresh()->estado);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'external_reference='.$p->idempotencia));
    }

    public function test_comprobar_informa_rechazo_y_no_confirma_pagos_de_otra_referencia(): void
    {
        $t = $this->tramite(); $p = $this->pago($t);
        $ajeno = $this->respuesta($p, ['id' => 999, 'external_reference' => 'otra-referencia']);
        Http::fake(['api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [
            $this->respuesta($p, ['status' => 'rejected']), $ajeno,
        ]])]);
        $this->acceso($t)->from(route('portal.estado'))->post(route('mercadopago.comprobar'))
            ->assertRedirect(route('portal.estado'))->assertSessionHas('error');
        $this->assertSame('PENDIENTE', $p->fresh()->estado);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/v1/payments/999'));
    }

    public function test_retorno_sin_sesion_confirma_con_mercado_pago_y_envia_al_seguimiento(): void
    {
        $t = $this->tramite(); $p = $this->pago($t);
        Http::fake(['api.mercadopago.com/v1/payments/123' => Http::response($this->respuesta($p))]);
        $this->get(route('mercadopago.retorno').'?payment_id=123')->assertRedirect(route('portal.seguimiento'))
            ->assertSessionHas('exito');
        $this->assertSame('PAGADO', $p->fresh()->estado);
    }

    public function test_reintenta_con_la_misma_referencia_y_nunca_redirige_a_url_ajena(): void
    {
        $t = $this->tramite();
        Http::fake(['api.mercadopago.com/checkout/preferences' => Http::response([
            'id' => '777-pref-test', 'sandbox_init_point' => 'https://evil.example.test/checkout',
        ])]);
        $this->acceso($t)->post(route('mercadopago.pagar'))->assertRedirect(route('portal.estado'))->assertSessionHas('error');
        $referencia = $t->fresh()->pago->idempotencia;
        // A failed creation no longer blocks the citizen; the retry keeps the same external_reference.
        $this->post(route('mercadopago.pagar'))->assertRedirect(route('portal.estado'))->assertSessionHas('error');
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r['external_reference'] === $referencia);
        $this->assertSame(1, \App\Models\PagoTramite::count());
        $this->assertSame('CREANDO', $t->fresh()->pago->estado);
        $this->assertNull($t->fresh()->pago->mercadopago_checkout_url);
    }

    public function test_acepta_app_usr_solo_tras_comprobar_vendedor_de_prueba_peruano(): void
    {
        config(['mercadopago.access_token' => 'APP_USR-fictional']);
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['id' => 777, 'site_id' => 'MPE', 'tags' => ['test_user']]),
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => '777-pref-test',
                'sandbox_init_point' => 'https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=test']),
        ]);
        $t = $this->tramite();
        $this->acceso($t)->post(route('mercadopago.pagar'))->assertRedirect('https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=test');
        Http::assertSentCount(2);
        $this->assertSame('PENDIENTE', $t->fresh()->pago->estado);
    }

    public function test_app_usr_de_cuenta_real_o_de_otro_vendedor_no_crea_checkout(): void
    {
        config(['mercadopago.access_token' => 'APP_USR-fictional']);
        Http::fake(['api.mercadopago.com/users/me' => Http::sequence()
            ->push(['id' => 777, 'site_id' => 'MPE', 'tags' => []])
            ->push(['id' => 888, 'site_id' => 'MPE', 'tags' => ['test_user']])
            ->push(['id' => 777, 'site_id' => 'MLA', 'tags' => ['test_user']])]);
        for ($i = 0; $i < 3; $i++) {
            $t = $this->tramite();
            $this->acceso($t)->post(route('mercadopago.pagar'))->assertRedirect(route('portal.estado'))->assertSessionHas('error');
            $this->assertNull($t->fresh()->pago->mercadopago_preferencia_id);
        }
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }
}
