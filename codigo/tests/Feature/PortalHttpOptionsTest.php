<?php

namespace Tests\Feature;

use App\Services\PortalHttpOptions;
use Tests\TestCase;

class PortalHttpOptionsTest extends TestCase
{
    public function test_verifica_tls_por_defecto_y_no_sigue_redirecciones(): void
    {
        config(['portal.http_ca_file' => null]);
        $this->assertSame(['allow_redirects' => false, 'verify' => true], PortalHttpOptions::trusted());
    }

    public function test_utiliza_el_bundle_configurado(): void
    {
        config(['portal.http_ca_file' => __FILE__]);
        $this->assertSame(__FILE__, PortalHttpOptions::trusted()['verify']);
    }

    public function test_un_bundle_ausente_bloquea_la_conexion(): void
    {
        config(['portal.http_ca_file' => __DIR__.'/certificado-inexistente.pem']);
        $this->expectException(\RuntimeException::class);
        PortalHttpOptions::trusted();
    }
}
