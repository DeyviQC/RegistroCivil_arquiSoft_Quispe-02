<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, TramitePublico};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class YapeOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function setupPago(): PagoTramite {
        config(['mercadopago.mode' => 'test', 'mercadopago.access_token' => 'APP_USR-fictional',
            'mercadopago.public_key' => 'APP_USR-fictional-public', 'mercadopago.collector_id' => '777',
            'mercadopago.webhook_secret' => 'fictional', 'mercadopago.public_url' => 'https://municipio.example.test',
            'portal.tarifa_centimos' => 1030]);
        Http::preventStrayRequests();
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(), 'estado' => 'PENDIENTE_PAGO',
            'identidad_estado' => 'DATOS_CONTRASTADOS', 'version' => 1, 'datos' => [],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
        return PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO',
            'estado' => 'PENDIENTE', 'importe_centimos' => 1030, 'idempotencia' => (string) Str::uuid()]);
    }

    private function fake(PagoTramite $p, array $changes = [], bool $realSeller = false): void {
        $data = array_replace(['id' => 'ORDPE123TEST', 'external_reference' => $p->idempotencia,
            'user_id' => '777', 'currency' => 'PEN', 'country_code' => 'PER', 'total_amount' => '10.30',
            'total_paid_amount' => '10.30', 'status' => 'processed', 'status_detail' => 'accredited',
            'transactions' => ['payments' => [['payment_method' => ['id' => 'yape'], 'status' => 'processed',
                'status_detail' => 'accredited', 'paid_amount' => '10.30']]]], $changes);
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['id' => 777, 'site_id' => 'MPE', 'tags' => $realSeller ? [] : ['test_user']]),
            'api.mercadopago.com/platforms/pci/yape/*' => Http::response(['id' => 'token-fictional']),
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORDPE123TEST']),
            'api.mercadopago.com/v1/orders/ORDPE123TEST' => Http::response($data),
        ]);
    }

    public function test_confirma_solo_orden_verificada_y_no_envia_datos_del_ciudadano(): void {
        $p = $this->setupPago(); $this->fake($p);
        $this->get(route('yape.formulario'))->assertOk()->assertSee('123456');
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertRedirect(route('portal.estado'));
        $this->assertSame('PAGADO', $p->fresh()->estado);
        $this->assertSame('PENDIENTE', $p->tramite->fresh()->estado);
        $this->assertSame(1, $p->tramite->eventos()->count());
        Http::assertSent(fn($r) => $r->url() === 'https://api.mercadopago.com/v1/orders'
            && $r['payer']['email'] === 'test_payer@example.com' && $r['total_amount'] === '10.30'
            && $r['processing_mode'] === 'automatic' && $r->hasHeader('X-Idempotency-Key'));
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertStatus(409);
        Http::assertSentCount(4);
    }

    public function test_no_permite_celular_real_ni_sesion_ajena_ni_otro_checkout(): void {
        $p = $this->setupPago();
        $this->post(route('yape.pagar'), ['celular' => '999223660', 'otp' => '123456'])->assertSessionHasErrors('celular');
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '654321'])->assertSessionHasErrors('otp');
        $this->post(route('yape.pagar'), ['celular' => '111111111'])->assertSessionHasErrors('otp');
        $p->update(['mercadopago_preferencia_id' => '777-pref']);
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertStatus(409);
        $this->withSession(['portal_acceso' => null])->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_orden_con_importe_incorrecto_no_confirma_ni_se_recrea(): void {
        $p = $this->setupPago(); $this->fake($p, ['total_amount' => '0.01']);
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertStatus(422);
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertStatus(422);
        $this->assertSame('PENDIENTE', $p->fresh()->estado);
        Http::assertSentCount(6); // second attempt reads only account and existing order.
        $this->post(route('mercadopago.pagar'))->assertStatus(409);
    }

    public function test_rechazo_no_aprueba_y_permite_reintentar_con_yape_o_tarjeta(): void {
        $p = $this->setupPago(); $this->fake($p, ['status' => 'failed', 'status_detail' => 'insufficient_amount']);
        $this->post(route('yape.pagar'), ['celular' => '111111113', 'otp' => '123456'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('PENDIENTE', $p->fresh()->estado);
        $this->assertSame(0, $p->tramite->eventos()->count());
        // The rejected attempt no longer blocks the request.
        $this->assertNull($p->fresh()->yape_intento);
        $this->get(route('portal.estado'))->assertOk()->assertSee('Pagar con Yape')->assertDontSee('en proceso');
        $this->post(route('yape.pagar'), ['celular' => '111111113', 'otp' => '123456'])->assertSessionHas('error');
        Http::assertSentCount(8); // two full attempts: account, token, order, read.
    }

    public function test_cuenta_productiva_no_envia_token_ni_orden(): void {
        $p = $this->setupPago(); $this->fake($p, [], true);
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertRedirect()->assertSessionHas('error');
        Http::assertSentCount(1);
        $this->assertNull($p->fresh()->yape_intento);
    }

    public function test_tras_un_timeout_reenvia_con_la_misma_clave_de_idempotencia(): void {
        $p = $this->setupPago();
        $this->fake($p);
        $clave = (string) Str::uuid();
        $p->update(['yape_intento' => $clave]);
        $this->post(route('yape.pagar'), ['celular' => '111111111', 'otp' => '123456'])->assertRedirect(route('portal.estado'));
        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/v1/orders' && $r->header('X-Idempotency-Key')[0] === $clave);
        $this->assertSame('PAGADO', $p->fresh()->estado);
    }

    public function test_saldo_insuficiente_402_al_crear_no_traba_la_solicitud(): void {
        $p = $this->setupPago();
        $orden = ['id' => 'ORDTST123', 'external_reference' => $p->idempotencia, 'user_id' => '777', 'currency' => 'PEN',
            'country_code' => 'PER', 'total_amount' => '10.30', 'total_paid_amount' => '0.00', 'status' => 'failed', 'status_detail' => 'failed'];
        Http::fake([
            'api.mercadopago.com/users/me' => Http::response(['id' => 777, 'site_id' => 'MPE', 'tags' => ['test_user']]),
            'api.mercadopago.com/platforms/pci/yape/*' => Http::response(['id' => 'token-fictional']),
            'api.mercadopago.com/v1/orders' => Http::response(['errors' => [['code' => 'failed']], 'data' => $orden], 402),
            'api.mercadopago.com/v1/orders/ORDTST123' => Http::response($orden),
        ]);
        $this->post(route('yape.pagar'), ['celular' => '111111113', 'otp' => '123456'])->assertSessionHas('error', fn ($m) => str_contains($m, 'saldo insuficiente'));
        $this->assertNull($p->fresh()->yape_intento);
        $this->assertNull($p->fresh()->yape_order_id);
        $this->assertSame('PENDIENTE', $p->fresh()->estado);
    }
}
