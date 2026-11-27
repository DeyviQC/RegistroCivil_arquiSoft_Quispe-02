<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class RespuestaObservacionTest extends TestCase
{
    use RefreshDatabase;

    private function observada(): TramitePublico
    {
        Mail::fake(); Storage::fake('local');
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'estado' => 'OBSERVADA', 'email' => 'ciudadano@example.test', 'email_verificado_at' => now(),
            'datos' => ['busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular Ficticio', 'fecha' => '2020-01-15'], 'relacion' => 'madre', 'nombre' => 'Madre Ficticia'],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        $t->eventos()->create(['estado' => 'OBSERVADA', 'mensaje' => 'Adjunta una copia de tu DNI.']);
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(20)->timestamp]]);
        return $t;
    }

    public function test_el_ciudadano_ve_la_observacion_y_responde_con_un_documento(): void
    {
        $t = $this->observada();
        $this->get(route('portal.estado'))->assertOk()
            ->assertSee('Adjunta una copia de tu DNI.')->assertSee('Enviar respuesta')->assertSee('N.º '.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT));

        $this->post(route('portal.responder'), ['respuesta' => 'corto'])->assertSessionHasErrors('respuesta');
        $this->post(route('portal.responder'), ['respuesta' => 'Adjunto la copia de mi DNI.',
            'adjunto' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('adjunto');

        $this->post(route('portal.responder'), ['respuesta' => 'Adjunto la copia de mi DNI.',
            'adjunto' => UploadedFile::fake()->create('dni.pdf', 50, 'application/pdf')])
            ->assertRedirect(route('portal.estado'))->assertSessionHas('exito');

        $t->refresh();
        $this->assertSame('PENDIENTE', $t->estado);
        $this->assertSame('Adjunto la copia de mi DNI.', $t->datos['subsanaciones'][0]['texto']);
        Storage::disk('local')->assertExists($t->datos['subsanaciones'][0]['adjunto']);
        // Only the observation notice from the setup: no "payment received" email for the citizen's own answer.
        Mail::assertQueuedCount(1);
        // A second answer is not possible until staff observes again.
        $this->post(route('portal.responder'), ['respuesta' => 'Otra respuesta más.'])->assertStatus(409);
    }

    public function test_el_personal_ve_la_respuesta_y_abre_el_adjunto(): void
    {
        $t = $this->observada();
        $this->post(route('portal.responder'), ['respuesta' => 'Adjunto la copia de mi DNI.',
            'adjunto' => UploadedFile::fake()->create('dni.pdf', 50, 'application/pdf')]);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());

        $this->actingAs($staff)->get(route('atencion.index'))->assertOk()->assertSee('Respondió la observación');
        $this->get(route('atencion.show', $t))->assertOk()
            ->assertSee('Respuestas del ciudadano')->assertSee('dni.pdf')->assertSee('15/01/2020')->assertSee('Aprobar solicitud')->assertDontSee('Iniciar revisión');
        $this->get(route('atencion.subsanacion', [$t, 0]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('atencion.subsanacion', [$t, 5]))->assertNotFound();
    }

    public function test_un_rechazo_muestra_el_motivo_al_ciudadano(): void
    {
        $t = $this->observada();
        $t->update(['estado' => 'RECHAZADA']);
        $t->eventos()->create(['estado' => 'RECHAZADA', 'mensaje' => 'El acta no corresponde al solicitante.']);
        $this->get(route('portal.estado'))->assertOk()->assertSee('Tu solicitud fue rechazada.')
            ->assertSee('El acta no corresponde al solicitante.')->assertDontSee('Enviar respuesta');
    }
}
