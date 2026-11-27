<?php

namespace Tests\Feature;

use App\Mail\EstadoTramiteMail;
use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Hash, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class AvisosMantenimientoRespaldoTest extends TestCase
{
    use RefreshDatabase;

    private function tramite(array $extra = []): TramitePublico
    {
        return TramitePublico::create($extra + ['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'datos' => ['busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Persona Ficticia']], 'email' => 'prueba@example.test',
            'email_verificado_at' => now(), 'terminos_version' => 'demo', 'aceptado_at' => now()]);
    }

    private function pagado(TramitePublico $t): void
    {
        PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO', 'idempotencia' => (string) Str::uuid(),
            'importe_centimos' => 1030, 'estado' => 'PAGADO', 'pagado_at' => now()]);
    }

    public function test_el_ciudadano_recibe_un_aviso_en_cola_cuando_el_personal_observa_su_solicitud(): void
    {
        Mail::fake();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $asistente = User::factory()->create();
        $asistente->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $t = $this->tramite(['estado' => 'EN_REVISION']);
        $this->pagado($t);

        $this->actingAs($asistente)->patch('/atencion-ciudadana/'.$t->id, ['estado' => 'OBSERVADA',
            'mensaje' => 'Falta acreditar el vínculo con el titular.', 'version' => $t->fresh()->version])->assertSessionHasNoErrors();

        Mail::assertQueued(EstadoTramiteMail::class, fn ($m) => $m->hasTo('prueba@example.test')
            && $m->estado === 'OBSERVADA' && $m->mensaje === 'Falta acreditar el vínculo con el titular.');
        Mail::assertNothingSent();
    }

    public function test_aprobar_no_exige_mensaje_pero_observar_o_rechazar_si(): void
    {
        Mail::fake();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $asistente = User::factory()->create();
        $asistente->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $acta = Acta::factory()->create();
        \App\Models\ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Titular Adulto', 'fecha_nacimiento' => '1990-01-01']);
        $t = $this->tramite(['estado' => 'EN_REVISION', 'acta_id' => $acta->id,
            'datos' => ['busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular Adulto'], 'relacion' => 'titular']]);
        $this->pagado($t);
        $this->actingAs($asistente)->get('/atencion-ciudadana/'.$t->id)->assertOk()
            ->assertSee('Aprobar solicitud')->assertSee('Observar')->assertSee('Rechazar');

        $this->patch('/atencion-ciudadana/'.$t->id, ['estado' => 'OBSERVADA', 'version' => $t->fresh()->version])
            ->assertSessionHasErrors('mensaje');
        $this->patch('/atencion-ciudadana/'.$t->id, ['estado' => 'APROBADA', 'version' => $t->fresh()->version])
            ->assertSessionHasNoErrors();
        $this->assertSame('APROBADA', $t->fresh()->estado);
        $this->assertSame('Tu solicitud fue aprobada. Estamos preparando tu acta firmada.', $t->eventos()->latest('id')->first()->mensaje);
    }

    public function test_el_aviso_no_expone_datos_del_acta_y_enlaza_al_seguimiento(): void
    {
        $html = (new EstadoTramiteMail(42, 'LISTA', 'Documento publicado.'))->render();
        $this->assertStringContainsString('N.º 000042', $html);
        $this->assertStringContainsString(route('portal.seguimiento'), $html);
        $this->assertStringNotContainsString('Persona Ficticia', $html);
    }

    public function test_no_se_avisa_en_revision_ni_a_correos_sin_verificar(): void
    {
        Mail::fake();
        $t = $this->tramite();
        $t->eventos()->create(['estado' => 'EN_REVISION', 'mensaje' => 'Revisión iniciada.']);
        $sinVerificar = $this->tramite(['email_verificado_at' => null]);
        $sinVerificar->eventos()->create(['estado' => 'APROBADA', 'mensaje' => 'Aprobada.']);
        Mail::assertNothingQueued();
    }

    public function test_mantenimiento_vence_solo_solicitudes_sin_ninguna_orden_de_pago(): void
    {
        Mail::fake();
        $antigua = $this->tramite(['otp_hash' => Hash::make('123456'), 'otp_expira_at' => now()->subDay()]);
        $conOrden = $this->tramite();
        PagoTramite::create(['tramite_publico_id' => $conOrden->id, 'proveedor' => 'MERCADOPAGO',
            'idempotencia' => (string) Str::uuid(), 'importe_centimos' => 1030]);
        $reciente = $this->tramite();
        TramitePublico::whereIn('id', [$antigua->id, $conOrden->id])->update(['created_at' => now()->subDays(10)]);

        $this->artisan('tramites:mantenimiento')->assertSuccessful();

        $this->assertSame('VENCIDA', $antigua->fresh()->estado);
        $this->assertNull($antigua->fresh()->otp_hash);
        $this->assertSame('VENCIDA', $antigua->eventos()->first()->estado);
        // A gateway order exists: a late webhook could still confirm it.
        $this->assertSame('PENDIENTE_PAGO', $conOrden->fresh()->estado);
        $this->assertSame('PENDIENTE_PAGO', $reciente->fresh()->estado);
        Mail::assertNothingQueued();
    }

    public function test_respaldo_copia_los_documentos_privados_de_forma_incremental(): void
    {
        $destino = storage_path('framework/testing/respaldo-'.Str::random(8));
        $archivo = storage_path('app/private/actas-prueba-respaldo/acta.pdf');
        File::ensureDirectoryExists(dirname($archivo));
        File::put($archivo, '%PDF-1.4 respaldo');
        config(['respaldo.ruta' => $destino, 'respaldo.frase' => 'frase-de-prueba-larga']);

        try {
            $this->artisan('respaldo:crear')->expectsOutputToContain('copiados')->assertSuccessful();
            $this->assertFileEquals($archivo, $destino.'/archivos/actas-prueba-respaldo/acta.pdf');
            $this->assertFileExists($destino.'/ultimo-respaldo.txt');
            // The APP_KEY copy is encrypted and only the passphrase opens it.
            $enc = File::get($destino.'/app-key.enc');
            $this->assertStringNotContainsString(config('app.key'), $enc);
            $this->assertSame(config('app.key'), \App\Console\Commands\CrearRespaldo::descifrarClave($enc, 'frase-de-prueba-larga'));
            $this->assertNull(\App\Console\Commands\CrearRespaldo::descifrarClave($enc, 'otra-frase-cualquiera'));
            $this->artisan('respaldo:clave', ['archivo' => $destino.'/app-key.enc'])
                ->expectsQuestion('Frase del respaldo (RESPALDO_FRASE)', 'frase-de-prueba-larga')
                ->expectsOutputToContain('APP_KEY=')->assertSuccessful();
            config(['respaldo.frase' => null]);
            $this->artisan('respaldo:crear', ['--solo-archivos' => true])->expectsOutputToContain('RESPALDO_FRASE')->assertFailed();
            config(['respaldo.frase' => 'frase-de-prueba-larga']);
            // Second run copies nothing new.
            $this->artisan('respaldo:crear', ['--solo-archivos' => true])->expectsOutputToContain('Archivos: 0 copiados')->assertSuccessful();
        } finally {
            File::deleteDirectory(dirname($archivo));
            File::deleteDirectory($destino);
        }
    }

    public function test_respaldo_rechaza_un_destino_dentro_de_los_documentos_privados(): void
    {
        config(['respaldo.ruta' => storage_path('app/private/respaldos')]);
        $this->artisan('respaldo:crear')->assertFailed();
    }
}
