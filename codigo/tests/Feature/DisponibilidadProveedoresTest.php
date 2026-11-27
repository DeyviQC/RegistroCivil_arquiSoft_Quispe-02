<?php

namespace Tests\Feature;

use App\Models\{Acta, ActaNacimiento, PagoTramite, TramitePublico};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{DB, Http, Mail, Storage};
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * EQ-02 Disponibilidad (sprint-2/06-atributos-de-calidad.md): every external provider is made to fail
 * (no connection, server error) and the test checks the answer the person gets and what was saved.
 * Goal: in 100 % of the simulated failures nothing is charged, confirmed, approved or delivered.
 */
class DisponibilidadProveedoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['portal.apisperu_token' => 'token-solo-prueba', 'portal.tarifa_centimos' => 1030,
            'mercadopago.mode' => 'test', 'mercadopago.access_token' => 'TEST-ficticio',
            'mercadopago.webhook_secret' => 'secreto-ficticio', 'mercadopago.collector_id' => '777',
            'mercadopago.public_url' => 'https://municipio.example.test']);
    }

    /** Both ways a provider can fail: it cannot be reached, or it answers with a server error. */
    public static function fallos(): array
    {
        return [
            'sin conexión' => [fn () => throw new ConnectionException('Connection refused')],
            'error 500' => [fn () => Http::response(['message' => 'Internal error'], 500)],
            'error 503' => [fn () => Http::response('', 503)],
        ];
    }

    private function registrarSolicitud(): \Illuminate\Testing\TestResponse
    {
        $acta = Acta::factory()->create();
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Ficticia Persona', 'dni' => '12345678', 'fecha_nacimiento' => '1990-01-01']);
        Storage::disk('local')->put($acta->pdf_ruta, '%PDF-1.4 prueba');
        $this->post('/tramite/buscar', ['terminos' => 1, 'tipo' => 'NACIMIENTO', 'titular' => 'Persona Ficticia', 'fecha' => '1990-01-01'])->assertRedirect('/tramite/datos');
        return $this->post('/tramite/datos', ['dni' => '12345678', 'nombre' => 'Persona Ficticia', 'nacimiento' => '1990-01-01', 'verificador' => '1',
            'email' => 'prueba@example.test', 'declaracion' => 1, 'relacion' => 'titular', 'motivo' => 'Copia de mi acta para trámite personal.']);
    }

    private function solicitudPorPagar(): TramitePublico
    {
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(), 'estado' => 'PENDIENTE_PAGO',
            'identidad_estado' => 'DATOS_CONTRASTADOS', 'version' => 1, 'datos' => [],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
        return $t;
    }

    private function pagoPendiente(TramitePublico $t): PagoTramite
    {
        return PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO', 'idempotencia' => (string) Str::uuid(),
            'importe_centimos' => 1030, 'estado' => 'PENDIENTE', 'mercadopago_preferencia_id' => 'pref-1']);
    }

    private function sinCambios(TramitePublico $t, ?PagoTramite $p = null, string $estadoPago = 'PENDIENTE'): void
    {
        $this->assertSame('PENDIENTE_PAGO', $t->fresh()->estado);
        $this->assertSame(0, $t->eventos()->count());
        if ($p) {
            $this->assertSame($estadoPago, $p->fresh()->estado);
            $this->assertNull($p->fresh()->pagado_at);
            $this->assertNull($p->fresh()->mercadopago_pago_id);
        }
    }

    /** @dataProvider fallos */
    public function test_si_falla_la_consulta_dni_no_se_crea_solicitud_ni_cobro(\Closure $fallo): void
    {
        Mail::fake();
        Http::fake(['dniruc.apisperu.com/*' => $fallo]);
        $this->registrarSolicitud()->assertSessionHasErrors(['dni' => 'La consulta DNI no está disponible. Inténtalo más tarde; no se ha generado ningún cobro.']);
        $this->assertSame(0, TramitePublico::count());
        $this->assertSame(0, PagoTramite::count());
        Mail::assertNothingSent();
    }

    public function test_si_falla_el_correo_la_solicitud_queda_sin_verificar_y_se_puede_reenviar(): void
    {
        Http::fake(['dniruc.apisperu.com/*' => Http::response(['success' => true, 'dni' => '12345678', 'nombres' => 'PERSONA',
            'apellidoPaterno' => 'FICTICIA', 'apellidoMaterno' => '', 'codVerifica' => 1])]);
        Mail::shouldReceive('to')->andThrow(new TransportException('Servidor de correo no disponible'));
        $this->registrarSolicitud()->assertRedirect('/tramite/codigo')
            ->assertSessionHas('error', 'No se pudo enviar el código. Puedes solicitar un reenvío en un minuto.');
        $t = TramitePublico::sole();
        $this->assertNull($t->email_verificado_at);
        $this->assertSame(0, PagoTramite::count());
        // Without the e-mail code there is no access to the request, so it cannot be paid.
        $this->post(route('mercadopago.pagar'))->assertForbidden();
    }

    /** @dataProvider fallos */
    public function test_si_mercado_pago_no_responde_al_abrir_el_pago_no_se_cobra_ni_confirma(\Closure $fallo): void
    {
        $t = $this->solicitudPorPagar();
        Http::fake(['api.mercadopago.com/*' => $fallo]);
        $this->post(route('mercadopago.pagar'))->assertRedirect(route('portal.estado'))->assertSessionHas('error');
        $p = PagoTramite::sole();
        $this->assertNull($p->mercadopago_checkout_url);
        // The order stays "being created" (never paid) and the next attempt reuses the same reference.
        $this->sinCambios($t, $p, 'CREANDO');
    }

    /** @dataProvider fallos */
    public function test_si_mercado_pago_no_responde_al_comprobar_el_pago_sigue_pendiente(\Closure $fallo): void
    {
        $t = $this->solicitudPorPagar();
        $p = $this->pagoPendiente($t);
        Http::fake(['api.mercadopago.com/*' => $fallo]);
        $this->from(route('portal.estado'))->post(route('mercadopago.comprobar'))->assertRedirect(route('portal.estado'))
            ->assertSessionHas('error', 'No se pudo consultar a Mercado Pago en este momento. Revisa tu conexión e inténtalo de nuevo; no realices otro pago.');
        $this->sinCambios($t, $p);
    }

    /** @dataProvider fallos */
    public function test_el_retorno_con_status_approved_no_confirma_si_mercado_pago_no_lo_respalda(\Closure $fallo): void
    {
        $t = $this->solicitudPorPagar();
        $p = $this->pagoPendiente($t);
        Http::fake(['api.mercadopago.com/*' => $fallo]);
        $this->get(route('mercadopago.retorno').'?payment_id=123&status=approved&collection_status=approved')
            ->assertRedirect(route('portal.estado'))->assertSessionMissing('pago_confirmado');
        $this->sinCambios($t, $p);
    }

    /** @dataProvider fallos */
    public function test_un_aviso_firmado_con_mercado_pago_caido_responde_error_para_que_se_reintente(\Closure $fallo): void
    {
        $t = $this->solicitudPorPagar();
        $p = $this->pagoPendiente($t);
        Http::fake(['api.mercadopago.com/*' => $fallo]);
        $ts = (string) now()->timestamp;
        $firma = hash_hmac('sha256', 'id:123;request-id:req-1;ts:'.$ts.';', 'secreto-ficticio');
        $r = $this->withHeaders(['x-request-id' => 'req-1', 'x-signature' => 'ts='.$ts.',v1='.$firma])
            ->postJson('/pagos/mercadopago/notificacion?data.id=123', ['type' => 'payment', 'data' => ['id' => '123']]);
        // A non-2xx answer makes Mercado Pago send the notice again later; nothing is lost or confirmed.
        $this->assertGreaterThanOrEqual(500, $r->status());
        $this->sinCambios($t, $p);
    }

    public function test_si_la_base_de_datos_cae_el_monitoreo_lo_informa_sin_exponer_detalles(): void
    {
        $original = config('database.default');
        config(['database.connections.caida' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'no_existe',
            'username' => 'nadie', 'password' => 'x', 'options' => [\PDO::ATTR_TIMEOUT => 2]], 'database.default' => 'caida']);
        DB::purge('caida');
        try {
            $r = $this->getJson('/api/v1/salud');
        } finally {
            config(['database.default' => $original]);
        }
        $r->assertStatus(503)->assertJsonPath('estado', 'degradado')->assertJsonPath('componentes.base_datos', 'error');
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
        $this->assertStringNotContainsString('127.0.0.1', $r->getContent());
    }
}
