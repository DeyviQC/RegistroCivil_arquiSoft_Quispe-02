<?php

namespace Database\Seeders;

use App\Models\Acta;
use App\Models\User;
use App\Services\ActaService;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActasPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::where('is_active', true)->firstOrFail();
        $archivosNuevos = [];
        try {
            DB::transaction(function () use ($usuario, &$archivosNuevos) {
                DB::table('actas')->delete();
                DB::table('acta_secuencias')->update(['ultimo' => 0]);
                app(AuditoriaService::class)->registrar(
                    'REEMPLAZAR_PRUEBAS', 'Registro Civil', null,
                    'Actas reemplazadas por 35 defunciones, 35 nacimientos y 35 matrimonios ficticios.', $usuario->id
                );
                $tipos = array_merge(array_fill(0, 35, 'DEFUNCION'), array_fill(0, 35, 'NACIMIENTO'), array_fill(0, 35, 'MATRIMONIO'));
                shuffle($tipos);
                $contadores = array_fill_keys(['DEFUNCION', 'NACIMIENTO', 'MATRIMONIO'], 0);
                $faker = \Faker\Factory::create('es_PE');
                foreach ($tipos as $posicion => $tipo) {
                    {
                        $i = ++$contadores[$tipo];
                        $numero = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                        $persona = fn () => $faker->lastName().' '.$faker->lastName().' '.$faker->firstName().' '.$faker->firstName();
                        $nombre = $tipo === 'MATRIMONIO' ? $persona().' / '.$persona() : $persona();
                        $documento = fn () => (string) $faker->unique()->numberBetween(10000000, 99999999);
                        $dni = $tipo === 'MATRIMONIO' ? $documento().' / '.$documento() : $documento();
                        $fecha = \Carbon\Carbon::parse('2026-06-01')->addDays($posicion)->format('Y-m-d');
                        $fechaEvento = \Carbon\Carbon::parse($fecha)->subDays($faker->numberBetween(0, 5))->format('Y-m-d');
                        $causas = ['Paro cardiorrespiratorio', 'Insuficiencia respiratoria', 'Causas naturales', 'Insuficiencia cardíaca', 'Falla multiorgánica'];
                        $causa = $causas[$faker->numberBetween(0, count($causas) - 1)];
                        $campoFecha = match ($tipo) {
                            'NACIMIENTO' => 'fecha_nacimiento',
                            'DEFUNCION' => 'fecha_defuncion',
                            'MATRIMONIO' => 'fecha_matrimonio',
                        };
                        $contenido = Pdf::loadHTML('<html><meta charset="utf-8"><body><h1>DOCUMENTO DE PRUEBA</h1>'
                            ."<p>Datos ficticios. Sin validez oficial.</p><p>Tipo: {$tipo}</p><p>Registro: {$numero}</p>"
                            ."<p>Nombre: {$nombre}</p><p>DNI: {$dni}</p><p>Fecha del evento: {$fechaEvento}</p><p>Fecha de registro: {$fecha}</p></body></html>")->setPaper('a4')->output();
                        $temporal = tempnam(sys_get_temp_dir(), 'acta_prueba_');
                        try {
                            file_put_contents($temporal, $contenido);
                            $acta = app(ActaService::class)->registrar([
                                'tipo' => $tipo, 'numero_acta' => $numero, 'anio' => 2026,
                                'fecha_acta' => $fecha, 'sobre' => (string) (intdiv($i - 1, 25) + 1),
                                'correlativo' => (($i - 1) % 25) + 1, 'observaciones' => 'Registro ficticio para pruebas.',
                            ], [
                                'apellido_nombre' => $nombre, 'dni' => $dni, $campoFecha => $fechaEvento,
                                ...($tipo === 'DEFUNCION' ? ['causa_defuncion' => $causa] : []),
                            ], new UploadedFile($temporal, $numero.'.pdf', 'application/pdf', null, true), $usuario);
                            $creado = \Carbon\Carbon::parse($fecha.' 09:00:00')->addMinutes($faker->numberBetween(0, 420));
                            $acta->timestamps = false;
                            $acta->forceFill(['created_at' => $creado, 'updated_at' => $creado])->save();
                            $archivosNuevos[] = $acta->pdf_ruta;
                        } finally {
                            if (is_file($temporal)) {
                                unlink($temporal);
                            }
                        }
                    }
                }
                foreach (['DEFUNCION' => 35, 'NACIMIENTO' => 35, 'MATRIMONIO' => 35] as $tipo => $cantidad) {
                    if (Acta::where('tipo', $tipo)->count() !== $cantidad
                        || Acta::where('tipo', $tipo)->max('orden_tipo') !== $cantidad) {
                        throw new \RuntimeException('La carga de pruebas no coincide con la cantidad solicitada.');
                    }
                }
            });
        } catch (\Throwable $error) {
            foreach ($archivosNuevos as $ruta) {
                Storage::disk('local')->delete($ruta);
            }
            throw $error;
        }
        $this->command?->info('Creadas 105 actas de prueba: 35 defunciones, 35 nacimientos y 35 matrimonios.');
    }
}
