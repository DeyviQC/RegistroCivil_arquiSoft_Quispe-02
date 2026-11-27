<?php

namespace Tests\Feature;

use App\Models\Acta;
use App\Models\Auditoria;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_el_login_queda_registrado_en_auditoria(): void
    {
        $usuario = User::factory()->create(['username' => 'admin', 'password' => bcrypt('Admin12345!')]);
        $usuario->roles()->attach(Role::where('slug', Role::ADMINISTRADOR)->first());

        $this->post('/login', ['username' => 'admin', 'password' => 'Admin12345!']);

        $this->assertDatabaseHas('auditoria', ['accion' => 'LOGIN', 'usuario_id' => $usuario->id]);
    }

    public function test_crear_acta_queda_registrado_en_auditoria(): void
    {
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::where('slug', Role::ASISTENTE)->first());

        $pdf = UploadedFile::fake()->createWithContent('acta.pdf', "%PDF-1.4\n%%EOF");

        $this->actingAs($usuario)->post('/actas', [
            'tipo' => Acta::TIPO_NACIMIENTO,
            'madre_nombre_estado' => 'NO_CONSIGNADO', 'madre_dni_estado' => 'NO_CONSIGNADO', 'padre_nombre_estado' => 'NO_CONSIGNADO', 'padre_dni_estado' => 'NO_CONSIGNADO',
            'numero_acta' => '00777',
            'anio' => 2026,
            'fecha_acta' => '2026-01-10',
            'apellido_nombre' => 'Ríos Vega Ana',
            'dni' => '12345678',
            'apellido_paterno' => 'Ríos',
            'apellido_materno' => 'Vega',
            'fecha_nacimiento' => '2026-01-10',
            'lugar_nacimiento' => 'Hospital Municipal',
            'sexo' => 'F',
            'pdf' => $pdf,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('auditoria', ['accion' => 'CREAR_ACTA', 'usuario_id' => $usuario->id]);
    }

    public function test_solo_administrador_puede_ver_la_auditoria(): void
    {
        Auditoria::factory()->count(3)->create();

        $asistente = User::factory()->create();
        $asistente->roles()->attach(Role::where('slug', Role::ASISTENTE)->first());

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', Role::ADMINISTRADOR)->first());

        $this->actingAs($asistente)->get('/auditoria')->assertForbidden();
        $this->actingAs($admin)->get('/auditoria')->assertOk();
    }
}
