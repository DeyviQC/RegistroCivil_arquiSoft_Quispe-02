<?php

namespace Tests\Feature;

use App\Contracts\ContrastadorIdentidad;
use App\Models\{Acta, ActaNacimiento, TramitePublico};
use App\Services\ApisPeruService;
use App\Support\NombrePersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Mail, Storage};
use Tests\TestCase;

/** Sprint 2 — SOLID y patrones (sprint-2/04-solid-y-patrones.md). */
class PrincipiosSolidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        Http::preventStrayRequests();
    }

    /** Implementation swapped in through the container, as a future RENIEC connector would be. */
    private function usarContrastador(string $resultado): void
    {
        $this->app->bind(ContrastadorIdentidad::class, fn () => new class($resultado) implements ContrastadorIdentidad {
            public function __construct(private string $resultado) {}
            public function contrastar(array $datos): string { return $this->resultado; }
        });
    }

    private function enviarDatos(): \Illuminate\Testing\TestResponse
    {
        $acta = Acta::factory()->create();
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Ficticia Persona', 'dni' => '12345678', 'fecha_nacimiento' => '1990-01-01']);
        Storage::disk('local')->put($acta->pdf_ruta, '%PDF-1.4 prueba');
        $this->post('/tramite/buscar', ['terminos' => 1, 'tipo' => 'NACIMIENTO', 'titular' => 'Persona Ficticia', 'fecha' => '1990-01-01'])->assertRedirect('/tramite/datos');
        return $this->post('/tramite/datos', ['dni' => '12345678', 'nombre' => 'Persona Ficticia', 'nacimiento' => '1990-01-01', 'verificador' => '1',
            'email' => 'prueba@example.test', 'declaracion' => 1, 'relacion' => 'titular', 'motivo' => 'Copia de mi acta para trámite personal.']);
    }

    public function test_una_sola_regla_compara_nombres_sin_importar_orden_tildes_ni_signos(): void
    {
        $this->assertSame('ANA DIAZ PEREZ', NombrePersona::normalizar('Pérez Díaz, Ana'));
        $this->assertTrue(NombrePersona::coinciden('ANA PEREZ DIAZ', 'pérez díaz, ana'));
        $this->assertFalse(NombrePersona::coinciden('Ana Pérez', 'Ana Pérez Díaz'));
        $this->assertFalse(NombrePersona::coinciden('', ''), 'Dos nombres vacíos no son una coincidencia.');
    }

    public function test_el_contrato_de_identidad_se_resuelve_con_apisperu_por_defecto(): void
    {
        $this->assertInstanceOf(ApisPeruService::class, app(ContrastadorIdentidad::class));
    }

    public function test_el_portal_funciona_con_cualquier_implementacion_del_contrato(): void
    {
        $this->usarContrastador(ContrastadorIdentidad::DATOS_CONTRASTADOS);
        $this->enviarDatos()->assertSessionHasNoErrors()->assertRedirect('/tramite/codigo');
        $this->assertSame(ContrastadorIdentidad::DATOS_CONTRASTADOS, TramitePublico::sole()->identidad_estado);
    }

    public function test_si_la_implementacion_no_coincide_no_se_crea_solicitud_ni_cobro(): void
    {
        $this->usarContrastador(ContrastadorIdentidad::NO_COINCIDE);
        $this->enviarDatos()->assertSessionHasErrors('dni');
        $this->assertSame(0, TramitePublico::count());
    }
}
