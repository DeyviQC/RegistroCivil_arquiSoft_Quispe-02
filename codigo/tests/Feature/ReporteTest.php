<?php
namespace Tests\Feature;
use App\Models\Acta;
use App\Models\Auditoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;
    private function usuario(string $rol): User
    {
        $user=User::factory()->create();
        $user->roles()->attach(Role::where('slug',$rol)->firstOrFail());
        return $user;
    }
    public function test_filtros_resumen_pendientes_y_exportaciones(): void
    {
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $admin=$this->usuario(Role::ADMINISTRADOR);
        $this->actingAs($admin);
        foreach(['NACIMIENTO','MATRIMONIO','DEFUNCION'] as $tipo){
            $acta=Acta::create(['tipo'=>$tipo,'numero_acta'=>'001','anio'=>2026,'fecha_acta'=>'2026-01-15','usuario_id'=>$admin->id,'estado'=>'REGISTRADA','pdf_ruta'=>$tipo.'.pdf','pdf_tamano'=>100,'pdf_hash'=>hash('sha256',$tipo)]);
            Storage::disk('local')->put($tipo.'.pdf','%PDF-1.4 test');
            $date=match($tipo){'NACIMIENTO'=>'fecha_nacimiento','MATRIMONIO'=>'fecha_matrimonio','DEFUNCION'=>'fecha_defuncion'};
            $acta->{strtolower($tipo)}()->create(['apellido_nombre'=>'Nombre '.$tipo,'dni'=>'01234567',$date=>'2026-01-01',...($tipo==='DEFUNCION'?['causa_defuncion'=>'Certificada']:[])]);
        }
        $this->get('/reportes?tipo=NACIMIENTO')->assertOk()->assertViewHas('total',1)->assertSee('Nombre NACIMIENTO')->assertDontSee('Nombre MATRIMONIO');
        $this->get('/reportes?reporte=resumen&anio=2026&mes=1')->assertOk()->assertViewHas('total',3)->assertViewHas('filas',fn($rows)=>array_sum(array_column($rows,1))===3);
        $this->get('/reportes?reporte=anual&anio=2026')->assertOk()->assertViewHas('total',3)->assertViewHas('metricas', ['total'=>3,'con'=>3,'sin'=>0]);
        $this->get('/reportes?reporte=resumen&anio=2025&mes=1')->assertOk()->assertViewHas('total',3)->assertViewHas('metricas', ['total'=>0,'con'=>0,'sin'=>0]);
        $this->get('/reportes?reporte=resumen')->assertSessionHasErrors('anio');
        $this->get('/reportes?reporte=resumen&anio=2026')->assertSessionHasErrors('mes');
        $this->get('/reportes?reporte=resumen&anio=2026&mes=2')->assertOk()->assertViewHas('metricas', ['total'=>0,'con'=>0,'sin'=>0]);
        $this->get('/reportes?reporte=anual&anio=2026&mes=1')->assertSessionHasErrors('mes');
        $this->get('/reportes?reporte=actas&mes=1')->assertSessionHasErrors('mes');
        $this->get('/reportes?reporte=pendientes&mes=1')->assertSessionHasErrors('mes');
        foreach (['actas','pendientes','actividad'] as $tipoReporte) {
            foreach (['','/excel','/pdf'] as $extension) {
                $this->get('/reportes'.$extension.'?reporte='.$tipoReporte.'&anio=2026')->assertSessionHasErrors('anio');
            }
        }
        $this->get('/reportes/pdf?reporte=resumen&anio=2026&mes=1')->assertOk();
        foreach(['','/excel','/pdf'] as $extension) {
            $this->get('/reportes'.$extension.'?reporte=anual&anio=2026&sobre=1')->assertSessionHasErrors('sobre');
            $this->get('/reportes'.$extension.'?reporte=resumen&anio=2026&mes=1&fecha_desde=2026-01-01')->assertSessionHasErrors('fecha_desde');
            $this->get('/reportes'.$extension.'?sobre=1')->assertSessionHasErrors('sobre');
            $this->get('/reportes'.$extension.'?tipo=NACIMIENTO&correlativo=1')->assertSessionHasErrors('correlativo');
            $this->get('/reportes'.$extension.'?anio=2026&fecha_desde=2026-01-01')->assertSessionHasErrors('anio');
            $this->get('/reportes'.$extension.'?reporte=actividad&tipo=NACIMIENTO')->assertSessionHasErrors('tipo');
        }
        $this->get('/reportes?tipo=NACIMIENTO&sobre=1&correlativo=1')->assertSessionHasErrors(['sobre','correlativo']);
        $this->get('/reportes?reporte=anual&anio=2026&tipo=NACIMIENTO')->assertOk()->assertViewHas('metricas', ['total'=>1,'con'=>1,'sin'=>0]);
        $this->get('/reportes/pdf?reporte=anual&anio=2026')->assertOk();
        Storage::disk('local')->delete('DEFUNCION.pdf');
        $this->get('/reportes?reporte=pendientes')->assertOk()->assertViewHas('total',1)->assertSee('Nombre DEFUNCION');
        $this->get('/reportes?fecha_hasta=2025-12-31')->assertOk()->assertViewHas('total',0);
        $this->get('/reportes?fecha_desde=2026-02-01&fecha_hasta=2026-01-01')->assertSessionHasErrors('fecha_hasta');
        $excel=$this->get('/reportes/excel?tipo=NACIMIENTO')->assertOk();
        $zip=new \ZipArchive();$this->assertTrue($zip->open($excel->baseResponse->getFile()->getPathname())===true);
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('01234567',$sheet);$this->assertStringNotContainsString('Nombre MATRIMONIO',$sheet);$zip->close();
        $pdf=$this->get('/reportes/pdf?tipo=NACIMIENTO')->assertOk();$this->assertStringStartsWith('%PDF-',$pdf->getContent());
        Auditoria::create(['usuario_id'=>$admin->id,'accion'=>'IMPORTACION_MASIVA','modulo'=>'Importación','descripcion'=>'3 actas importadas']);
        $this->get('/reportes?reporte=sobres')->assertOk()->assertViewHas('total',3);
        $base = Acta::where('tipo','NACIMIENTO')->firstOrFail();
        for ($i=2; $i<=26; $i++) {
            $nuevo=$base->replicate();
            $nuevo->numero_acta='SOBRE-'.$i;
            $nuevo->pdf_hash=hash('sha256','sobre-'.$i);
            $nuevo->pdf_ruta=null;
            $nuevo->pdf_tamano=0;
            $nuevo->save();
        }
        $this->get('/reportes?reporte=sobres&tipo=NACIMIENTO')->assertOk()->assertViewHas('total',2)
            ->assertViewHas('filas',fn($rows)=>$rows[0]===['NACIMIENTO','1',25,1,24,0,'Completo'] && $rows[1]===['NACIMIENTO','2',1,0,1,24,'Disponible']);
        $this->get('/reportes?reporte=sobres&fecha_desde=2026-01-01')->assertSessionHasErrors('fecha_desde');
        $this->get('/reportes?reporte=sobres&usuario_id='.$admin->id)->assertSessionHasErrors('usuario_id');
        $this->get('/reportes/pdf?reporte=sobres')->assertOk();
        $this->get('/reportes/excel?reporte=sobres')->assertOk();
        $this->get('/reportes?reporte=sobres&tipo=NACIMIENTO')->assertOk()
            ->assertSee(route('actas.index',['tipo'=>'NACIMIENTO','sobre'=>'1']));
        $this->get('/actas?tipo=NACIMIENTO&sobre=1')->assertOk()
            ->assertViewHas('actas',fn($actas)=>$actas->total()===25 && $actas->first()->correlativo===1)
            ->assertSee('Volver a sobres');
        $this->get('/actas?tipo=NACIMIENTO&sobre=2')->assertOk()
            ->assertViewHas('actas',fn($actas)=>$actas->total()===1);
        $asistente=$this->usuario(Role::ASISTENTE);$this->actingAs($asistente);
        $this->get('/reportes')->assertOk();
        foreach(['actividad'] as $reporte){foreach(['','/pdf','/excel'] as $extension){$this->get('/reportes'.$extension.'?reporte='.$reporte)->assertForbidden();}}
    }
}
