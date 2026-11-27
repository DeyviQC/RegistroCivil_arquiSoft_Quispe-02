<?php

namespace Tests\Feature;

use App\Models\Acta;
use App\Models\ActaNacimiento;
use App\Services\ApisPeruService;
use App\Services\CruceParentescoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CruceParentescoTest extends TestCase
{
    use RefreshDatabase;
    public function test_compara_solo_el_progenitor_declarado_y_no_expone_sus_datos(): void
    {
        $acta = Acta::factory()->create();
        $nacimiento = ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Titular', 'dni' => '12345678', 'fecha_nacimiento' => '2020-01-01', 'madre_dni' => '23456789', 'madre_dni_estado' => 'REGISTRADO', 'padre_dni' => '34567890', 'padre_dni_estado' => 'REGISTRADO']);
        $servicio = app(CruceParentescoService::class);
        $resultado = $servicio->comparar($acta, ['relacion' => 'madre', 'dni' => '23456789']);
        $this->assertSame('COINCIDE', $resultado['resultado']);
        $this->assertStringNotContainsString('23456789', json_encode($resultado));
        $this->assertSame('NO_COINCIDE', $servicio->comparar($acta, ['relacion' => 'padre', 'dni' => '23456789'])['resultado']);
        $nacimiento->update(['madre_dni_estado' => 'DESCONOCIDO', 'madre_dni' => null]);
        $this->assertSame('SIN_DATOS', $servicio->comparar($acta->fresh(), ['relacion' => 'madre', 'dni' => '23456789'])['resultado']);
        $this->assertSame('NO_APLICA', $servicio->comparar($acta, ['relacion' => 'representante', 'dni' => '23456789'])['resultado']);
    }

    public function test_sin_resultados_es_distinto_de_fallo_de_servicio_y_nunca_es_contrastado(): void
    {
        config(['portal.apisperu_token' => 'token-solo-prueba']);
        Http::preventStrayRequests();
        Http::fake(['dniruc.apisperu.com/*' => Http::sequence()->push(['success' => false, 'message' => 'No se encontraron resultados.'])->push(['message' => 'Error'], 503)]);
        $datos = ['dni' => '12345678', 'nombre' => 'Persona Ficticia', 'verificador' => '1'];
        $this->assertSame('SIN_RESULTADOS', app(ApisPeruService::class)->contrastar($datos));
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-solo-prueba') && !str_contains($request->url(), 'token='));
        $this->expectException(\RuntimeException::class);
        app(ApisPeruService::class)->contrastar($datos);
    }
}
