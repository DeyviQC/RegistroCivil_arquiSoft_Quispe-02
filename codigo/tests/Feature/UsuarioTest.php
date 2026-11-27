<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuarioConRol(string $slug): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::where('slug', $slug)->first());

        return $usuario;
    }

    public function test_asistente_no_puede_gestionar_usuarios(): void
    {
        $asistente = $this->crearUsuarioConRol(Role::ASISTENTE);

        $respuesta = $this->actingAs($asistente)->get('/usuarios');

        $respuesta->assertForbidden();
    }

    public function test_administrador_puede_gestionar_usuarios(): void
    {
        $admin = $this->crearUsuarioConRol(Role::ADMINISTRADOR);

        $respuesta = $this->actingAs($admin)->get('/usuarios');

        $respuesta->assertOk();
    }

    public function test_administrador_puede_crear_usuario(): void
    {
        $admin = $this->crearUsuarioConRol(Role::ADMINISTRADOR);

        $respuesta = $this->actingAs($admin)->post('/usuarios', [
            'name' => 'Nuevo Asistente',
            'username' => 'nuevo_asistente',
            'email' => 'nuevo@municipalidad.local',
            'password' => 'Clave12345!',
            'password_confirmation' => 'Clave12345!',
            'role' => 'asistente',
        ]);

        $respuesta->assertRedirect(route('usuarios.index'));
        $this->assertDatabaseHas('users', ['username' => 'nuevo_asistente']);
    }

    public function test_asistente_no_puede_acceder_a_auditoria(): void
    {
        $asistente = $this->crearUsuarioConRol(Role::ASISTENTE);

        $respuesta = $this->actingAs($asistente)->get('/auditoria');

        $respuesta->assertForbidden();
    }

    public function test_administrador_no_puede_desactivarse_ni_quitarse_el_rol_desde_editar(): void
    {
        $admin = $this->crearUsuarioConRol(Role::ADMINISTRADOR);
        $admin->update(['username' => 'admin_principal']);
        $datos = ['name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email];

        $this->actingAs($admin)->put('/usuarios/'.$admin->id, $datos + ['role' => 'asistente', 'is_active' => 0])
            ->assertSessionHasErrors(['role', 'is_active']);
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->isAdmin());

        $this->put('/usuarios/'.$admin->id, $datos + ['role' => 'administrador', 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));
    }

    public function test_administrador_puede_desactivar_a_otro_usuario_desde_editar(): void
    {
        $admin = $this->crearUsuarioConRol(Role::ADMINISTRADOR);
        $otro = User::factory()->create(['username' => 'asistente_dos']);
        $otro->roles()->attach(Role::where('slug', Role::ASISTENTE)->first());

        $this->actingAs($admin)->put('/usuarios/'.$otro->id, ['name' => $otro->name, 'username' => $otro->username,
            'email' => $otro->email, 'role' => 'asistente', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($otro->fresh()->is_active);
    }

    public function test_api_interna_acepta_la_sesion_web_del_personal(): void
    {
        $asistente = $this->crearUsuarioConRol(Role::ASISTENTE);

        $this->getJson('/api/actas')->assertUnauthorized();
        $this->actingAs($asistente)->getJson('/api/actas')->assertOk()->assertJsonStructure(['data']);
    }
}
