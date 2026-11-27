<?php

namespace Tests\Feature;

use App\Models\{User, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerfilInterfazTest extends TestCase
{
    use RefreshDatabase;

    private function empleado(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        return $user;
    }

    public function test_perfil_no_permite_escalar_permisos_ni_editar_otro_usuario(): void
    {
        $user = $this->empleado();
        $other = User::factory()->create();
        $original = $user->only(['username', 'email', 'password']);
        $this->actingAs($user)->put('/perfil', ['name' => 'Nombre de prueba', 'role' => 'administrador', 'is_active' => 0,
            'usuario_id' => $other->id, 'username' => 'nuevo', 'email' => 'cambio@example.test',
            'password' => 'ClaveAjena123', 'password_confirmation' => 'ClaveAjena123'])->assertRedirect('/perfil');
        $this->assertSame($original, $user->fresh()->only(['username', 'email', 'password']));
        $this->assertTrue($user->fresh()->is_active);
        $this->assertTrue($user->fresh()->isAsistente());
        $this->assertNotSame('Nombre de prueba', $other->fresh()->name);
        $this->get('/perfil')->assertOk()->assertSee('Nombre de prueba')->assertSee('El administrador le proporciona su usuario y contraseña.')
            ->assertDontSee('name="username"', false)->assertDontSee('name="password"', false);
    }

    public function test_foto_privada_solo_actualiza_el_perfil_propio_y_se_puede_quitar(): void
    {
        Storage::fake('local');
        $user = $this->empleado();
        $other = User::factory()->create();
        $other->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->actingAs($user)->put('/perfil/foto', ['foto' => UploadedFile::fake()->image('foto.png'), 'usuario_id' => $other->id])->assertRedirect('/perfil');
        $path = $user->fresh()->foto;
        Storage::disk('local')->assertExists($path);
        $this->assertNull($other->fresh()->foto);
        $this->get('/perfil/foto')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/usuarios/'.$user->id.'/foto')->assertForbidden();
        $this->actingAs($other)->get('/perfil/foto')->assertNotFound();
        $this->actingAs($user)->delete('/perfil/foto')->assertRedirect('/perfil');
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($user->fresh()->foto);
    }

    public function test_rechaza_archivos_invalidos_y_administrador_necesita_clave_actual(): void
    {
        Storage::fake('local');
        $user = $this->empleado();
        $this->actingAs($user)->put('/perfil/foto', ['foto' => UploadedFile::fake()->create('foto.php', 1, 'text/plain')])->assertSessionHasErrors('foto');
        $this->assertEmpty(Storage::disk('local')->allFiles());
        $user->roles()->sync([Role::where('slug', 'administrador')->firstOrFail()->id]);
        $user->unsetRelation('roles');
        $this->put('/perfil', ['name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123', 'current_password' => 'incorrecta'])->assertSessionHasErrors('current_password');
    }
}
