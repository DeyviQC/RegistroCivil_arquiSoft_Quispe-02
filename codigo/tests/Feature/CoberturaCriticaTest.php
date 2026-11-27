<?php

namespace Tests\Feature;

use App\Contracts\ContrastadorIdentidad;
use App\Models\{Role, User};
use App\Services\{ApisPeruService, FirmaDocumentoService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Sprint 2 — gaps found in the coverage report (sprint-2/05-pruebas-cobertura-y-ci.md):
 * the positive path of the CMS signature check was always mocked, the DNI adapter only had
 * its error paths tested and no test logged out a user deactivated mid-session.
 */
class CoberturaCriticaTest extends TestCase
{
    use RefreshDatabase;

    private const PREPARADO = "%PDF-1.4 documento preparado para la solicitud\n";

    /**
     * Builds a PDF-shaped file with a real detached CMS signature over its ByteRange,
     * signed by a throwaway self-signed certificate (no real identity or key is involved).
     */
    private function pdfFirmado(?callable $alterarDatos = null): string
    {
        $cnf = tempnam(sys_get_temp_dir(), 'cnf');
        file_put_contents($cnf, "[req]\ndistinguished_name = dn\n[dn]\n");
        $cfg = ['config' => $cnf, 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $clave = openssl_pkey_new($cfg);
        $certificado = openssl_csr_sign(openssl_csr_new(['commonName' => 'Firmante de Prueba'], $clave, $cfg), null, $clave, 1, $cfg);
        unlink($cnf);
        $firmar = function (string $datos) use ($certificado, $clave): string {
            $entrada = tempnam(sys_get_temp_dir(), 'dat');
            $salida = tempnam(sys_get_temp_dir(), 'sig');
            file_put_contents($entrada, $datos);
            $this->assertTrue(openssl_cms_sign($entrada, $salida, $certificado, $clave, [], OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY, OPENSSL_ENCODING_DER));
            $der = (string) file_get_contents($salida);
            unlink($entrada);
            unlink($salida);
            return $der;
        };
        $largoHex = 2 * strlen($firmar('medida'));
        $final = "\n%%EOF\n";
        $plantilla = "1 0 obj << /Type /Sig /ByteRange [0 %010d %010d %010d] /Contents ";
        $inicio = strlen(self::PREPARADO.sprintf($plantilla, 0, 0, 0));
        $cabecera = self::PREPARADO.sprintf($plantilla, $inicio, $inicio + $largoHex + 2, strlen($final));
        $firmados = $alterarDatos ? $alterarDatos($cabecera.$final) : $cabecera.$final;
        $der = $firmar($firmados);
        $this->assertSame($largoHex, 2 * strlen($der), 'La firma debe ocupar el espacio reservado.');
        return $cabecera.'<'.bin2hex($der).'>'.$final;
    }

    public function test_una_firma_cms_valida_se_verifica_y_queda_pendiente_de_revision_humana(): void
    {
        $resultado = (new FirmaDocumentoService)->verificar($this->pdfFirmado(), self::PREPARADO);
        $this->assertSame('VERIFICADA', $resultado['integridad']);
        $this->assertSame('PENDIENTE_REVISION', $resultado['confianza'], 'La integridad no acredita la confianza en el firmante.');
        $this->assertSame('Firmante de Prueba', $resultado['firmante']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $resultado['certificado_sha256']);
    }

    public function test_un_byte_cambiado_despues_de_firmar_invalida_la_firma(): void
    {
        $pdf = $this->pdfFirmado();
        $posicion = strlen(self::PREPARADO) - 5;
        $alterado = substr_replace($pdf, $pdf[$posicion] === 'X' ? 'Y' : 'X', $posicion, 1);
        $this->expectException(ValidationException::class);
        (new FirmaDocumentoService)->verificar($alterado, substr($alterado, 0, strlen(self::PREPARADO)));
    }

    public function test_una_firma_de_otro_contenido_no_se_acepta(): void
    {
        $pdf = $this->pdfFirmado(fn (string $datos) => $datos.'contenido distinto');
        try {
            (new FirmaDocumentoService)->verificar($pdf, self::PREPARADO);
            $this->fail('Una firma que no corresponde al contenido debe rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('archivo', $e->errors());
        }
    }

    public function test_el_adaptador_dni_distingue_coincidencia_nombre_y_verificador(): void
    {
        config(['portal.apisperu_token' => 'token-solo-prueba']);
        Http::preventStrayRequests();
        $persona = ['success' => true, 'dni' => '12345678', 'nombres' => 'ANA', 'apellidoPaterno' => 'PÉREZ', 'apellidoMaterno' => 'DÍAZ', 'codVerifica' => 7];
        Http::fake(['dniruc.apisperu.com/*' => Http::response($persona)]);
        $dni = app(ApisPeruService::class);
        $this->assertSame(ContrastadorIdentidad::DATOS_CONTRASTADOS, $dni->contrastar(['dni' => '12345678', 'nombre' => 'Pérez Díaz, Ana', 'verificador' => '7']));
        $this->assertSame(ContrastadorIdentidad::NO_COINCIDE, $dni->contrastar(['dni' => '12345678', 'nombre' => 'Ana Pérez', 'verificador' => '7']));
        $this->assertSame(ContrastadorIdentidad::NO_COINCIDE, $dni->contrastar(['dni' => '12345678', 'nombre' => 'Ana Pérez Díaz', 'verificador' => '3']));
        $this->expectException(\RuntimeException::class);
        $dni->contrastar(['dni' => '87654321', 'nombre' => 'Ana Pérez Díaz', 'verificador' => '7']);
    }

    public function test_sin_token_configurado_no_se_consulta_al_proveedor(): void
    {
        config(['portal.apisperu_token' => null]);
        Http::preventStrayRequests();
        Http::fake();
        $this->assertSame(ContrastadorIdentidad::PENDIENTE_CONFIGURACION, app(ApisPeruService::class)->contrastar(['dni' => '12345678', 'nombre' => 'Ana', 'verificador' => '1']));
        Http::assertNothingSent();
    }

    public function test_un_usuario_desactivado_con_sesion_abierta_es_expulsado(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($usuario)->get('/dashboard')->assertOk();
        $usuario->update(['is_active' => false]);
        $this->get('/dashboard')->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }
}
