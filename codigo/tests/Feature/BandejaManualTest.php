<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BandejaManualTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($staff);
    }

    private function tramite(string $estado = 'APROBADA', bool $pagado = true): TramitePublico
    {
        $a = Acta::factory()->create();
        Storage::disk('local')->put($a->pdf_ruta, "%PDF-1.4\nORIGINAL PRUEBA\n%%EOF");
        $t = TramitePublico::create(['acta_id' => $a->id, 'codigo' => (string) Str::uuid(), 'estado' => $estado,
            'email' => 'persona@example.test', 'datos' => ['nombre' => 'Persona '.$estado, 'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular ficticio']],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => $pagado ? 'PAGADO' : 'CREANDO', 'idempotencia' => (string) Str::uuid()]);
        return $t;
    }

    public function test_bandeja_solo_muestra_pagados_sin_exponer_uuid_y_filtra_estado_y_numero(): void
    {
        $a = $this->tramite();
        $b = $this->tramite('PENDIENTE');
        $noPagado = $this->tramite('OBSERVADA', false);
        $this->get(route('atencion.index'))->assertOk()->assertSee('N.º '.str_pad((string)$a->id, 6, '0', STR_PAD_LEFT))
            ->assertSee('Persona PENDIENTE')->assertDontSee('Persona OBSERVADA')->assertDontSee($a->codigo)->assertDontSee($b->codigo);
        $this->get(route('atencion.index', ['estado' => 'APROBADA']))->assertOk()->assertSee('Persona APROBADA')->assertDontSee('Persona PENDIENTE');
        $this->get(route('atencion.index', ['solicitud' => $b->id]))->assertOk()->assertSee('Persona PENDIENTE')->assertDontSee('Persona APROBADA');
        $this->get(route('atencion.index', ['solicitud' => $noPagado->id]))->assertOk()->assertSee('No hay solicitudes para mostrar');
        $this->get(route('atencion.index', ['estado' => 'INVENTADO']))->assertSessionHasErrors('estado');
    }

    public function test_descarga_manual_prepara_copia_identica_sin_logo_y_actualiza_version(): void
    {
        $t = $this->tramite();
        // Replace a previous prepared rectangle only after an explicit new download.
        $t->update(['datos' => array_merge($t->datos, ['entrega' => ['preparado_ruta' => 'private/viejo.pdf']])]);
        $response = $this->postJson(route('entrega.preparar', $t), ['version' => 1, 'modo' => 'original'])->assertOk()->assertJsonPath('version', 2);
        $entrega = $t->fresh()->datos['entrega'];
        $original = Storage::disk('local')->get($t->acta->pdf_ruta);
        $this->assertSame('ORIGINAL', $entrega['modo']);
        $this->assertSame($original, Storage::disk('local')->get($entrega['preparado_ruta']));
        $this->assertSame(hash('sha256', $original), $entrega['preparado_sha256']);
        $this->get($response->json('descarga'))->assertOk()->assertDownload('solicitud-'.$t->id.'-preparado.pdf');
        $this->get(route('atencion.show', $t))->assertOk()->assertSee('Abrir Firma Perú')->assertDontSee('Ajustar ubicación del logo')->assertDontSee('Pendiente de habilitación institucional');
        $this->postJson(route('entrega.preparar', $t), ['version' => 1, 'modo' => 'original'])->assertUnprocessable();
        $this->assertSame($original, Storage::disk('local')->get($t->acta->pdf_ruta));
    }

    public function test_descargar_original_varias_veces_no_duplica_archivos_ni_cambia_la_solicitud(): void
    {
        $t = $this->tramite();
        $archivos = Storage::disk('local')->allFiles();
        for ($i = 0; $i < 3; $i++) {
            $response = $this->get(route('entrega.archivo', [$t, 'original']).'?descargar=1')->assertOk()->assertDownload();
        }
        $this->assertSame($archivos, Storage::disk('local')->allFiles());
        $this->assertSame(1, $t->fresh()->version);
        $this->assertArrayNotHasKey('entrega', $t->fresh()->datos);
        $this->assertSame(Storage::disk('local')->get($t->acta->pdf_ruta), $response->streamedContent());
    }

    public function test_subida_directa_sin_descarga_usa_original_y_solo_guarda_el_firmado(): void
    {
        $t = $this->tramite();
        $original = Storage::disk('local')->get($t->acta->pdf_ruta);
        $pdf = $original."\nrevision firmada de prueba";
        $mock = $this->partialMock(\App\Services\FirmaDocumentoService::class);
        $mock->shouldReceive('verificar')->once()->with($pdf, $original)->andReturn(['integridad' => 'VERIFICADA', 'firmante' => 'Persona ficticia', 'confianza' => 'PENDIENTE_REVISION']);
        $this->post(route('entrega.cargar', $t), ['version' => 1, 'archivo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('firmado.pdf', $pdf)])->assertSessionHasNoErrors();
        $entrega = $t->fresh()->datos['entrega'];
        $this->assertSame($t->acta->pdf_ruta, $entrega['preparado_ruta']);
        $this->assertSame($original, Storage::disk('local')->get($t->acta->pdf_ruta));
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $this->assertSame('APROBADA', $t->fresh()->estado);
    }

    public function test_subida_directa_rechaza_un_documento_ajeno_sin_guardarlo(): void
    {
        $t = $this->tramite();
        $antes = Storage::disk('local')->allFiles();
        $this->post(route('entrega.cargar', $t), ['version' => 1, 'archivo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('otro.pdf', "%PDF-1.4\nOTRO DOCUMENTO\n%%EOF")])->assertSessionHasErrors('archivo');
        $this->assertSame($antes, Storage::disk('local')->allFiles());
        $this->assertArrayNotHasKey('entrega', $t->fresh()->datos);
    }
}
