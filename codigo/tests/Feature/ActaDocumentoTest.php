<?php
namespace Tests\Feature;

use App\Models\Acta;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_dni_exige_excepcion_y_la_guarda_solo_en_nacimiento_y_defuncion(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user=User::factory()->create();
        $user->roles()->attach(Role::where('slug',Role::ASISTENTE)->first());
        $this->actingAs($user);
        foreach(['NACIMIENTO'=>'fecha_nacimiento','DEFUNCION'=>'fecha_defuncion'] as $tipo=>$fecha) {
            $datos=['tipo'=>$tipo,'numero_acta'=>'SIN-DNI','fecha_acta'=>'2026-01-01','apellido_nombre'=>'Nombre del acta',$fecha=>'2025-12-31','edad'=>'1 día','causa_defuncion'=>'Certificada','dni_no_consignado'=>1];
            if ($tipo === 'NACIMIENTO') $datos += ['madre_nombre_estado' => 'NO_CONSIGNADO', 'madre_dni_estado' => 'NO_CONSIGNADO', 'padre_nombre_estado' => 'NO_CONSIGNADO', 'padre_dni_estado' => 'NO_CONSIGNADO'];
            $this->post('/actas',$datos+['dni'=>'12345678'])->assertSessionHasErrors('dni');
            $this->post('/actas',$datos+['pdf'=>UploadedFile::fake()->createWithContent('acta.pdf',"%PDF-1.4\n% {$tipo}\n%%EOF")])->assertSessionHasNoErrors();
            $acta=Acta::where('tipo',$tipo)->firstOrFail();
            $this->assertNull($acta->detalle()->dni);
            $this->assertTrue($acta->detalle()->dni_no_consignado);
            $this->get('/actas/'.$acta->id)->assertOk()->assertSee('No consignado en el acta');
        }
        $this->post('/actas',['tipo'=>'MATRIMONIO','numero_acta'=>'M','fecha_acta'=>'2026-01-01','apellido_nombre'=>'Ambos','fecha_matrimonio'=>'2025-12-31','dni_no_consignado'=>1])->assertSessionHasErrors(['dni','dni_no_consignado']);
    }

    public function test_reporta_los_datos_faltantes_ademas_del_pdf_en_los_tres_tipos(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user=User::factory()->create();
        $user->roles()->attach(Role::where('slug',Role::ASISTENTE)->first());
        foreach(['NACIMIENTO'=>'fecha_nacimiento','MATRIMONIO'=>'fecha_matrimonio','DEFUNCION'=>'fecha_defuncion'] as $tipo=>$fecha) {
            $campos=['apellido_nombre','dni',$fecha,'pdf'];
            if($tipo==='DEFUNCION'){ $campos[]='causa_defuncion'; $campos[]='edad'; }
            $this->actingAs($user)->post('/actas',['tipo'=>$tipo,'numero_acta'=>'SIN-DATOS','fecha_acta'=>'2026-01-01'])->assertSessionHasErrors($campos);
        }
        $this->assertSame(0,Acta::count());
    }

    public function test_registro_simple_de_los_tres_tipos_con_pdf(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', Role::ASISTENTE)->first());
        $this->actingAs($user)->get('/actas/create')->assertOk()
            ->assertDontSee('generar_despues')->assertDontSee('padre_prenombres');
        foreach (['NACIMIENTO' => 'fecha_nacimiento', 'MATRIMONIO' => 'fecha_matrimonio', 'DEFUNCION' => 'fecha_defuncion'] as $tipo => $fecha) {
            $datos = ['tipo' => $tipo, 'numero_acta' => $tipo, 'fecha_acta' => '2026-01-01',
                'sobre' => 'SIMPLE', 'correlativo' => 1, 'apellido_nombre' => 'NOMBRE DE PRUEBA',
                'dni' => '12345678', 'edad' => '83 años', $fecha => '2025-12-31', 'causa_defuncion' => 'CERTIFICADA', 'observaciones' => 'Observación del registro'];
            if ($tipo === 'NACIMIENTO') $datos += ['madre_nombre_estado' => 'NO_CONSIGNADO', 'madre_dni_estado' => 'NO_CONSIGNADO', 'padre_nombre_estado' => 'NO_CONSIGNADO', 'padre_dni_estado' => 'NO_CONSIGNADO'];
            $this->actingAs($user)->post('/actas', $datos + ['generar_despues' => 1])->assertSessionHasErrors('pdf');
            $contenido = "%PDF-1.4\n% $tipo\n%%EOF";
            $this->post('/actas', $datos + ['pdf' => UploadedFile::fake()->createWithContent('acta.pdf', $contenido)])
                ->assertSessionHasNoErrors()->assertRedirect();
            $acta = Acta::where('tipo', $tipo)->firstOrFail();
            $this->assertSame(Acta::ESTADO_REGISTRADA, $acta->estado);
            $this->assertSame(1, $acta->orden_tipo);
            $this->assertSame('1', $acta->sobre);
            $this->assertSame(1, $acta->correlativo);
            $this->assertSame('NOMBRE DE PRUEBA', $acta->detalle()->apellido_nombre);
            $this->get("/actas/{$acta->id}/pdf")->assertOk()->assertContent($contenido);
            $this->get("/actas/{$acta->id}")->assertOk()->assertDontSee('Generar documento');
            $this->get("/actas/{$acta->id}/generar")->assertNotFound();
            $this->get("/actas/{$acta->id}/completar-datos")->assertNotFound();
            $datos['numero_acta'] .= '-SEGUNDA';
            $datos['correlativo'] = 2;
            $datos['fecha_acta'] = '2025-12-31';
            $this->post('/actas', $datos + ['pdf' => UploadedFile::fake()->createWithContent('segunda.pdf', $contenido.' segunda')])
                ->assertSessionHasNoErrors();
            $this->assertSame(2, Acta::where('numero_acta', $datos['numero_acta'])->firstOrFail()->orden_tipo);
            for ($orden = 3; $orden <= 26; $orden++) {
                $nuevo = $acta->replicate();
                $nuevo->numero_acta = $tipo.'-'.$orden;
                $nuevo->pdf_hash = hash('sha256', $tipo.'-'.$orden);
                $nuevo->sobre = 'VALOR IGNORADO';
                $nuevo->correlativo = 24;
                $nuevo->save();
                $this->assertSame($orden, $nuevo->orden_tipo);
                $this->assertSame((string) (intdiv($orden - 1, 25) + 1), $nuevo->sobre);
                $this->assertSame((($orden - 1) % 25) + 1, $nuevo->correlativo);
            }
        }
    }
}
