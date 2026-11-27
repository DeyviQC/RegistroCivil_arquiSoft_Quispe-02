<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use App\Services\FirmaDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EntregaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private function solicitud(): TramitePublico
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($staff);
        $tramite = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'estado' => 'APROBADA', 'email' => 'prueba@example.test', 'email_verificado_at' => now(),
            'datos' => ['busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Persona de prueba']],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $tramite->id, 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $tramite;
    }

    public function test_bloquea_preparacion_sin_aprobacion_o_sin_pago_y_version_obsoleta(): void
    {
        $t = $this->solicitud();
        $data = ['version' => 1, 'pagina' => 1, 'posicion' => 'inferior_derecha'];
        $t->update(['estado' => 'EN_REVISION']);
        $this->post(route('entrega.preparar', $t), $data)->assertForbidden();
        $t->update(['estado' => 'APROBADA']);
        $t->pago->update(['estado' => 'CREANDO']);
        $this->post(route('entrega.preparar', $t), $data)->assertForbidden();
        $t->pago->update(['estado' => 'PAGADO']);
        $this->post(route('entrega.preparar', $t), array_merge($data, ['version' => 0]))->assertSessionHasErrors('firma');
    }

    public function test_flujo_carga_revision_y_descarga_con_correo_verificado(): void
    {
        $t = $this->solicitud();
        Storage::disk('local')->put('private/preparado.pdf', '%PDF-1.4 preparado');
        $mock = $this->mock(FirmaDocumentoService::class);
        $mock->shouldReceive('preparar')->once()->andReturn(['preparado_ruta' => 'private/preparado.pdf', 'preparado_sha256' => hash('sha256', '%PDF-1.4 preparado')]);
        $mock->shouldReceive('verificar')->once()->andReturn(['integridad' => 'VERIFICADA', 'firmante' => 'Persona de prueba', 'confianza' => 'PENDIENTE_REVISION']);
        $this->post(route('entrega.preparar', $t), ['version' => 1, 'pagina' => 1, 'posicion' => 'inferior_derecha'])->assertSessionHasNoErrors();
        $this->get(route('atencion.show', $t))->assertOk()->assertSee('Descargar para firmar');
        $preview = $this->get(route('entrega.archivo', [$t, 'preparado']));
        $preview->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $preview->headers->get('Cache-Control'));
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $preview->baseResponse);
        $this->assertSame('%PDF-1.4 preparado', file_get_contents($preview->baseResponse->getFile()->getPathname()));
        $this->get(route('entrega.archivo', [$t, 'preparado']).'?descargar=1')->assertOk()->assertDownload('solicitud-'.$t->id.'-preparado.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post(route('entrega.cargar', $t), ['version' => 2, 'archivo' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4 preparado\nfirmado\n%%EOF")])->assertSessionHasNoErrors();
        $this->get(route('atencion.show', $t))->assertOk()->assertSee('Revisa y publica');
        $this->get(route('entrega.archivo', [$t, 'firmado']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('entrega.archivo', [$t, 'firmado']).'?descargar=1')->assertOk()->assertDownload('solicitud-'.$t->id.'-firmado.pdf');
        $session = ['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->addMinutes(30)->timestamp]];
        $this->withSession($session)->get(route('portal.documento'))->assertForbidden();
        $this->post(route('entrega.publicar', $t), ['version' => 3])->assertSessionHasErrors(['revision_firma', 'sustento_firma']);
        $this->post(route('entrega.publicar', $t), ['version' => 3, 'revision_firma' => 1, 'sustento_firma' => 'Revisión de confianza y contenido en herramienta autorizada.'])->assertSessionHasNoErrors();
        $this->assertSame('LISTA', $t->fresh()->estado);
        $this->withSession($session)->get(route('portal.documento'))->assertOk()->assertDownload('mi-acta-firmada.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('portal.estado'))->assertOk()->assertSee('Descargar mi acta');
        $this->withSession(['portal_acceso' => ['codigo' => $t->codigo, 'expira' => now()->subMinute()->timestamp]])->get(route('portal.documento'))->assertRedirect(route('portal.seguimiento'));
        $this->post(route('entrega.descartar', $t), ['version' => 4, 'motivo_descarte' => 'No corresponde el archivo'])->assertForbidden();
    }

    public function test_rechaza_archivo_sin_firma_y_no_habilita_descarga_de_otra_solicitud(): void
    {
        $t = $this->solicitud();
        $pdf = '%PDF-1.4 sin firma';
        Storage::disk('local')->put('private/preparado.pdf', $pdf);
        $t->update(['datos' => array_merge($t->datos, ['entrega' => ['preparado_ruta' => 'private/preparado.pdf', 'preparado_sha256' => hash('sha256', $pdf)]])]);
        $this->post(route('entrega.cargar', $t), ['version' => 1, 'archivo' => UploadedFile::fake()->createWithContent('sin-firma.pdf', $pdf)])->assertSessionHasErrors('archivo');
        $this->assertArrayNotHasKey('firmado_ruta', $t->fresh()->datos['entrega']);
        $this->assertSame('APROBADA', $t->fresh()->estado);
        $this->withSession(['portal_acceso' => ['codigo' => (string) Str::uuid(), 'expira' => now()->addMinutes(10)->timestamp]])->get(route('portal.documento'))->assertNotFound();
        $this->get(route('entrega.archivo', [$t, 'inexistente']))->assertNotFound();
    }

    public function test_integridad_rechaza_documento_distinto_o_modificado(): void
    {
        $service = new FirmaDocumentoService;
        foreach ([['%PDF otro', '%PDF preparado'], ['%PDF preparado', '%PDF preparado']] as [$pdf, $base]) {
            try {
                $service->verificar($pdf, $base);
                $this->fail('Debe rechazar un archivo no firmado.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('archivo', $e->errors());
            }
        }
    }
}
