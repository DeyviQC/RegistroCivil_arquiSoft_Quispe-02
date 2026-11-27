<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class EndurecimientoTest extends TestCase
{
    use RefreshDatabase;

    private function tramite(string $dni, string $email = 'ciudadano@example.test'): TramitePublico
    {
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'estado' => 'PENDIENTE', 'email' => $email, 'email_verificado_at' => now(),
            'datos' => ['dni' => $dni, 'nombre' => 'Persona '.$dni, 'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular']],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $t;
    }

    public function test_el_dni_se_guarda_como_indice_ciego_y_no_en_claro(): void
    {
        $t = $this->tramite('12345678');
        $fila = DB::table('tramites_publicos')->find($t->id);
        $this->assertSame(TramitePublico::hashDni('12345678'), $fila->dni_hash);
        $this->assertStringNotContainsString('12345678', $fila->dni_hash.$fila->datos);
        $t->update(['datos' => array_merge($t->datos, ['dni' => '87654321'])]);
        $this->assertSame(TramitePublico::hashDni('87654321'), $t->fresh()->dni_hash);
    }

    public function test_seguimiento_encuentra_por_correo_y_dni_del_solicitante(): void
    {
        Mail::fake();
        $propia = $this->tramite('12345678');
        $this->tramite('99999999'); // same mailbox, other applicant
        $this->post(route('portal.recuperar'), ['dni' => '12345678', 'email' => 'CIUDADANO@example.test'])->assertRedirect(route('portal.codigo'));
        $this->assertSame([$propia->id], session('portal_consulta_pendiente'));
    }

    public function test_la_bandeja_filtra_por_dni(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->tramite('12345678');
        $this->tramite('99999999');
        $this->actingAs($staff)->get(route('atencion.index', ['dni' => '12345678']))->assertOk()
            ->assertSee('Persona 12345678')->assertDontSee('Persona 99999999');
        $this->get(route('atencion.index', ['dni' => '123']))->assertSessionHasErrors('dni');
    }

    public function test_solo_el_personal_puede_entrar_al_panel(): void
    {
        $sinRol = User::factory()->create(['username' => 'sinrol', 'password' => Hash::make('Clave-Segura-123')]);
        $this->post(route('login.store'), ['username' => 'sinrol', 'password' => 'Clave-Segura-123'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->get('/ciudadano/solicitudes')->assertRedirect('/tramite/seguimiento');
    }

    public function test_las_pasarelas_retiradas_ya_no_reciben_notificaciones(): void
    {
        $this->post('/pagos/izipay/notificacion')->assertNotFound();
        $this->post('/pagos/taypi/notificacion')->assertNotFound();
    }

    public function test_las_respuestas_llevan_cabeceras_de_seguridad_y_no_revelan_php(): void
    {
        $r = $this->get(route('portal.inicio'))->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('X-Powered-By')
            ->assertHeaderMissing('Strict-Transport-Security'); // only over HTTPS
        $csp = $r->headers->get('Content-Security-Policy');
        foreach (["frame-ancestors 'self'", "object-src 'none'", "base-uri 'self'", "default-src 'self'"] as $directiva) {
            $this->assertStringContainsString($directiva, $csp);
        }
        $this->get('https://localhost/tramite')->assertHeader('Strict-Transport-Security');
    }

    public function test_verificacion_del_sistema_detecta_configuracion_insegura(): void
    {
        config(['respaldo.frase' => null, 'queue.default' => 'sync']);
        $this->artisan('sistema:verificar')->expectsOutputToContain('RESPALDO_FRASE')->assertFailed();
    }
}
