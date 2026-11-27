<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/** The OpenAPI contract (docs/api/openapi.yaml) must describe exactly the implemented API v1. */
class ContratoOpenApiTest extends TestCase
{
    private function contrato(): array
    {
        return Yaml::parseFile(base_path('docs/api/openapi.yaml'));
    }

    /** @return array<string, \Illuminate\Routing\Route> "METHOD /path" => route */
    private function rutasV1(): array
    {
        $rutas = [];
        foreach (Route::getRoutes() as $ruta) {
            if (! str_starts_with($ruta->uri(), 'api/v1/')) continue;
            foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                $rutas[$metodo.' /'.$ruta->uri()] = $ruta;
            }
        }

        return $rutas;
    }

    public function test_el_contrato_es_openapi_3_y_tiene_seguridad_bearer(): void
    {
        $contrato = $this->contrato();
        $this->assertSame('3.0.3', $contrato['openapi']);
        $this->assertSame('bearer', $contrato['components']['securitySchemes']['bearerAuth']['scheme']);
    }

    public function test_cada_ruta_implementada_esta_documentada_y_viceversa(): void
    {
        $documentadas = [];
        foreach ($this->contrato()['paths'] as $ruta => $operaciones) {
            foreach (array_keys($operaciones) as $metodo) {
                $documentadas[] = strtoupper($metodo).' '.$ruta;
            }
        }
        $implementadas = array_keys($this->rutasV1());
        sort($documentadas);
        sort($implementadas);

        $this->assertSame($implementadas, $documentadas, 'El contrato OpenAPI y las rutas de la API v1 no coinciden.');
    }

    public function test_las_rutas_protegidas_declaran_token_y_respuesta_401(): void
    {
        $contrato = $this->contrato();
        foreach ($this->rutasV1() as $clave => $ruta) {
            [$metodo, $uri] = explode(' ', $clave, 2);
            $operacion = $contrato['paths'][$uri][strtolower($metodo)];
            $protegida = in_array('auth:sanctum', $ruta->gatherMiddleware(), true);
            $this->assertSame($protegida, isset($operacion['security']), "Seguridad mal documentada en {$clave}.");
            if ($protegida) {
                $this->assertArrayHasKey('401', $operacion['responses'], "{$clave} debe documentar 401.");
            }
            $this->assertNotEmpty($operacion['summary'] ?? null, "{$clave} necesita un resumen.");
        }
    }

    public function test_swagger_ui_y_el_contrato_se_sirven_desde_el_sistema(): void
    {
        $this->get(route('api.documentacion'))->assertOk()->assertSee('swagger-ui-bundle.js')->assertSee('SwaggerUIBundle');
        $this->get(route('api.documentacion.contrato'))->assertOk()->assertHeader('Content-Type', 'application/yaml; charset=UTF-8');
        $this->assertFileExists(public_path('vendor/swagger-ui/swagger-ui-bundle.js'));
    }
}
