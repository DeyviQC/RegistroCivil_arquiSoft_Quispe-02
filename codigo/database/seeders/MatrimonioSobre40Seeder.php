<?php
namespace Database\Seeders;

use App\Models\Acta;
use App\Models\User;
use App\Services\ActaService;
use App\Services\AuditoriaService;
use App\Http\Requests\StoreMatrimonioRequest;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MatrimonioSobre40Seeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(file_get_contents(storage_path('app/private/importaciones/matrimonio-sobre40-2026/registros.json')), true, 512, JSON_THROW_ON_ERROR);
        if (count($rows) !== 31) { throw new \RuntimeException('Deben existir exactamente 31 registros.'); }
        $user = User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('slug', 'administrador'))->firstOrFail();
        $files = [];
        try {
            DB::transaction(function () use ($rows, $user, &$files) {
                DB::table('acta_secuencias')->where('tipo', 'MATRIMONIO')->lockForUpdate()->first();
                foreach ($rows as $i => $r) {
                    if ($r['orden_tipo'] !== ($i < 8 ? 941 + $i : 942 + $i) || $r['source_page'] !== 2 + $i || $r['sobre'] !== 40 || $r['correlativo'] !== 1 + $i) {
                        throw new \RuntimeException('Numeración o referencia incoherente.');
                    }
                    Validator::make($r, array_merge((new StoreMatrimonioRequest())->rules(), [
                        'numero_acta' => 'required|string|max:20', 'fecha_acta' => 'required|date|before_or_equal:today',
                    ]))->validate();
                    if (hash_file('sha256', $r['pdf']) !== $r['sha256']) { throw new \RuntimeException('PDF diferente al revisado.'); }
                    if (Acta::where('tipo', 'MATRIMONIO')->where('orden_tipo', $r['orden_tipo'])->exists()) { throw new \RuntimeException('Correlativo histórico ocupado.'); }
                    $acta = app(ActaService::class)->registrarHistorico(
                        array_intersect_key($r, array_flip(['tipo', 'numero_acta', 'anio', 'fecha_acta', 'observaciones'])),
                        array_intersect_key($r, (new StoreMatrimonioRequest())->rules()),
                        new UploadedFile($r['pdf'], basename($r['pdf']), 'application/pdf', null, true),
                        $user, $r['orden_tipo'], $r['sobre'], $r['correlativo']
                    );
                    $files[] = $acta->pdf_ruta;
                    if ($acta->orden_tipo !== $r['orden_tipo'] || $acta->sobre !== '40' || $acta->correlativo !== $r['correlativo']) {
                        throw new \RuntimeException('Numeración diferente al documento.');
                    }
                    if (hash('sha256', Storage::disk('local')->get($acta->pdf_ruta)) !== $r['sha256']) { throw new \RuntimeException('Adjunto diferente al original.'); }
                    $detalle = $acta->matrimonio;
                    if ($detalle->apellido_nombre !== $r['apellido_nombre'] || $detalle->dni !== $r['dni']
                        || $detalle->fecha_matrimonio->format('Y-m-d') !== $r['fecha_matrimonio']
                        || $acta->fecha_acta->format('Y-m-d') !== $r['fecha_acta']) {
                        throw new \RuntimeException('Datos distintos al índice revisado.');
                    }
                }
                app(AuditoriaService::class)->registrar(AuditoriaService::IMPORTACION_MASIVA, 'Importación', null,
                    '31 matrimonios de 2026, sobre 40, generales 941–972 (949 ausente), páginas 2–32. Verificados contra índice y PDF original.', $user->id);
            });
        } catch (\Throwable $e) {
            foreach ($files as $file) { Storage::disk('local')->delete($file); }
            throw $e;
        }
        $this->command?->info('31 actas guardadas y verificadas; generales 941–972, sin 949; sobre 40; posiciones 1–31.');
    }
}
