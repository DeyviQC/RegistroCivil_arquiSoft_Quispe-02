<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use App\Services\{FirmaDocumentoService, FirmaPeruService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class FirmaPeruTest extends TestCase
{
    use RefreshDatabase;

    private function solicitud(bool $credentials = true): TramitePublico
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        config(['firma.peru_credenciales' => Storage::disk('local')->path('private/test-authorization.json'),
            'portal.http_ca_file' => null, 'app.url' => 'http://localhost']);
        if ($credentials) {
            Storage::disk('local')->put('private/test-authorization.json', json_encode(['client_id' => 'test-client',
                'client_secret' => 'test-only-secret', 'token_url' => 'https://firmaperu.gob.pe/test-token']));
            $jwt = 'eyJhbGciOiJSUzI1NiJ9.'.rtrim(strtr(base64_encode(json_encode(['exp' => now()->addHour()->timestamp])), '+/', '-_'), '=').'.testsignature';
            Http::fake(['https://firmaperu.gob.pe/test-token' => Http::response($jwt, 200)]);
        }
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($user);
        $acta = Acta::factory()->create();
        Storage::disk('local')->put($acta->pdf_ruta, "%PDF-1.4\nacta de prueba\n%%EOF");
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $acta->id, 'estado' => 'APROBADA',
            'datos' => ['busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Persona ficticia']],
            'email' => 'persona@example.test', 'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $t;
    }

    private function iniciar(TramitePublico $t): array
    {
        $response = $this->postJson(route('firmaperu.iniciar', $t), ['version' => $t->fresh()->version])->assertOk();
        return [$response->json(), json_decode(base64_decode($response->json('param')), true)];
    }

    private function parametros(array $launch): array
    {
        $response = $this->post($launch['param_url'], ['param_token' => $launch['param_token']])->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        return json_decode(base64_decode($response->getContent()), true);
    }

    public function test_no_finge_firma_ni_habilita_boton_sin_credenciales(): void
    {
        $t = $this->solicitud(false);
        $this->postJson(route('firmaperu.iniciar', $t), ['version' => 1])->assertUnprocessable()->assertJsonValidationErrors('firma_peru');
        $this->assertArrayNotHasKey('entrega', $t->fresh()->datos);
        $this->get(route('atencion.show', $t))->assertOk()->assertSee('Abrir Firma Perú')->assertDontSee('id="firma-peru-iniciar"', false);
        Http::assertNothingSent();
    }

    public function test_parametros_de_un_uso_y_pdf_original_sin_tarjeta_de_preparacion(): void
    {
        $t = $this->solicitud();
        [$start, $launch] = $this->iniciar($t);
        $this->assertStringNotContainsString('test-only-secret', json_encode($start));
        $this->assertSame(48596, $start['port']);
        $this->get(route('atencion.show', $t))->assertOk()->assertSee('Abrir Firma Perú')->assertDontSee('clienteweb/firmaperu.min.js');
        $params = $this->parametros($launch);
        $this->post($launch['param_url'], ['param_token' => $launch['param_token']])->assertForbidden();
        $this->assertTrue($params['visiblePosition']);
        $this->assertSame(1, $params['signatureStyle']);
        $this->assertSame('PAdES', $params['signatureFormat']);
        $this->assertArrayNotHasKey('client_secret', $params);
        // Native app has no authenticated browser session; only its scoped capability works.
        Auth::forgetGuards();
        $this->app['session']->flush();
        $response = $this->get($params['documentToSign'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(Storage::disk('local')->get($t->acta->pdf_ruta), file_get_contents($response->baseResponse->getFile()->getPathname()));
        $this->get($params['imageToStamp'])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(preg_replace('/token=[^&]+/', 'token='.str_repeat('z', 64), $params['documentToSign']))->assertForbidden();
        $this->get(str_replace('/'.$t->id.'/', '/999999/', $params['documentToSign']))->assertNotFound();
        $this->travel(11)->minutes();
        $this->get($params['documentToSign'])->assertForbidden();
    }

    public function test_retorno_automatico_verifica_y_no_publica_ni_acepta_repeticiones(): void
    {
        $t = $this->solicitud();
        [$start, $launch] = $this->iniciar($t);
        $params = $this->parametros($launch);
        $this->mock(FirmaDocumentoService::class)->shouldReceive('verificar')->once()->andReturn([
            'integridad' => 'VERIFICADA', 'firmante' => 'Firmante de prueba', 'confianza' => 'PENDIENTE_REVISION']);
        $this->post($params['uploadDocumentSigned'], ['signed_file' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4\nfirmado\n%%EOF")])->assertOk();
        $actual = $t->fresh();
        $this->assertSame('APROBADA', $actual->estado);
        $this->assertSame('RECIBIDA', $actual->datos['entrega']['firma_peru']['estado']);
        $this->assertSame('VERIFICADA', $actual->datos['entrega']['integridad']);
        Storage::disk('local')->assertExists($actual->datos['entrega']['firmado_ruta']);
        $this->assertArrayNotHasKey('parametros', $actual->datos['entrega']['firma_peru']);
        $this->get(route('firmaperu.estado', $t))->assertOk()->assertJson(['recibida' => true]);
        $this->post($params['uploadDocumentSigned'], ['signed_file' => UploadedFile::fake()->createWithContent('firmado.pdf', '%PDF fake')])->assertForbidden();
        $this->assertDatabaseHas('auditoria', ['accion' => 'RECIBIR_FIRMAPERU']);
    }

    public function test_cancela_o_nuevo_intento_revoca_las_capacidades_previas(): void
    {
        $t = $this->solicitud();
        [$start, $launch] = $this->iniciar($t);
        $params = $this->parametros($launch);
        $this->postJson(route('firmaperu.cancelar', $t), ['intento' => $start['intento']])->assertOk();
        $this->get($params['documentToSign'])->assertForbidden();
        $this->post($params['uploadDocumentSigned'])->assertForbidden();
        [$new, $newLaunch] = $this->iniciar($t);
        $newParams = $this->parametros($newLaunch);
        $this->get($params['documentToSign'])->assertForbidden();
        $this->get($newParams['documentToSign'])->assertOk();
    }

    public function test_rechaza_pdf_sin_firma_y_estado_no_autorizado(): void
    {
        $t = $this->solicitud();
        [$start, $launch] = $this->iniciar($t);
        $params = $this->parametros($launch);
        $this->postJson($params['uploadDocumentSigned'], ['signed_file' => UploadedFile::fake()->createWithContent('sin-firma.pdf', Storage::disk('local')->get($t->acta->pdf_ruta))])->assertUnprocessable();
        $this->assertArrayNotHasKey('firmado_ruta', $t->fresh()->datos['entrega']);
        $t->update(['estado' => 'EN_REVISION']);
        $this->get($params['documentToSign'])->assertForbidden();
        $this->postJson(route('firmaperu.iniciar', $t), ['version' => $t->fresh()->version])->assertForbidden();
    }

    public function test_no_envia_secretos_a_un_endpoint_no_oficial(): void
    {
        $this->solicitud();
        Storage::disk('local')->put('private/test-authorization.json', json_encode(['client_id' => 'x', 'client_secret' => 'y', 'token_url' => 'http://127.0.0.1:9999/token']));
        $this->assertFalse(app(FirmaPeruService::class)->disponible());
        Http::assertNothingSent();
    }
}
