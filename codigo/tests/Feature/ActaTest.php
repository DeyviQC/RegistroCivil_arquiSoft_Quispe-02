<?php

namespace Tests\Feature;

use App\Models\Acta;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function usuarioAsistente(): User
    {
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::where('slug', Role::ASISTENTE)->first());

        return $usuario;
    }

    private function pdfValido(string $nombre = 'acta.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $nombre,
            "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF"
        );
    }

    private function datosNacimiento(array $overrides = []): array
    {
        return array_merge([
            'tipo' => Acta::TIPO_NACIMIENTO,
            'madre_nombre_estado' => 'REGISTRADO', 'madre_nombre' => 'Madre Ficticia', 'madre_dni_estado' => 'REGISTRADO', 'madre_dni' => '23456789',
            'padre_nombre_estado' => 'REGISTRADO', 'padre_nombre' => 'Padre Ficticio', 'padre_dni_estado' => 'NO_CONSIGNADO',
            'numero_acta' => '00125',
            'anio' => 2026,
            'fecha_acta' => '2026-05-20',
            'apellido_nombre' => 'Pérez López Juan',
            'sobre' => 'SOBRE-1',
            'correlativo' => 1,
            'apellido_paterno' => 'Pérez',
            'apellido_materno' => 'López',
            'dni' => '12345678',
            'fecha_nacimiento' => '2026-05-20',
            'lugar_nacimiento' => 'Hospital Municipal',
            'sexo' => 'M',
        ], $overrides);
    }

    public function test_asistente_puede_crear_acta_de_nacimiento_con_pdf_valido(): void
    {
        $usuario = $this->usuarioAsistente();

        $respuesta = $this->actingAs($usuario)->post('/actas', array_merge(
            $this->datosNacimiento(),
            ['pdf' => $this->pdfValido()]
        ));

        $respuesta->assertRedirect();
        $this->assertDatabaseHas('actas', ['numero_acta' => '00125', 'anio' => 2026, 'tipo' => 'NACIMIENTO']);
        $this->assertDatabaseHas('acta_nacimientos', ['dni' => '12345678']);
    }

    public function test_no_permite_acta_duplicada_por_numero_anio_tipo(): void
    {
        $usuario = $this->usuarioAsistente();

        $this->actingAs($usuario)->post('/actas', array_merge($this->datosNacimiento(), ['pdf' => $this->pdfValido('a.pdf')]));

        $respuesta = $this->actingAs($usuario)->post('/actas', array_merge(
            $this->datosNacimiento(['dni' => '99999999']),
            ['pdf' => $this->pdfValido('b.pdf')]
        ));

        $respuesta->assertSessionHasErrors('numero_acta');
        $this->assertEquals(1, Acta::count());
    }

    public function test_rechaza_archivo_que_no_es_pdf(): void
    {
        $usuario = $this->usuarioAsistente();

        $archivoFalso = UploadedFile::fake()->create('documento.txt', 10, 'text/plain');

        $respuesta = $this->actingAs($usuario)->post('/actas', array_merge(
            $this->datosNacimiento(),
            ['pdf' => $archivoFalso]
        ));

        $respuesta->assertSessionHasErrors('pdf');
        $this->assertEquals(0, Acta::count());
    }

    public function test_detecta_pdf_duplicado_por_hash(): void
    {
        $usuario = $this->usuarioAsistente();
        $mismoContenido = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

        $this->actingAs($usuario)->post('/actas', array_merge(
            $this->datosNacimiento(),
            ['pdf' => UploadedFile::fake()->createWithContent('doc1.pdf', $mismoContenido)]
        ));

        $respuesta = $this->actingAs($usuario)->post('/actas', array_merge(
            $this->datosNacimiento(['numero_acta' => '00999', 'correlativo' => 2]),
            ['pdf' => UploadedFile::fake()->createWithContent('doc2.pdf', $mismoContenido)]
        ));

        $respuesta->assertSessionHasErrors('pdf');
        $this->assertEquals(1, Acta::count());
    }

    public function test_puede_consultar_un_acta_registrada(): void
    {
        $usuario = $this->usuarioAsistente();
        $this->actingAs($usuario)->post('/actas', array_merge($this->datosNacimiento(), ['pdf' => $this->pdfValido()]));
        $acta = Acta::first();

        $respuesta = $this->actingAs($usuario)->get("/actas/{$acta->id}");

        $respuesta->assertOk();
        $respuesta->assertSee('ACTA BLOQUEADA', false);
    }

    public function test_acta_registrada_no_puede_modificarse(): void
    {
        $usuario = $this->usuarioAsistente();
        $this->actingAs($usuario)->post('/actas', array_merge($this->datosNacimiento(), ['pdf' => $this->pdfValido()]));
        $acta = Acta::first();

        $this->assertFalse($usuario->can('update', $acta));
    }

    public function test_acta_registrada_no_puede_eliminarse(): void
    {
        $usuario = $this->usuarioAsistente();
        $this->actingAs($usuario)->post('/actas', array_merge($this->datosNacimiento(), ['pdf' => $this->pdfValido()]));
        $acta = Acta::first();

        $this->assertFalse($usuario->can('delete', $acta));
    }

    public function test_puede_visualizar_el_pdf_del_acta(): void
    {
        $usuario = $this->usuarioAsistente();
        $this->actingAs($usuario)->post('/actas', array_merge($this->datosNacimiento(), ['pdf' => $this->pdfValido()]));
        $acta = Acta::first();

        $respuesta = $this->actingAs($usuario)->get("/actas/{$acta->id}/pdf");

        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($usuario)->get("/actas/{$acta->id}/print")
            ->assertOk()->assertViewIs('pdf.imprimir')
            ->assertSee('window.print()', false)
            ->assertSee(route('actas.pdf', $acta), false);
    }

    public function test_puede_buscar_actas_por_numero(): void
    {
        $usuario = $this->usuarioAsistente();
        $this->actingAs($usuario)->post('/actas', array_merge($this->datosNacimiento(), ['pdf' => $this->pdfValido()]));

        $respuesta = $this->actingAs($usuario)->get('/actas?numero_acta=00125');

        $respuesta->assertOk();
        $respuesta->assertSee('00125');
    }
}
