<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, TramitePublico};
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PagoLimiteTest extends TestCase
{
    use RefreshDatabase;

    private const CHECKOUT = 'https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-prueba';

    private function tramite(): TramitePublico
    {
        return TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'email' => 'prueba@example.test', 'email_verificado_at' => now(), 'estado' => 'PENDIENTE_PAGO',
            'identidad_estado' => 'DATOS_CONTRASTADOS', 'datos' => [], 'terminos_version' => 'test', 'aceptado_at' => now()]);
    }

    private function conAcceso(TramitePublico $t): void
    {
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_el_flujo_previo_no_bloquea_el_pago_y_los_intentos_repetidos_si_se_limitan(): void
    {
        Http::preventStrayRequests();
        config(['portal.tarifa_centimos' => 1030]);
        $t = $this->tramite();
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('disponible')->times(4)->andReturn(true);
            $mock->shouldReceive('crear')->once()->andReturn(['mercadopago_preferencia_id' => 'pref-prueba', 'mercadopago_checkout_url' => self::CHECKOUT, 'estado' => 'PENDIENTE']);
            $mock->shouldReceive('checkoutValido')->times(4)->andReturn(true);
        });
        // These requests consumed the same unnamed IP bucket as checkout before the fix.
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('portal.buscar'), [])->assertSessionHasErrors();
        }
        $this->conAcceso($t);
        for ($i = 0; $i < 3; $i++) {
            $this->post(route('mercadopago.pagar'))->assertRedirect(self::CHECKOUT);
        }
        $this->post(route('mercadopago.pagar'))->assertStatus(429)
            ->assertSee('Espera un momento')->assertHeader('Retry-After')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, PagoTramite::count());
        $this->assertSame('PENDIENTE', $t->fresh()->pago->estado);
        $this->assertSame('PENDIENTE_PAGO', $t->fresh()->estado);
        $this->assertSame(0, $t->eventos()->count());
        $this->travel(61)->seconds();
        $this->post(route('mercadopago.pagar'))->assertRedirect(self::CHECKOUT);
        $this->assertSame(1, PagoTramite::count());
        Http::assertNothingSent();
    }

    public function test_un_fallo_al_crear_el_checkout_permite_reintentar_con_la_misma_referencia(): void
    {
        config(['portal.tarifa_centimos' => 1030]);
        $t = $this->tramite();
        $this->mock(MercadoPagoService::class, function ($mock) {
            $mock->shouldReceive('disponible')->twice()->andReturn(true);
            $mock->shouldReceive('crear')->once()->andThrow(new \RuntimeException('Fallo de red simulado.'));
            $mock->shouldReceive('crear')->once()->andReturn(['mercadopago_preferencia_id' => 'pref-prueba', 'mercadopago_checkout_url' => self::CHECKOUT, 'estado' => 'PENDIENTE']);
            $mock->shouldReceive('checkoutValido')->once()->andReturn(true);
        });
        $this->conAcceso($t);
        $this->post(route('mercadopago.pagar'))->assertRedirect(route('portal.estado'))->assertSessionHas('error');
        $referencia = $t->fresh()->pago->idempotencia;
        $this->post(route('mercadopago.pagar'))->assertRedirect(self::CHECKOUT);
        $this->assertSame(1, PagoTramite::count());
        $this->assertSame($referencia, $t->fresh()->pago->idempotencia);
    }
}
