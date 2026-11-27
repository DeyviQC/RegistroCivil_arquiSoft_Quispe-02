<?php

namespace Tests\Feature;

use App\Models\Acta;
use App\Models\Role;
use App\Models\User;
use App\Services\IncidenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('slug', 'administrador')->first());
    }

    private function pendiente(): Acta
    {
        $a = new Acta(['tipo' => 'NACIMIENTO', 'numero_acta' => '3003306350', 'anio' => 2017, 'fecha_acta' => '2017-06-23', 'usuario_id' => $this->admin->id, 'estado' => 'BORRADOR']);
        $a->asignarNumeracionHistorica(455, 16, 1); $a->save();
        return $a;
    }

    private function lote(?Acta $a = null, string $ruta = 'private/notas.pdf'): void
    {
        Storage::disk('local')->put('private/cargas/prueba/lote.json', json_encode([
            'estado' => 'importado', 'nombre' => 'Índice histórico', 'usuario_id' => $this->admin->id,
            'archivos' => ['original.pdf' => ['ruta' => $ruta]],
            'filas' => array_map(fn ($n) => ['tipo' => 'NACIMIENTO', 'anio' => $n === 455 ? 2017 : 2021, 'orden_tipo' => $n, 'sobre' => '16', 'archivo' => 'original.pdf'], [455, 1047, 1244, 387]),
            'incidencias' => [
                ['orden' => 455, 'acta_id' => $a?->id, 'nombre' => 'Persona pendiente', 'detalle' => 'Solo se recibió una nota de inexistencia física.', 'pagina' => 2],
                ['orden' => 1047, 'detalle' => 'El índice marca INUTILIZADO.', 'pagina' => 3],
                ['orden' => 1244, 'detalle' => 'Referencia trasladada al sobre 40.', 'pagina' => 4],
                ['orden' => 387, 'detalle' => 'El índice repite el CUI con otro nombre; confirmar el documento.', 'pagina' => 5],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    public function test_reune_historicos_y_fichas_sin_pdf_sin_duplicar_y_filtra_por_situacion(): void
    {
        $a = $this->pendiente(); $this->lote($a);
        $all = app(IncidenciaService::class)->todas();
        $this->assertCount(4, $all);
        $this->assertEqualsCanonicalizing(['PENDIENTE', 'REVISION', 'INUTILIZADA', 'ACLARADA'], $all->pluck('estado')->all());
        $this->actingAs($this->admin)->get('/incidencias')->assertOk()->assertSee('Incidencias de actas')->assertSee('1 acta pendiente de PDF')
            ->assertSee('Inutilizadas / en blanco')->assertSee('2 incidencias encontradas')
            ->assertDontSee('Requieren revisión')->assertDontSee('Aclaradas')->assertDontSee('Resueltas')
            ->assertDontSee('confirmar el documento.')->assertDontSee('referencias requieren');
        foreach (['REVISION', 'ACLARADA', 'RESUELTA'] as $state) {
            $this->get('/incidencias?situacion='.$state)->assertRedirect(route('incidencias.index'));
        }
        $this->get('/incidencias?situacion=PENDIENTE&anio=2017&tipo=NACIMIENTO')->assertOk()->assertSee('Persona pendiente')->assertDontSee('Referencia trasladada al sobre 40.');
        $this->get('/incidencias?situacion=INVENTADA')->assertSessionHasErrors('situacion');
        $this->get('/dashboard')->assertOk()->assertSee('incidencias-notification')->assertSee('1 acta pendiente de PDF')->assertDontSee('Revisar incidencias')->assertDontSee('incidencia-aviso');
        $this->get('/actas')->assertOk()->assertSee('Situación')->assertSee('Pendiente');
        $this->get('/actas/'.$a->id)->assertOk()->assertSee('Ver incidencia');
    }

    public function test_adjuntar_original_resuelve_el_aviso_y_conserva_el_antecedente(): void
    {
        $a = $this->pendiente(); $this->lote($a);
        $incident = app(IncidenciaService::class)->todas()->firstWhere('estado', 'PENDIENTE');
        $this->actingAs($this->admin)->get('/incidencias?detalle='.$incident['id'])->assertOk()->assertSee('Adjuntar acta original')->assertSee('Ver documento recibido');
        $this->post('/actas/'.$a->id.'/completar-pdf', ['pdf' => UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.4\nDocumento original de prueba\n%%EOF")])->assertRedirect('/actas/'.$a->id);
        $this->assertSame('REGISTRADA', $a->fresh()->estado);
        $updated = app(IncidenciaService::class)->todas()->firstWhere('id', $incident['id']);
        $this->assertSame('RESUELTA', $updated['estado']);
        $this->assertNotNull($updated['ruta']);
        $this->get('/incidencias?detalle='.$incident['id'])->assertNotFound();
        $this->get('/incidencias')->assertOk()->assertDontSee('Persona pendiente')->assertDontSee('Documento original adjuntado')->assertDontSee('Adjuntar acta original')->assertDontSee('incidencias-notification');
        $this->post('/actas/'.$a->id.'/completar-pdf', [])->assertForbidden();
    }

    public function test_documentos_privados_y_detalles_requieren_permiso_y_no_admiten_rutas_externas(): void
    {
        $this->lote();
        $id = app(IncidenciaService::class)->todas()->first()['id'];
        $this->get('/incidencias')->assertRedirect('/login');
        $this->get('/incidencias/'.$id.'/documento')->assertRedirect('/login');
        $sinRol = User::factory()->create();
        $this->actingAs($sinRol)->get('/incidencias')->assertForbidden();
        $assistant = User::factory()->create(); $assistant->roles()->attach(Role::where('slug', 'asistente')->first());
        Storage::disk('local')->put('private/notas.pdf', '%PDF-1.4 nota');
        $this->actingAs($assistant)->get('/incidencias')->assertOk();
        $this->get('/incidencias/'.$id.'/documento')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/incidencias?detalle='.str_repeat('f', 64))->assertNotFound();
        Storage::disk('local')->put('secreto.pdf', 'privado'); $this->lote(null, 'secreto.pdf');
        $this->get('/incidencias/'.$id.'/documento')->assertNotFound();
    }
}
