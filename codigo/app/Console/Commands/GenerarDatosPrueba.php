<?php

namespace App\Console\Commands;

use App\Models\{Acta, PagoTramite, TramitePublico, User};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, File, Storage};
use Illuminate\Support\Str;

/**
 * Synthetic data for performance and scalability tests (AC01/AC03).
 * Refuses to run in production or on a database whose name does not say it is
 * for testing, so the working archive can never be filled with fake records.
 */
class GenerarDatosPrueba extends Command
{
    protected $signature = 'pruebas:generar-datos
        {--actas=10000 : Actas a crear}
        {--tramites=2000 : Solicitudes ciudadanas pagadas a crear}
        {--exportar=tests/carga/datos.json : Archivo con casos de búsqueda para k6}';

    protected $description = 'Genera actas y solicitudes sintéticas en una base de pruebas de carga.';

    private const NOMBRES = ['JUAN', 'MARIA', 'JOSE', 'ROSA', 'LUIS', 'CARMEN', 'CARLOS', 'ANA', 'JORGE', 'ELENA', 'PEDRO', 'LUCIA',
        'MIGUEL', 'SONIA', 'VICTOR', 'GLADYS', 'RAUL', 'NANCY', 'CESAR', 'MARTHA', 'DAVID', 'PILAR', 'HUGO', 'NORMA', 'IVAN', 'YOLANDA',
        'DIEGO', 'VALERIA', 'ANGEL', 'FIORELLA', 'SAUL', 'MILAGROS', 'EDWIN', 'KATHERINE', 'WILBER', 'ROCIO', 'FREDY', 'SANDRA'];
    private const APELLIDOS = ['QUISPE', 'HUAMAN', 'MAMANI', 'FLORES', 'CONDORI', 'GARCIA', 'ROJAS', 'SANCHEZ', 'CHAVEZ', 'RAMOS',
        'TORRES', 'MENDOZA', 'VARGAS', 'CASTRO', 'GUTIERREZ', 'PALOMINO', 'CCORAHUA', 'HUAYTA', 'BELITO', 'PARIONA', 'CANCHARI',
        'TAIPE', 'YUPANQUI', 'ALCA', 'CANCHO', 'SULCARAY', 'GARRIAZO', 'MALLQUI', 'ARONES', 'PRADO', 'LAPA', 'OCHOA', 'VILCA'];

