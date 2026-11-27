<?php

namespace Tests\Feature;

use App\Models\{Acta, ActaNacimiento, PagoTramite, Role, TramitePublico, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash, Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

/** REST API v1: authentication, archive, citizen requests, decisions and public availability. */
class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private function personal(string $rol = 'asistente', array $extra = []): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $usuario = User::factory()->create($extra + ['username' => $rol.'.api', 'password' => Hash::make('Clave-Segura-123')]);
        $usuario->roles()->attach(Role::where('slug', $rol)->firstOrFail());

        return $usuario;
    }

    private function token(User $usuario): string
    {
        return $this->postJson('/api/v1/auth/token', ['username' => $usuario->username, 'password' => 'Clave-Segura-123'])
            ->assertCreated()->json('token');
    }

    private function tramite(string $estado = 'PENDIENTE', bool $pagado = true): TramitePublico
    {
        $acta = Acta::factory()->create();
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Titular Adulto', 'fecha_nacimiento' => '1990-01-01']);
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $acta->id, 'estado' => $estado,
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(),
            'datos' => ['relacion' => 'titular', 'nombre' => 'Solicitante Ficticio', 'dni' => '12345678',
                'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'Titular Adulto']],
            'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO', 'importe_centimos' => 1030,
            'estado' => $pagado ? 'PAGADO' : 'CREANDO', 'idempotencia' => (string) Str::uuid()]);

        return $t->fresh();
    }

    public function test_salud_es_publica_y_reporta_sus_componentes(): void
    {
        $this->getJson('/api/v1/salud')->assertOk()
            ->assertJson(['estado' => 'ok', 'componentes' => ['base_datos' => 'ok', 'almacenamiento' => 'ok']]);
    }

    public function test_el_token_se_emite_solo_al_personal_activo_y_caduca(): void
    {
        $usuario = $this->personal();
        $r = $this->postJson('/api/v1/auth/token', ['username' => $usuario->username, 'password' => 'Clave-Segura-123', 'dispositivo' => 'postman'])
            ->assertCreated()->assertJsonStructure(['token', 'tipo', 'expira_en', 'usuario' => ['id', 'nombre', 'username', 'rol']])
            ->assertJsonPath('tipo', 'Bearer')->assertJsonPath('usuario.rol', 'asistente');
        $this->assertNotNull($usuario->tokens()->first()->expires_at);
        $this->assertDatabaseHas('auditoria', ['accion' => 'LOGIN', 'modulo' => 'API REST', 'usuario_id' => $usuario->id]);
        $this->assertStringNotContainsString('password', $r->getContent());

        $this->postJson('/api/v1/auth/token', ['username' => $usuario->username, 'password' => 'incorrecta'])->assertUnauthorized();
        $this->postJson('/api/v1/auth/token', [])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);

        $inactivo = User::factory()->create(['username' => 'inactivo.api', 'password' => Hash::make('Clave-Segura-123'), 'is_active' => false]);
        $inactivo->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        $this->postJson('/api/v1/auth/token', ['username' => 'inactivo.api', 'password' => 'Clave-Segura-123'])->assertForbidden();
        User::factory()->create(['username' => 'sinrol.api', 'password' => Hash::make('Clave-Segura-123')]);
        $this->postJson('/api/v1/auth/token', ['username' => 'sinrol.api', 'password' => 'Clave-Segura-123'])->assertForbidden();
    }

    public function test_cinco_intentos_fallidos_bloquean_el_usuario(): void
    {
        $usuario = $this->personal();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/token', ['username' => $usuario->username, 'password' => 'incorrecta'])->assertUnauthorized();
        }
        $this->postJson('/api/v1/auth/token', ['username' => $usuario->username, 'password' => 'Clave-Segura-123'])->assertStatus(429);
    }

    public function test_sin_token_o_con_token_revocado_o_vencido_no_hay_acceso(): void
    {
        foreach (['/api/v1/auth/yo', '/api/v1/actas', '/api/v1/tramites'] as $ruta) {
            $this->getJson($ruta)->assertUnauthorized();
        }
        // Even without the Accept header the API answers JSON, never a redirect to the login page.
        $this->get('/api/v1/actas')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');

        $token = $this->token($this->personal());
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertOk()->assertJsonPath('usuario.rol', 'asistente');
        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/yo')->assertUnauthorized();

        $otro = $this->token(User::where('username', 'asistente.api')->first());
        $this->app['auth']->forgetGuards();
        $this->travel(9)->hours();
        $this->withToken($otro)->getJson('/api/v1/auth/yo')->assertUnauthorized();
    }

    public function test_actas_se_buscan_y_consultan_sin_exponer_el_pdf(): void
    {
        $token = $this->token($this->personal('administrador'));
        $acta = Acta::factory()->create(['numero_acta' => '00777', 'pdf_ruta' => 'private/actas/secreto.pdf']);
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Persona Buscada', 'fecha_nacimiento' => '2000-01-01']);
        Acta::factory()->create();

        $lista = $this->withToken($token)->getJson('/api/v1/actas?nombre=Buscada')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.numero_acta', '00777')->assertJsonPath('data.0.titular', 'Persona Buscada')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
        $this->assertStringNotContainsString('secreto.pdf', $lista->getContent());

        $this->withToken($token)->getJson("/api/v1/actas/{$acta->id}")->assertOk()
            ->assertJsonPath('data.pdf_disponible', true)->assertJsonPath('data.copia_firmada.vigente', false);
        $this->withToken($token)->getJson('/api/v1/actas/999999')->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/actas?tipo=OTRO')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    }

    public function test_tramites_solo_pagados_y_sin_datos_privados(): void
    {
        $token = $this->token($this->personal());
        $pagado = $this->tramite();
        $this->tramite('PENDIENTE_PAGO', false);

        $lista = $this->withToken($token)->getJson('/api/v1/tramites')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.numero', str_pad((string) $pagado->id, 6, '0', STR_PAD_LEFT));
        foreach ([$pagado->codigo, 'ciudadano@example.test', '12345678'] as $privado) {
            $this->assertStringNotContainsString($privado, $lista->getContent());
        }
        $this->withToken($token)->getJson('/api/v1/tramites?dni=12345678')->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/v1/tramites?dni=87654321')->assertJsonCount(0, 'data');

        $this->withToken($token)->getJson("/api/v1/tramites/{$pagado->id}")->assertOk()
            ->assertJsonPath('data.transiciones_permitidas', ['OBSERVADA', 'APROBADA', 'RECHAZADA'])
            ->assertJsonStructure(['data' => ['eventos', 'requiere_revision_vinculo', 'version']]);
        $noPagado = TramitePublico::where('estado', 'PENDIENTE_PAGO')->first();
        $this->withToken($token)->getJson("/api/v1/tramites/{$noPagado->id}")->assertNotFound();
    }

    public function test_decidir_por_la_api_usa_la_misma_regla_que_el_panel(): void
    {
        Mail::fake();
        Storage::fake('local');
        $usuario = $this->personal();
        $token = $this->token($usuario);
        $t = $this->tramite();

        $this->withToken($token)->postJson("/api/v1/tramites/{$t->id}/decision", ['estado' => 'OBSERVADA', 'version' => $t->version])
            ->assertUnprocessable()->assertJsonValidationErrors('mensaje');
        $this->withToken($token)->postJson("/api/v1/tramites/{$t->id}/decision", ['estado' => 'LISTA', 'version' => $t->version])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');

        $this->withToken($token)->postJson("/api/v1/tramites/{$t->id}/decision", ['estado' => 'APROBADA', 'version' => $t->version])
            ->assertOk()->assertJsonPath('data.estado', 'APROBADA')->assertJsonPath('data.version', $t->version + 1);
        $this->assertDatabaseHas('auditoria', ['accion' => 'REVISAR_TRAMITE', 'registro_id' => $t->id, 'usuario_id' => $usuario->id]);

        // Same version again: someone already decided, so it is a conflict and nothing changes.
        $this->withToken($token)->postJson("/api/v1/tramites/{$t->id}/decision", ['estado' => 'RECHAZADA', 'mensaje' => 'Motivo de prueba.', 'version' => $t->version])
            ->assertStatus(409)->assertJsonPath('errors.version.0', 'La solicitud cambió. Recarga antes de decidir.');
        $this->assertSame('APROBADA', $t->fresh()->estado);
        $this->assertSame(1, $t->eventos()->count());
    }

    public function test_consulta_publica_de_disponibilidad_no_revela_el_archivo(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/actas/acta.pdf', '%PDF-1.4');
        $acta = Acta::factory()->create(['pdf_ruta' => 'private/actas/acta.pdf']);
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Persona Disponible', 'fecha_nacimiento' => '2001-02-03']);

        $si = $this->postJson('/api/v1/consultas/disponibilidad', ['tipo' => 'NACIMIENTO', 'titular' => 'disponible persona', 'fecha' => '2001-02-03'])
            ->assertOk()->assertJsonPath('disponible', true);
        $this->assertStringNotContainsString((string) $acta->numero_acta, $si->getContent());
        $this->postJson('/api/v1/consultas/disponibilidad', ['tipo' => 'NACIMIENTO', 'titular' => 'Otra Persona', 'fecha' => '2001-02-03'])
            ->assertOk()->assertJsonPath('disponible', false);
        $this->postJson('/api/v1/consultas/disponibilidad', ['tipo' => 'X'])->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo', 'titular', 'fecha']);
    }
}
