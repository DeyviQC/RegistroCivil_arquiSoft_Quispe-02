<?php
namespace Tests\Feature;

use App\Mail\CodigoTramite;
use App\Models\Acta;
use App\Models\TramitePublico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeguimientoCiudadanoTest extends TestCase
{
    use RefreshDatabase;

    private function solicitud(string $dni, string $email): TramitePublico
    {
        return TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => Acta::factory()->create()->id,
            'datos' => ['dni' => $dni, 'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular ficticio']],
            'email' => $email, 'terminos_version' => 'test', 'aceptado_at' => now()]);
    }

    public function test_consulta_sin_referencia_verifica_correo_y_limita_la_lista_y_la_seleccion(): void
    {
        Mail::fake();
        $primera = $this->solicitud('12345678', 'demo@example.test');
        $segunda = $this->solicitud('12345678', 'demo@example.test');
        $ajena = $this->solicitud('87654321', 'demo@example.test');
        $otroCorreo = $this->solicitud('12345678', 'otro@example.test');
        $this->get('/tramite/mis-solicitudes')->assertForbidden();
        $this->post('/tramite/seguimiento', ['dni' => '12345678', 'email' => 'demo@example.test'])->assertRedirect(route('portal.codigo'));
        $codigo = null;
        Mail::assertSent(CodigoTramite::class, function ($mail) use (&$codigo) { $codigo = $mail->codigo; return $mail->hasTo('demo@example.test'); });
        $this->get('/tramite/mis-solicitudes')->assertForbidden();
        $this->post('/tramite/codigo', ['codigo' => $codigo])->assertRedirect(route('portal.solicitudes'));
        $this->get('/tramite/mis-solicitudes')->assertOk()->assertViewHas('tramites', fn ($lista) => $lista->modelKeys() === [$segunda->id, $primera->id]);
        $this->post('/tramite/seleccionar', ['solicitud' => $ajena->id])->assertForbidden();
        $this->post('/tramite/seleccionar', ['solicitud' => $otroCorreo->id])->assertForbidden();
        $this->post('/tramite/seleccionar', ['solicitud' => $primera->id])->assertRedirect(route('portal.estado'));
        $this->get('/tramite/estado')->assertOk()->assertDontSee($primera->codigo);
        $this->travel(31)->minutes();
        $this->get('/tramite/mis-solicitudes')->assertForbidden();
        $this->post('/tramite/seleccionar', ['solicitud' => $primera->id])->assertForbidden();
    }

    public function test_datos_desconocidos_no_revelan_si_existen_solicitudes(): void
    {
        Mail::fake();
        $this->solicitud('12345678', 'demo@example.test');
        $this->post('/tramite/seguimiento', ['dni' => '87654321', 'email' => 'demo@example.test'])->assertRedirect(route('portal.codigo'));
        Mail::assertNothingSent();
        $this->post('/tramite/codigo', ['codigo' => '123456'])->assertSessionHasErrors('codigo');
    }
}
