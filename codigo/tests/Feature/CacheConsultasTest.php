<?php

namespace Tests\Feature;

use App\Models\{Acta, ActaNacimiento, PagoTramite, Role, TramitePublico, User};
use App\Services\{ActaService, CacheConsultas, DashboardService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

/** Sprint 2 — análisis de base de datos y caché (sprint-2/03-base-de-datos-y-cache.md). */
class CacheConsultasTest extends TestCase
{
    use RefreshDatabase;

    private function tramitePagado(string $estado = 'PENDIENTE'): TramitePublico
    {
        $acta = Acta::factory()->create();
        ActaNacimiento::create(['acta_id' => $acta->id, 'apellido_nombre' => 'Titular Adulto', 'fecha_nacimiento' => '1990-01-01']);
        $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $acta->id, 'estado' => $estado,
            'email' => 'ciudadano@example.test', 'email_verificado_at' => now(), 'identidad_estado' => 'DATOS_CONTRASTADOS',
            'datos' => ['relacion' => 'titular'], 'terminos_version' => 'test', 'aceptado_at' => now()]);
        PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO', 'importe_centimos' => 1030,
            'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid()]);
        return $t->fresh();
    }

    private function asistente(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $u = User::factory()->create();
        $u->roles()->attach(Role::where('slug', 'asistente')->firstOrFail());
        return $u;
    }

    public function test_el_panel_se_sirve_de_cache_y_se_actualiza_al_registrar_un_acta(): void
    {
        Acta::factory()->count(2)->create();
        $panel = app(DashboardService::class);
        $this->assertSame(2, $panel->estadisticas()['total']);

        DB::enableQueryLog();
        $panel->estadisticas();
        $panel->actasPorAnio();
        $conteos = collect(DB::getQueryLog())->filter(fn ($q) => str_contains(strtolower($q['query']), 'count('));
        $this->assertCount(0, $conteos, 'La segunda visita no debe volver a contar el archivo.');

        Acta::factory()->matrimonio()->create();
        $this->assertSame(3, $panel->estadisticas()['total']);
        $this->assertSame(1, $panel->estadisticas()['matrimonios']);
    }

    public function test_el_resumen_de_la_bandeja_se_invalida_en_cada_cambio_de_estado(): void
    {
        Mail::fake();
        $t = $this->tramitePagado();
        $this->actingAs($this->asistente())->get(route('atencion.index'))->assertOk();
        $this->assertSame(['PENDIENTE' => 1], Cache::get(CacheConsultas::BANDEJA));

        $t->transicionar('OBSERVADA', 'Adjunte su DNI.', null);
        $this->assertFalse(Cache::has(CacheConsultas::BANDEJA));

        $this->get(route('atencion.index'))->assertOk();
        $this->assertSame(['OBSERVADA' => 1], Cache::get(CacheConsultas::BANDEJA));
    }

    public function test_la_invalidacion_espera_al_commit_y_un_rollback_no_borra_el_cache(): void
    {
        Mail::fake();
        $t = $this->tramitePagado();
        CacheConsultas::recordar(CacheConsultas::BANDEJA, fn () => ['PENDIENTE' => 1]);

        try {
            DB::transaction(function () use ($t) {
                $t->transicionar('OBSERVADA', 'Adjunte su DNI.', null);
                throw new \RuntimeException('falla simulada');
            });
        } catch (\RuntimeException) {
        }
        $this->assertTrue(Cache::has(CacheConsultas::BANDEJA), 'Nada cambió: el resumen sigue siendo válido.');

        DB::transaction(function () use ($t) {
            $t->fresh()->transicionar('OBSERVADA', 'Adjunte su DNI.', null);
            $this->assertTrue(Cache::has(CacheConsultas::BANDEJA), 'Antes del commit otro usuario aún ve el dato confirmado.');
        });
        $this->assertFalse(Cache::has(CacheConsultas::BANDEJA));
    }

    public function test_el_filtro_por_fechas_del_archivo_incluye_ambos_extremos(): void
    {
        foreach (['2019-12-31', '2020-01-01', '2020-12-31', '2021-01-01'] as $fecha) {
            Acta::factory()->create(['fecha_acta' => $fecha]);
        }
        $pagina = app(ActaService::class)->buscar(['fecha_desde' => '2020-01-01', 'fecha_hasta' => '2020-12-31']);
        $this->assertSame(['2020-12-31', '2020-01-01'], collect($pagina->items())->map(fn ($a) => $a->fecha_acta->toDateString())->all());
    }
}
