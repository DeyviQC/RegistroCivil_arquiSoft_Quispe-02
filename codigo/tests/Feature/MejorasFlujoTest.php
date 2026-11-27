<?php

namespace Tests\Feature;

use App\Mail\EstadoTramiteMail;
use App\Models\{Acta, ActaNacimiento, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, URL};
use Illuminate\Support\Str;
use Tests\TestCase;

class MejorasFlujoTest extends TestCase
{
    use RefreshDatabase;

    private function tramite(string $estado, bool $pagado = true, array $datos = []): TramitePublico
    {
        $acta = Acta::factory()->create();
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Titular Adulto', 'fecha_nacimiento' => '1990-01-01']);
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $acta->id, 'estado' => $estado,
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(), 'identidad_estado' => 'DATOS_CONTRASTADOS',
            'datos' => $datos + ['relacion' => 'titular', 'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular Adulto']],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        if ($pagado) PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO', 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $t;
    }

    private function asistente(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $u = User::factory()->create();
        $u->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        return $u;
    }

    private function acceder(TramitePublico $t): void
    {
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
    }

    public function test_las_transiciones_no_permitidas_se_rechazan_en_un_solo_lugar(): void
    {
        $t = $this->tramite('PENDIENTE_PAGO', false);
        $this->expectException(\DomainException::class);
        $t->transicionar('APROBADA', 'No debería pasar.');
    }

    public function test_transicionar_sube_la_version_y_registra_el_evento(): void
    {
        Mail::fake();
        $t = $this->tramite('PENDIENTE');
        $t = $t->fresh();
        $version = $t->version;
        $t->transicionar('OBSERVADA', 'Adjunta tu DNI.', null);
        $this->assertSame($version + 1, $t->fresh()->version);
        $this->assertSame('OBSERVADA', $t->eventos()->latest('id')->first()->estado);
        Mail::assertQueued(EstadoTramiteMail::class);
    }

    public function test_el_personal_aprueba_directo_sin_iniciar_revision_y_ve_plantillas(): void
    {
        Mail::fake();
        $t = $this->tramite('PENDIENTE');
        $this->actingAs($this->asistente())->get(route('atencion.show', $t))->assertOk()
            ->assertSee('Aprobar solicitud')->assertDontSee('Iniciar revisión')
            ->assertSee('Falta DNI')->assertSee('data-plantilla', false);
        $this->patch(route('atencion.atender', $t), ['estado' => 'APROBADA', 'version' => $t->fresh()->version])->assertSessionHasNoErrors();
        $this->assertSame('APROBADA', $t->fresh()->estado);
    }

    public function test_una_decision_con_version_desactualizada_se_rechaza_sin_cambiar_nada(): void
    {
        Mail::fake();
        $t = $this->tramite('PENDIENTE')->fresh();
        $this->actingAs($this->asistente());
        $vieja = $t->version;
        // Another staff member decides first: the version moves on.
        $this->patch(route('atencion.atender', $t), ['estado' => 'OBSERVADA', 'mensaje' => 'Adjunta tu DNI.', 'version' => $vieja])->assertSessionHasNoErrors();
        // A second decision prepared with the old version must not overwrite it.
        $this->patch(route('atencion.atender', $t), ['estado' => 'APROBADA', 'version' => $vieja])->assertSessionHasErrors('version');
        $this->assertSame('OBSERVADA', $t->fresh()->estado);
        $this->assertSame($vieja + 1, $t->fresh()->version);
        $this->assertSame(1, $t->eventos()->count());
    }

    public function test_sin_pagar_el_pago_aparece_primero(): void
    {
        $t = $this->tramite('PENDIENTE_PAGO', false);
        $this->acceder($t);
        $html = $this->get(route('portal.estado'))->assertOk()->assertSee('Paga tu trámite para que lo revisemos')->getContent();
        $this->assertLessThan(strpos($html, 'TU DOCUMENTO'), strpos($html, 'Pagar con Yape'));
    }

    public function test_tras_pagar_se_pide_elegir_la_entrega_arriba_una_sola_vez(): void
    {
        config(['portal.whatsapp_contacto' => '51900000000']);
        $t = $this->tramite('PENDIENTE');
        $this->acceder($t);
        $html = $this->get(route('portal.estado'))->assertOk()->assertSee('PAGO CONFIRMADO · ÚLTIMO PASO')->getContent();
        $this->assertSame(1, substr_count($html, '¿Cómo deseas recibir tu documento?'));
        $this->assertLessThan(strpos($html, 'TU DOCUMENTO'), strpos($html, '¿Cómo deseas recibir tu documento?'));

        $t->update(['datos' => array_merge($t->datos, ['canales_entrega' => ['correo' => true]])]);
        $this->get(route('portal.estado'))->assertOk()->assertDontSee('PAGO CONFIRMADO · ÚLTIMO PASO');
    }

    public function test_enlace_directo_del_correo_abre_la_solicitud_y_caduca(): void
    {
        $t = $this->tramite('OBSERVADA');
        $enlace = EstadoTramiteMail::enlaceDirecto($t);
        $this->get($enlace)->assertRedirect(route('portal.estado'));
        $this->get(route('portal.estado'))->assertOk()->assertSee('Registro Civil necesita que completes algo');

        $this->flushSession();
        $this->get($enlace.'x')->assertRedirect(route('portal.seguimiento'))->assertSessionHas('aviso');
        $this->travel(EstadoTramiteMail::DIAS_ENLACE + 1)->days();
        $this->get($enlace)->assertRedirect(route('portal.seguimiento'));
        $this->get(route('portal.estado'))->assertRedirect(route('portal.seguimiento'));
    }

    public function test_el_correo_de_aviso_incluye_el_enlace_directo(): void
    {
        $t = $this->tramite('OBSERVADA');
        $html = (new EstadoTramiteMail($t->id, 'OBSERVADA', 'Adjunta tu DNI.'))->render();
        $this->assertStringContainsString('Ver mi solicitud', $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringContainsString(e(URL::to('/tramite/acceso/'.$t->codigo)), $html);
    }

    public function test_acceso_vencido_redirige_al_seguimiento_y_post_sigue_prohibido(): void
    {
        $this->get(route('yape.formulario'))->assertRedirect(route('portal.seguimiento'));
        $this->post(route('portal.responder'), ['respuesta' => 'Texto de prueba'])->assertForbidden();
    }
}
