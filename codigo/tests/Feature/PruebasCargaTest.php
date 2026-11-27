<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruebasCargaTest extends TestCase
{
    use RefreshDatabase;

    private function buscar(array $cabeceras = [])
    {
        return $this->withHeaders($cabeceras)->post(route('portal.buscar'), []);
    }

    public function test_los_limites_siguen_activos_sin_la_clave_de_carga(): void
    {
        config(['pruebas.carga_token' => 'clave-de-carga-de-prueba']);
        for ($i = 0; $i < 5; $i++) $this->buscar(['X-Prueba-Carga' => 'clave-incorrecta'])->assertStatus(302);
        $this->buscar(['X-Prueba-Carga' => 'clave-incorrecta'])->assertStatus(429);
    }

    public function test_la_clave_de_carga_omite_el_limite_fuera_de_produccion(): void
    {
        config(['pruebas.carga_token' => 'clave-de-carga-de-prueba']);
        for ($i = 0; $i < 8; $i++) $this->buscar(['X-Prueba-Carga' => 'clave-de-carga-de-prueba'])->assertStatus(302);
    }

    public function test_cada_ruta_tiene_su_propio_contador_de_intentos(): void
    {
        // Using other limited routes must not consume the search allowance (shared counter bug).
        for ($i = 0; $i < 4; $i++) $this->get('/tramite/acceso/'.\Illuminate\Support\Str::uuid().'?signature=x')->assertRedirect();
        for ($i = 0; $i < 3; $i++) $this->get(route('portal.documento'))->assertRedirect();
        for ($i = 0; $i < 5; $i++) $this->buscar()->assertStatus(302);
        $this->buscar()->assertStatus(429);
    }

    public function test_en_produccion_o_sin_clave_configurada_no_hay_excepcion(): void
    {
        config(['pruebas.carga_token' => null]);
        for ($i = 0; $i < 5; $i++) $this->buscar(['X-Prueba-Carga' => ''])->assertStatus(302);
        $this->buscar(['X-Prueba-Carga' => ''])->assertStatus(429);

        $this->app['env'] = 'production';
        config(['pruebas.carga_token' => 'clave-de-carga-de-prueba']);
        $this->assertFalse(\App\Http\Middleware\ThrottleConPruebasCarga::esPruebaDeCarga(
            \Illuminate\Http\Request::create('/', 'POST', server: ['HTTP_X_PRUEBA_CARGA' => 'clave-de-carga-de-prueba'])));
    }
}
