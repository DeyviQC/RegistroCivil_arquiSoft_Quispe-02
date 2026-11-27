<?php

namespace Tests\Feature;

use App\Models\Acta;
use App\Models\ActaNacimiento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiliacionTest extends TestCase
{
    use RefreshDatabase;
    public function test_actualiza_solo_filiacion_con_historial_y_no_sobrescribe_version_antigua(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $acta = Acta::factory()->create();
        $nacimiento = ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Titular de Prueba', 'dni' => '12345678', 'fecha_nacimiento' => '2020-01-01']);
        $original = $acta->fresh()->getAttributes();
        $datos = ['filiacion_version' => 0, 'madre_nombre_estado' => 'REGISTRADO', 'madre_nombre' => 'Madre de Prueba', 'madre_dni_estado' => 'REGISTRADO', 'madre_dni' => '23456789', 'padre_nombre_estado' => 'REGISTRADO', 'padre_nombre' => 'Padre de Prueba', 'padre_dni_estado' => 'DESCONOCIDO', 'padre_dni' => '99999999', 'apellido_nombre' => 'No se permite cambiar', 'orden_tipo' => 999];
        $this->actingAs($usuario)->get('/actas/'.$acta->id.'/filiacion')->assertOk();
        $this->patch('/actas/'.$acta->id.'/filiacion', array_replace($datos, ['madre_dni' => '123']))->assertSessionHasErrors('madre_dni');
        $this->patch('/actas/'.$acta->id.'/filiacion', $datos)->assertSessionHasNoErrors()->assertRedirect('/actas/'.$acta->id);
        $nacimiento->refresh();
        $this->assertSame('Madre de Prueba', $nacimiento->madre_nombre);
        $this->assertNull($nacimiento->padre_dni);
        $this->assertSame('Titular de Prueba', $nacimiento->apellido_nombre);
        $this->assertEquals($original, $acta->fresh()->getAttributes());
        $this->assertDatabaseCount('filiacion_historial', 1);
        $this->assertDatabaseHas('auditoria', ['accion' => 'COMPLETAR_FILIACION', 'registro_id' => $acta->id]);
        $this->patch('/actas/'.$acta->id.'/filiacion', $datos)->assertSessionHasErrors('filiacion_version');
        $this->assertDatabaseCount('filiacion_historial', 1);
        $this->actingAs(User::factory()->create())->patch('/actas/'.$acta->id.'/filiacion', $datos)->assertForbidden();
    }
    public function test_nuevo_nacimiento_exige_revisar_los_cuatro_campos_de_padres(): void
    {
        $reglas = (new \App\Http\Requests\StoreNacimientoRequest)->rules();
        $errores = validator(['apellido_nombre' => 'Titular', 'dni' => '12345678', 'fecha_nacimiento' => '2020-01-01'], $reglas)->errors();
        foreach (['madre_nombre_estado','madre_dni_estado','padre_nombre_estado','padre_dni_estado'] as $campo) $this->assertTrue($errores->has($campo));
    }
}
