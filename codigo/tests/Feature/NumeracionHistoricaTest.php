<?php
namespace Tests\Feature;

use App\Models\Acta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumeracionHistoricaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sobre_historico_con_31_matrimonios_conserva_su_numero(): void
    {
        $usuario = User::factory()->create();
        $datos = ['tipo'=>'MATRIMONIO','numero_acta'=>'HIST-MAT','anio'=>2026,'fecha_acta'=>'2026-07-31','usuario_id'=>$usuario->id,'estado'=>'REGISTRADA'];
        $ultimo = new Acta($datos);
        $ultimo->asignarNumeracionHistorica(972,40,31);
        $ultimo->save();
        $nuevo = Acta::create(array_merge($datos,['numero_acta'=>'NUEVO-MAT']));
        $this->assertSame('40',$ultimo->sobre);
        $this->assertSame(31,$ultimo->correlativo);
        $this->assertSame(973,$nuevo->orden_tipo);
        $this->assertSame('41',$nuevo->sobre);
        $this->assertSame(1,$nuevo->correlativo);
    }

    public function test_historicos_conservan_orden_y_sobre_sin_retroceder_la_secuencia(): void
    {
        $usuario = User::factory()->create();
        $datos = ['tipo'=>'NACIMIENTO','numero_acta'=>'HIST-1','anio'=>2026,'fecha_acta'=>'2026-01-01','usuario_id'=>$usuario->id,'estado'=>'REGISTRADA'];
        $historico = new Acta($datos);
        $historico->asignarNumeracionHistorica(1573,53,22);
        $historico->save();
        $anterior = new Acta(array_merge($datos,['numero_acta'=>'HIST-ANTERIOR']));
        $anterior->asignarNumeracionHistorica(1500,52,1);
        $anterior->save();
        $nuevo = Acta::create(array_merge($datos,['numero_acta'=>'NUEVO']));
        $this->assertSame(1574,$nuevo->orden_tipo);
        $this->assertSame('53',$nuevo->sobre);
        $this->assertSame(23,$nuevo->correlativo);
        for($i=24;$i<=26;$i++) {
            $nuevo=Acta::create(array_merge($datos,['numero_acta'=>'NUEVO-'.$i]));
        }
        $this->assertSame('54',$nuevo->sobre);
        $this->assertSame(1,$nuevo->correlativo);
        $defuncion = Acta::create(array_merge($datos,['tipo'=>'DEFUNCION']));
        $this->assertSame(1,$defuncion->orden_tipo);
        $this->assertSame('1',$defuncion->sobre);
    }
}