    public function handle(): int
    {
        $base = (string) DB::connection()->getDatabaseName();
        if (app()->environment('production') || ! preg_match('/carga|prueba/i', $base)) {
            $this->error("Rechazado: solo se ejecuta fuera de producción y en una base de pruebas (actual: «{$base}»).");
            return self::FAILURE;
        }
        $nActas = max(1, (int) $this->option('actas'));
        $nTramites = max(0, (int) $this->option('tramites'));

        if (! User::query()->exists()) {
            $this->call('db:seed', ['--class' => \Database\Seeders\RoleSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => \Database\Seeders\UserSeeder::class, '--force' => true]);
        }
        $usuarioId = User::query()->value('id');

        // One small PDF shared by every synthetic act: the search checks that the file exists.
        $pdf = 'private/carga/acta-sintetica.pdf';
        Storage::disk('local')->put($pdf, "%PDF-1.4\n% acta sintetica para pruebas de carga\n%%EOF\n");

        $tipos = [Acta::TIPO_NACIMIENTO, Acta::TIPO_MATRIMONIO, Acta::TIPO_DEFUNCION];
        $orden = []; $correlativo = []; $casos = [];
        foreach ($tipos as $t) {
            $orden[$t] = (int) DB::table('actas')->where('tipo', $t)->max('orden_tipo');
            $correlativo[$t] = (int) DB::table('actas')->where('tipo', $t)->count();
        }
        $siguienteId = (int) DB::table('actas')->max('id') + 1;
        $barra = $this->output->createProgressBar($nActas);
        $this->info("Creando {$nActas} actas en «{$base}»…");

        for ($inicio = 0; $inicio < $nActas; $inicio += 1000) {
            $actas = []; $detalles = [Acta::TIPO_NACIMIENTO => [], Acta::TIPO_MATRIMONIO => [], Acta::TIPO_DEFUNCION => []];
            $ahora = now()->toDateTimeString();
            for ($i = $inicio; $i < min($nActas, $inicio + 1000); $i++) {
                $id = $siguienteId++;
                $tipo = $tipos[$i % 3];
                $n = $correlativo[$tipo]++;
                $fecha = date('Y-m-d', mt_rand(strtotime('1950-01-01'), strtotime('2025-12-31')));
                $nombre = $this->nombre();
                $actas[] = ['id' => $id, 'numero_acta' => 'S'.str_pad((string) $id, 8, '0', STR_PAD_LEFT), 'anio' => (int) substr($fecha, 0, 4),
                    'tipo' => $tipo, 'fecha_acta' => $fecha, 'sobre' => 'S'.intdiv($n, 250), 'correlativo' => $n % 250 + 1,
                    'usuario_id' => $usuarioId, 'estado' => Acta::ESTADO_REGISTRADA, 'pdf_original' => 'sintetica.pdf', 'pdf_ruta' => $pdf,
                    'pdf_hash' => hash('sha256', 'carga-'.$id.'-'.Str::random(8)), 'pdf_tamano' => 64, 'orden_tipo' => ++$orden[$tipo],
                    'created_at' => $ahora, 'updated_at' => $ahora];
                $dni = (string) mt_rand(10000000, 79999999);
                $detalles[$tipo][] = match ($tipo) {
                    Acta::TIPO_NACIMIENTO => ['acta_id' => $id, 'apellido_nombre' => $nombre, 'dni' => $dni, 'fecha_nacimiento' => $fecha, 'created_at' => $ahora, 'updated_at' => $ahora],
                    Acta::TIPO_MATRIMONIO => ['acta_id' => $id, 'apellido_nombre' => $nombre.' Y '.$this->nombre(), 'fecha_matrimonio' => $fecha, 'created_at' => $ahora, 'updated_at' => $ahora],
                    Acta::TIPO_DEFUNCION => ['acta_id' => $id, 'apellido_nombre' => $nombre, 'dni' => $dni, 'fecha_defuncion' => $fecha, 'causa_defuncion' => 'NO CONSIGNADA', 'created_at' => $ahora, 'updated_at' => $ahora],
                };
                if (count($casos) < 600 && mt_rand(1, 10) === 1) {
                    $casos[] = ['tipo' => $tipo, 'titular' => end($detalles[$tipo])['apellido_nombre'], 'fecha' => $fecha,
                        'numero' => end($actas)['numero_acta'], 'nombre' => explode(' ', $nombre)[0]];
                }
            }
            DB::transaction(function () use ($actas, $detalles) {
                DB::table('actas')->insert($actas);
                DB::table('acta_nacimientos')->insert($detalles[Acta::TIPO_NACIMIENTO]);
                DB::table('acta_matrimonios')->insert($detalles[Acta::TIPO_MATRIMONIO]);
                DB::table('acta_defunciones')->insert($detalles[Acta::TIPO_DEFUNCION]);
            });
            $barra->advance(count($actas));
        }
        $barra->finish();
        $this->newLine();
        foreach ($tipos as $t) DB::table('acta_secuencias')->updateOrInsert(['tipo' => $t], ['ultimo' => $orden[$t]]);

        $seguimiento = [];
        if ($nTramites > 0) {
            $this->info("Creando {$nTramites} solicitudes pagadas…");
            $actaIds = DB::table('actas')->where('pdf_ruta', $pdf)->inRandomOrder()->limit($nTramites)->pluck('id');
            $estados = ['PENDIENTE', 'PENDIENTE', 'OBSERVADA', 'APROBADA', 'LISTA', 'LISTA', 'RECHAZADA'];
            foreach ($actaIds as $k => $actaId) {
                $dni = (string) mt_rand(10000000, 79999999);
                $email = 'carga'.$k.'@example.test';
                $t = TramitePublico::create(['codigo' => (string) Str::uuid(), 'acta_id' => $actaId, 'estado' => $estados[$k % count($estados)],
                    'email' => $email, 'email_verificado_at' => now(), 'identidad_estado' => 'DATOS_CONTRASTADOS', 'terminos_version' => 'carga',
                    'aceptado_at' => now(), 'datos' => ['dni' => $dni, 'nombre' => $this->nombre(), 'relacion' => 'titular', 'motivo' => 'Prueba de carga',
                        'busqueda' => ['tipo' => 'NACIMIENTO', 'titular' => 'TITULAR SINTETICO', 'fecha' => '2000-01-01']]]);
                PagoTramite::create(['tramite_publico_id' => $t->id, 'proveedor' => 'MERCADOPAGO', 'importe_centimos' => 1030,
                    'estado' => 'PAGADO', 'idempotencia' => (string) Str::uuid(), 'pagado_at' => now()]);
                if (count($seguimiento) < 300) $seguimiento[] = ['dni' => $dni, 'email' => $email];
            }
        }

        $destino = base_path((string) $this->option('exportar'));
        File::ensureDirectoryExists(dirname($destino));
        File::put($destino, json_encode(['busquedas' => $casos, 'seguimiento' => $seguimiento], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info('Listo. Actas totales: '.DB::table('actas')->count().' · solicitudes: '.DB::table('tramites_publicos')->count()
            .' · casos exportados a '.$this->option('exportar'));
        return self::SUCCESS;
    }

    private function nombre(): string
    {
        $a = self::APELLIDOS;
        $n = self::NOMBRES;
        return $a[array_rand($a)].' '.$a[array_rand($a)].', '.$n[array_rand($n)].' '.$n[array_rand($n)];
    }
}
