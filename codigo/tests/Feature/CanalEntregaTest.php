<?php
namespace Tests\Feature;

use App\Mail\DocumentoFirmadoMail;
use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class CanalEntregaTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferencias_visibles_al_cargar_firma_y_envios_solo_al_publicar(): void
    {
        $t = $this->tramite();
        $t->update(['datos' => ['canales_entrega' => ['correo' => true, 'whatsapp' => true, 'numero' => '51912345678']]]);
        $antes = view('portal.canales-entrega', ['tramite' => $t, 'entrega' => []])->render();
        $this->assertStringContainsString('verificado@example.test', $antes);
        $this->assertStringContainsString('+51912345678', $antes);
        $this->assertStringContainsString('Enviar por correo al publicar', $antes);
        $this->assertStringNotContainsString('https://wa.me/', $antes);
        $this->assertStringNotContainsString('action="'.route('entrega.correo', $t).'"', $antes);
        $t->update(['estado' => 'LISTA']);
        $despues = view('portal.canales-entrega', ['tramite' => $t, 'entrega' => []])->render();
        $this->assertStringContainsString('Enviar PDF por correo', $despues);
        $this->assertStringContainsString('https://wa.me/51912345678?text=', $despues);
        $this->assertStringContainsString('ya está lista. Le adjuntamos el documento firmado', $despues);
        $this->assertStringContainsString('Copiar mensaje', $despues);
        $this->assertStringContainsString('Descargar PDF firmado para adjuntar', $despues);
        Mail::assertNothingSent();
    }

    private function tramite(): TramitePublico
    {
        Mail::fake(); Storage::fake('local');
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'estado' => 'APROBADA', 'email' => 'verificado@example.test', 'email_verificado_at' => now(),
            'datos' => [], 'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $t;
    }

    public function test_solo_la_sesion_verificada_guarda_los_canales_sin_enviar_documentos(): void
    {
        $t = $this->tramite();
        $this->post(route('portal.canales'), ['correo' => 1])->assertForbidden();
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
        $this->post(route('portal.canales'), ['whatsapp' => 1, 'numero' => 'incorrecto'])->assertSessionHasErrors('numero');
        $this->post(route('portal.canales'), ['correo' => 1, 'whatsapp' => 1, 'numero' => '51912345678', 'email' => 'otro@example.test'])->assertRedirect(route('portal.estado'));
        $this->assertTrue($t->fresh()->datos['canales_entrega']['correo']);
        $this->assertSame('51912345678', $t->fresh()->datos['canales_entrega']['numero']);
        $this->assertSame('verificado@example.test', $t->fresh()->email);
        Mail::assertNothingSent();
    }

    public function test_envia_solo_el_firmado_publicado_al_correo_verificado_y_evita_doble_envio(): void
    {
        $t = $this->tramite();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($staff);
        $this->post(route('entrega.correo', $t))->assertForbidden();
        Storage::disk('local')->put('private/firmado.pdf', '%PDF-firmado');
        $t->update(['estado' => 'LISTA', 'datos' => ['canales_entrega' => ['correo' => true], 'entrega' => [
            'firmado_ruta' => 'private/firmado.pdf', 'firmado_sha256' => hash('sha256', '%PDF-firmado'), 'revision' => ['usuario_id' => $staff->id]]]]);
        $this->post(route('entrega.correo', $t), ['email' => 'otro@example.test'])->assertSessionHas('exito');
        $this->post(route('entrega.correo', $t))->assertSessionHas('exito');
        Mail::assertSent(DocumentoFirmadoMail::class, fn ($mail) => $mail->hasTo('verificado@example.test') && $mail->ruta === 'private/firmado.pdf');
        Mail::assertSentCount(1);
        $this->assertNotEmpty($t->fresh()->datos['entrega']['correo_enviado_at']);
    }

    public function test_publicar_envia_el_correo_automaticamente_si_el_ciudadano_lo_pidio(): void
    {
        $t = $this->tramite();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        Storage::disk('local')->put('private/firmado.pdf', '%PDF-firmado');
        $t->update(['estado' => 'APROBADA', 'datos' => ['canales_entrega' => ['correo' => true], 'entrega' => [
            'firmado_ruta' => 'private/firmado.pdf', 'firmado_sha256' => hash('sha256', '%PDF-firmado'),
            'integridad' => 'VERIFICADA', 'firmante' => 'FUNCIONARIO DE PRUEBA']]]);

        $this->actingAs($staff)->get(route('atencion.show', $t))->assertOk()
            ->assertSee('Publicar y entregar')->assertSee('FUNCIONARIO DE PRUEBA')->assertSee('se le enviará el PDF por correo');
        $this->post(route('entrega.publicar', $t), ['version' => $t->fresh()->version, 'revision_firma' => 1,
            'sustento_firma' => 'Se revisó el contenido y la firma del funcionario.'])->assertSessionHas('exito');

        $this->assertSame('LISTA', $t->fresh()->estado);
        Mail::assertSent(DocumentoFirmadoMail::class, fn ($mail) => $mail->hasTo('verificado@example.test'));
        Mail::assertSentCount(1);
        $this->assertNotEmpty($t->fresh()->datos['entrega']['correo_enviado_at']);
    }
}
