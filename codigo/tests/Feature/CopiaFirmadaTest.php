<?php

namespace Tests\Feature;

use App\Models\{Acta, PagoTramite, Role, TramitePublico, User};
use App\Services\FirmaDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CopiaFirmadaTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGINAL = '%PDF-1.4 acta original';
    private Acta $acta;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Storage::disk('local')->put('private/actas/acta.pdf', self::ORIGINAL);
        $this->acta = Acta::factory()->create(['pdf_ruta' => 'private/actas/acta.pdf', 'pdf_hash' => hash('sha256', self::ORIGINAL)]);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($staff);
        // Real preparation over the archive file; only the CMS check is simulated.
        $firma = Mockery::mock(FirmaDocumentoService::class)->makePartial();
        $firma->shouldReceive('verificar')->andReturnUsing(function (string $pdf, string $original) {
            if (! str_starts_with($pdf, $original)) throw \Illuminate\Validation\ValidationException::withMessages(['archivo' => 'No coincide.']);
            return ['integridad' => 'VERIFICADA', 'firmante' => 'FUNCIONARIO DE PRUEBA', 'certificado_sha256' => 'abc', 'confianza' => 'PENDIENTE_REVISION'];
        });
        $this->app->instance(FirmaDocumentoService::class, $firma);
    }

    private function solicitud(): TramitePublico
    {
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $this->acta->id, 'estado' => 'APROBADA',
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(),
            'datos' => ['busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular']], 'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'importe_centimos' => 1030, 'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $t->fresh();
    }

    private function firmarYPublicar(TramitePublico $t): void
    {
        $this->post(route('entrega.cargar', $t), ['version' => $t->version,
            'archivo' => UploadedFile::fake()->createWithContent('firmado.pdf', self::ORIGINAL."\nfirma\n%%EOF")])->assertSessionHasNoErrors();
        $this->get(route('atencion.show', $t))->assertSee('las próximas solicitudes se entregan sin volver a firmar');
        $this->post(route('entrega.publicar', $t), ['version' => $t->fresh()->version, 'revision_firma' => 1,
            'sustento_firma' => 'Revisado el contenido y el certificado.'])->assertSessionHasNoErrors();
    }

    public function test_la_primera_firma_queda_en_el_acta_y_la_siguiente_solicitud_se_entrega_sin_firmar(): void
    {
        $primera = $this->solicitud();
        $this->firmarYPublicar($primera);
        $acta = $this->acta->fresh();
        $this->assertTrue($acta->copiaFirmadaVigente());
        $this->assertSame($primera->id, $acta->firmado_tramite_id);
        $this->assertSame('FUNCIONARIO DE PRUEBA', $acta->firmado_firmante);
        $this->get(route('actas.pdf-firmado', $acta))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('actas.show', $acta))->assertSee('Copia firmada');

        $segunda = $this->solicitud();
        $this->get(route('atencion.show', $segunda))->assertOk()
            ->assertSee('Esta acta ya tiene una copia firmada')->assertSee('Entregar con la copia ya firmada');
        $this->post(route('entrega.reutilizar', $segunda), ['version' => $segunda->version])->assertSessionHas('exito');

        $segunda->refresh();
        $this->assertSame('LISTA', $segunda->estado);
        $this->assertSame($primera->id, $segunda->datos['entrega']['reutilizada']['tramite_id']);
        $this->assertStringContainsString('N.º '.str_pad((string) $primera->id, 6, '0', STR_PAD_LEFT), $segunda->datos['entrega']['revision']['sustento']);
        $this->assertSame(Storage::disk('local')->get($acta->firmado_ruta), Storage::disk('local')->get($segunda->datos['entrega']['firmado_ruta']));
        // Reuse does not overwrite the act's reference copy.
        $this->assertSame($primera->id, $this->acta->fresh()->firmado_tramite_id);
        // The citizen can download it like any other delivery.
        $this->withSession(['portal_acceso' => ['codigo' => $segunda->codigo, 'expira' => now()->addMinutes(20)->timestamp]])
            ->get(route('portal.documento'))->assertOk();
    }

    public function test_si_el_original_cambia_o_la_copia_vence_hay_que_firmar_de_nuevo(): void
    {
        $this->firmarYPublicar($this->solicitud());
        $nueva = $this->solicitud();

        $this->travel(91)->days();
        $this->assertFalse($this->acta->fresh()->copiaFirmadaVigente());
        $this->get(route('atencion.show', $nueva))->assertDontSee('Entregar con la copia ya firmada')->assertSee('Descargar para firmar');
        $this->post(route('entrega.reutilizar', $nueva), ['version' => $nueva->version])->assertSessionHasErrors('firma');
        $this->travelBack();

        config(['firma.vigencia_copia_dias' => 0]);
        $this->assertTrue($this->acta->fresh()->copiaFirmadaVigente());
        $this->acta->update(['pdf_hash' => hash('sha256', 'acta corregida')]);
        $this->assertFalse($this->acta->fresh()->copiaFirmadaVigente());
        $this->post(route('entrega.reutilizar', $nueva), ['version' => $nueva->version])->assertSessionHasErrors('firma');
        $this->assertSame('APROBADA', $nueva->fresh()->estado);
    }

    public function test_una_copia_con_sello_de_la_solicitud_no_se_guarda_en_el_acta(): void
    {
        $t = $this->solicitud();
        Storage::disk('local')->put('private/preparado.pdf', '%PDF-1.4 sello solicitud');
        $datos = $t->datos;
        $datos['entrega'] = ['preparado_ruta' => 'private/preparado.pdf', 'preparado_sha256' => hash('sha256', '%PDF-1.4 sello solicitud')];
        $t->update(['datos' => $datos]);
        $this->post(route('entrega.cargar', $t), ['version' => $t->fresh()->version,
            'archivo' => UploadedFile::fake()->createWithContent('firmado.pdf', "%PDF-1.4 sello solicitud\nfirma")])->assertSessionHasNoErrors();
        $this->post(route('entrega.publicar', $t), ['version' => $t->fresh()->version, 'revision_firma' => 1,
            'sustento_firma' => 'Revisado el contenido y el certificado.'])->assertSessionHasNoErrors();
        $this->assertNull($this->acta->fresh()->firmado_ruta);
    }
}
