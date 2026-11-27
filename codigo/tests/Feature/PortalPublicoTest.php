<?php

namespace Tests\Feature;

use App\Mail\CodigoTramite;
use App\Models\Acta;
use App\Models\ActaNacimiento;
use App\Models\PagoTramite;
use App\Models\TramitePublico;
use App\Services\CodigoTramiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PortalPublicoTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); Storage::fake('local'); Mail::fake(); config(['portal.apisperu_token' => null]); \Illuminate\Support\Facades\Http::preventStrayRequests(); }
    private function tramite(): TramitePublico
    {
        $acta = Acta::factory()->create();
        return TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $acta->id, 'datos' => ['nombre' => 'Persona Ficticia', 'dni' => '12345678', 'nacimiento' => '2000-01-01', 'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Persona Ficticia']], 'email' => 'prueba@example.test', 'terminos_version' => 'demo', 'aceptado_at' => now()]);
    }
    public function test_busqueda_no_cobra_ni_expone_el_archivo_y_conserva_aceptacion(): void
    {
        $this->get('/tramite')->assertOk();
        $this->post('/tramite/buscar', ['tipo' => 'NACIMIENTO', 'titular' => 'Persona Ficticia', 'fecha' => '2000-01-01'])->assertSessionHasErrors('terminos');
        $acta = Acta::factory()->create();
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Ficticia Persona', 'dni' => '12345678', 'fecha_nacimiento' => '2000-01-01']);
        Storage::disk('local')->put($acta->pdf_ruta, '%PDF-1.4 prueba');
        $this->post('/tramite/buscar', ['terminos' => 1, 'tipo' => 'NACIMIENTO', 'titular' => 'Persona Ficticia', 'fecha' => '2000-01-01'])->assertRedirect('/tramite/datos');
        $this->get('/tramite/datos')->assertOk()->assertDontSee($acta->pdf_ruta);
        $this->post('/tramite/datos', ['dni' => '12345678', 'nombre' => 'Persona Ficticia', 'nacimiento' => '2000-01-01', 'verificador' => '1', 'email' => 'prueba@example.test', 'declaracion' => 1, 'relacion' => 'titular', 'motivo' => 'Copia de mi acta para trámite personal.'])->assertSessionHasNoErrors()->assertRedirect('/tramite/codigo');
        $this->assertDatabaseCount('users', 1); // Only the acta factory's internal author.
        $this->assertDatabaseCount('pagos_tramite', 0);
        $this->assertDatabaseHas('tramites_publicos', ['terminos_version' => config('portal.terminos_version')]);
        $registrado = TramitePublico::firstOrFail();
        $this->assertSame('NO_APLICA', $registrado->datos['cruce_parentesco']['resultado']);
        $this->assertStringContainsString('soy el titular', $registrado->datos['declaracion_detalle']['texto']);
        $this->assertSame(\App\Services\DeclaracionSolicitud::VERSION, $registrado->datos['declaracion_detalle']['version']);
        Mail::assertSent(CodigoTramite::class);
        $this->get('/tramite/estado')->assertRedirect(route('portal.seguimiento'))->assertSessionHas('aviso');
    }
    public function test_codigo_un_solo_uso_caducidad_e_intentos(): void
    {
        $tramite = $this->tramite();
        $servicio = app(CodigoTramiteService::class);
        $servicio->enviar($tramite);
        $codigo = Mail::sent(CodigoTramite::class)->first()->codigo;
        for ($i = 0; $i < 5; $i++) $this->assertFalse($servicio->verificar($tramite, '000000'));
        $this->assertFalse($servicio->verificar($tramite, $codigo));
        $this->travel(2)->minutes();
        $servicio->enviar($tramite);
        $codigo = Mail::sent(CodigoTramite::class)->last()->codigo;
        $this->assertTrue($servicio->verificar($tramite, $codigo));
        $this->assertFalse($servicio->verificar($tramite, $codigo));
        $this->travel(2)->minutes();
        $servicio->enviar($tramite);
        $codigo = Mail::sent(CodigoTramite::class)->last()->codigo;
        $this->travel(11)->minutes();
        $this->assertFalse($servicio->verificar($tramite, $codigo));
    }

    public function test_padre_de_menor_puede_pagar_pero_no_aprobarse_sin_revision_del_vinculo(): void
    {
        $tramite = $this->tramite();
        ActaNacimiento::create(['acta_id' => $tramite->acta_id, 'apellido_nombre' => 'Menor Ficticio', 'fecha_nacimiento' => now()->subYears(8)->toDateString(), 'dni_no_consignado' => true]);
        $tramite->update(['datos' => array_merge($tramite->datos, ['relacion' => 'padre', 'motivo' => 'Acta para matrícula de mi hijo.']), 'identidad_estado' => 'DATOS_CONTRASTADOS', 'email_verificado_at' => now()]);
        $this->assertTrue($tramite->requiereRevisionVinculo());
        $checkout = 'https://sandbox.mercadopago.com.pe/checkout/v1/redirect?pref_id=pref-padre';
        $this->mock(\App\Services\MercadoPagoService::class, function ($mock) use ($checkout) {
            $mock->shouldReceive('disponible')->once()->andReturn(true);
            $mock->shouldReceive('crear')->once()->andReturn(['mercadopago_preferencia_id' => 'pref-padre', 'mercadopago_checkout_url' => $checkout, 'estado' => 'PENDIENTE']);
            $mock->shouldReceive('checkoutValido')->once()->andReturn(true);
        });
        $this->withSession(['portal_acceso' => ['codigo' => $tramite->codigo, 'expira' => now()->addMinutes(30)->timestamp]])->post(route('mercadopago.pagar'))->assertSessionHasNoErrors()->assertRedirect($checkout);
        $this->assertSame('PENDIENTE_PAGO', $tramite->fresh()->estado);
        $tramite->pago->update(['estado' => 'PAGADO']);
        $tramite->update(['estado' => 'EN_REVISION']);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $asistente = \App\Models\User::factory()->create();
        $asistente->roles()->attach(\App\Models\Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($asistente)->get('/atencion-ciudadana/'.$tramite->id)->assertOk()->assertSee('Soy el padre')->assertSee('Sustento de la revisión');
        $decision = ['estado' => 'APROBADA', 'version' => $tramite->fresh()->version, 'mensaje' => 'Revisión final.'];
        $this->patch('/atencion-ciudadana/'.$tramite->id, $decision)->assertSessionHasErrors(['vinculo_revisado', 'sustento_revision']);
        $this->assertSame('EN_REVISION', $tramite->fresh()->estado);
        $this->patch('/atencion-ciudadana/'.$tramite->id, $decision + ['vinculo_revisado' => 1, 'sustento_revision' => 'Revisados los nombres del padre en el acta original.'])->assertSessionHasNoErrors();
        $this->assertSame('APROBADA', $tramite->fresh()->estado);
        $this->assertSame($asistente->id, $tramite->fresh()->datos['revision_vinculo']['usuario_id']);
    }

    public function test_indicar_titular_no_evade_revision_si_el_acta_es_de_un_menor(): void
    {
        $tramite = $this->tramite();
        ActaNacimiento::create(['acta_id' => $tramite->acta_id, 'apellido_nombre' => 'Menor Ficticio', 'fecha_nacimiento' => now()->subYears(8)->toDateString(), 'dni_no_consignado' => true]);
        $tramite->update(['datos' => array_merge($tramite->datos, ['relacion' => 'titular'])]);
        $this->assertTrue($tramite->requiereRevisionVinculo());
        $tramite->acta->nacimiento->update(['fecha_nacimiento' => now()->subYears(25)->toDateString()]);
        $this->assertFalse($tramite->fresh()->requiereRevisionVinculo());
    }
}
