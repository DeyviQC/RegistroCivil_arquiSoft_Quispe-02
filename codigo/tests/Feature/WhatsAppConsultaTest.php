<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, TramitePublico};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppConsultaTest extends TestCase
{
    use RefreshDatabase;

    private function tramite(string $pago): TramitePublico
    {
        Http::preventStrayRequests();
        config(['portal.whatsapp_contacto' => '15555550100']);
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'email' => 'privado@example.test', 'email_verificado_at' => now(), 'estado' => 'PENDIENTE',
            'datos' => ['nombre' => 'Solicitante privado', 'dni' => '77777777', 'relacion' => 'titular', 'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular privado']],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => $pago, 'idempotencia' => (string) Str::uuid()]);
        return $t;
    }

    private function sesion(TramitePublico $t): array
    {
        return ['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]];
    }

    public function test_despues_del_pago_ofrece_chat_solo_con_numero_de_solicitud_y_sin_enviar_nada(): void
    {
        $t = $this->tramite('PAGADO');
        $this->withSession($this->sesion($t))->get(route('portal.estado'))->assertOk()
            ->assertSee('Escribir a Registro Civil')->assertSee('Recomendado');

        // The citizen starts the chat: the button records the choice and opens WhatsApp.
        $url = $this->post(route('portal.whatsapp'))->assertRedirect()->headers->get('Location');
        $this->assertSame('/15555550100', parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('Hola, soy el solicitante de la solicitud N.º '.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT).' de Registro Civil. Ya realicé el pago y quiero recibir los avisos y mi documento por este WhatsApp.', $query['text']);
        foreach ([$t->codigo, '77777777', $t->email, 'Titular privado', 'Solicitante privado', '/tramite/documento'] as $dato) $this->assertStringNotContainsString($dato, urldecode($url));
        $canales = $t->fresh()->datos['canales_entrega'];
        $this->assertTrue($canales['whatsapp']);
        $this->assertNotEmpty($canales['whatsapp_iniciado_at']);
        $this->assertSame('PENDIENTE', $t->fresh()->estado);
        $this->assertSame(0, $t->eventos()->count());
        Http::assertNothingSent();

        // Saving the e-mail preference afterwards keeps the WhatsApp choice.
        $this->post(route('portal.canales'), ['correo' => 1])->assertRedirect(route('portal.estado'));
        $this->assertTrue($t->fresh()->datos['canales_entrega']['whatsapp']);
        $this->assertTrue($t->fresh()->datos['canales_entrega']['correo']);
    }

    public function test_no_ofrece_whatsapp_antes_de_confirmar_el_pago_ni_abre_el_seguimiento_sin_verificar(): void
    {
        $t = $this->tramite('CREANDO');
        $this->withSession($this->sesion($t))->get(route('portal.estado'))->assertOk()->assertDontSee('https://wa.me/', false);
        $this->withSession(['portal_acceso' => null])->get(route('portal.estado'))->assertRedirect(route('portal.seguimiento'));
        $this->assertSame(0, $t->eventos()->count());
    }

    public function test_omite_el_boton_si_falta_el_numero_o_el_formato_es_invalido(): void
    {
        $t = $this->tramite('PAGADO');
        foreach ([null, '', 'https://otro.example', '0000', 'abc51999999999', str_repeat('9', 20)] as $numero) {
            config(['portal.whatsapp_contacto' => $numero]);
            $this->withSession($this->sesion($t))->get(route('portal.estado'))->assertOk()->assertDontSee('https://wa.me/', false);
        }
        Http::assertNothingSent();
    }
}

