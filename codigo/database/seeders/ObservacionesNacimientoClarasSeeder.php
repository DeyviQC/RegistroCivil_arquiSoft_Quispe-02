<?php
namespace Database\Seeders;

use App\Models\Acta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ObservacionesNacimientoClarasSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = storage_path('app/private/importaciones/sobre53-2026/registros.json');
        $rows = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use (&$rows) {
            foreach ($rows as &$r) {
                $lineas = [];
                if (preg_match('/corregido de (\d+) a (\d+)/u', $r['observaciones'], $m)) {
                    $lineas[] = 'Número de orden corregido: en la lista figuraba '.$m[1].'; se registró '.$m[2].' para continuar la secuencia, según su indicación.';
                }
                if ($r['dni'] === 'SIN CUI') {
                    $lineas[] = 'La lista indica SIN CUI: no se asignó un número de documento.';
                    $lineas[] = 'El nacimiento ocurrió en 1954, pero se inscribió en 2026. Las dos fechas se conservaron.';
                }
                if (preg_match('/Sección del índice: ([A-Z]+)\./u', $r['observaciones'], $m)) {
                    $mes = ucfirst(strtolower($m[1]));
                    $lineas[] = 'Este registro corresponde a la sección de '.$mes.' de 2026 en la lista.';
                    if (in_array($m[1], ['ABRIL', 'MAYO', 'JUNIO'])) {
                        $lineas[] = 'La etiqueta del sobre en la lista dice MARZO, aunque el registro está en la sección de '.$mes.'. Se mantuvo el sobre 53 y las fechas del documento.';
                    }
                }
                $lineas[] = 'Documento de origen: sobre 53, página '.$r['source_page'].' del PDF de nacimientos de 2026.';
                $texto = implode("\n", $lineas);
                $acta = Acta::where('tipo', 'NACIMIENTO')->where('numero_acta', $r['numero_acta'])->where('anio', 2026)->firstOrFail();
                if ($acta->observaciones !== $r['observaciones']) {
                    throw new \RuntimeException('Las observaciones fueron modificadas; no se sobrescribieron.');
                }
                $acta->update(['observaciones' => $texto]);
                $acta->nacimiento->update(['observaciones' => $texto]);
                $r['observaciones'] = $texto;
            }
        });
        file_put_contents($ruta, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->command?->info('22 observaciones aclaradas, sin cambiar datos ni PDF.');
    }
}
