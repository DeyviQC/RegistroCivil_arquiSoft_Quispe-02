<?php
namespace Database\Seeders;

use App\Models\Acta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ObservacionesDefuncionClarasSeeder extends Seeder
{
    public function run(): void
    {
        $ruta = storage_path('app/private/importaciones/defuncion-sobre53-2026/registros.json');
        $rows = json_decode(file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use (&$rows) {
            foreach ($rows as &$r) {
                $lineas = ['Edad registrada según la lista: '.$r['edad'].($r['edad'] === '1 DIA' ? '.' : ' años.')];
                if ($r['orden_tipo'] === 1481) {
                    $lineas[] = 'Revisar edad: la lista dice 83 años, pero el acta dice 84. Se registraron 83 años; falta confirmar cuál es el dato correcto.';
                }
                if ($r['orden_tipo'] === 1482) {
                    $lineas[] = 'Revisar edad: la lista dice 39 años, pero el acta dice 89. Se registraron 39 años; falta confirmar cuál es el dato correcto.';
                }
                if (in_array($r['orden_tipo'], [1477, 1487])) {
                    $lineas[] = 'Revisar causa de muerte: el texto de la lista está cortado. Se guardó únicamente lo que se alcanza a leer; falta confirmar el texto completo.';
                }
                if ($r['orden_tipo'] === 1474) {
                    $lineas[] = 'Sin DNI en el acta. Como documento de identidad figura el acta de nacimiento N° 3007293834.';
                }
                if ($r['orden_tipo'] === 1497) {
                    $lineas[] = 'DNI: el acta indica que no se conoce. El fallecimiento ocurrió en 1990 y se inscribió en 2026.';
                }
                if (in_array($r['orden_tipo'], [1491, 1494])) {
                    $lineas[] = 'El nombre estaba cortado en la lista y se completó con el que aparece en el acta.';
                }
                $lineas[] = 'Documento de origen: sobre 53, página '.$r['source_page'].' del PDF de defunciones de abril y mayo de 2026.';
                $texto = implode("\n", $lineas);
                $acta = Acta::where('tipo', 'DEFUNCION')->where('numero_acta', $r['numero_acta'])->where('anio', 2026)->firstOrFail();
                if ($acta->observaciones !== $r['observaciones']) {
                    throw new \RuntimeException('Las observaciones fueron modificadas; no se sobrescribieron.');
                }
                $acta->update(['observaciones' => $texto]);
                $r['observaciones'] = $texto;
            }
        });
        file_put_contents($ruta, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->command?->info('25 observaciones reescritas sin cambiar edades, causas ni documentos.');
    }
}
