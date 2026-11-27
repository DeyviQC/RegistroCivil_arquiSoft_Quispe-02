<?php

namespace App\Console\Commands;

use App\Models\{Acta, TramitePublico};
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

/**
 * One-off backfill: requests published before signed copies were kept on the act
 * become the act's reusable copy (latest delivery signed over the unchanged original).
 */
class RecuperarCopiasFirmadas extends Command
{
    protected $signature = 'actas:copias-firmadas';

    protected $description = 'Guarda en cada acta la copia firmada más reciente de solicitudes ya entregadas.';

    public function handle(AuditoriaService $auditoria): int
    {
        $disk = Storage::disk('local');
        $actualizadas = 0;
        $tramites = TramitePublico::with('acta')->where('estado', 'LISTA')->orderByDesc('id')->get();
        foreach ($tramites->groupBy('acta_id') as $actaId => $lista) {
            foreach ($lista as $t) {
                $e = $t->datos['entrega'] ?? [];
                $acta = $t->acta;
                if (empty($e['revision']) || empty($e['firmado_ruta']) || ! empty($e['reutilizada'])
                    || ($e['preparado_ruta'] ?? null) !== $acta->pdf_ruta || ! $acta->pdf_hash
                    || ! $disk->exists($e['firmado_ruta']) || ! hash_equals((string) ($e['firmado_sha256'] ?? ''), hash_file('sha256', $disk->path($e['firmado_ruta'])))) {
                    continue;
                }
                $fecha = Carbon::parse($e['revision']['fecha'] ?? $t->updated_at);
                if ($acta->firmado_at && $acta->firmado_at->gte($fecha)) break; // already has this one or a newer one
                DB::transaction(function () use ($acta, $t, $e, $fecha, $disk, $auditoria) {
                    $ruta = 'private/actas-firmadas/'.$acta->id.'/'.Str::uuid().'.pdf';
                    $disk->copy($e['firmado_ruta'], $ruta);
                    $acta->forceFill(['firmado_ruta' => $ruta, 'firmado_sha256' => $e['firmado_sha256'], 'firmado_original_hash' => $acta->pdf_hash,
                        'firmado_firmante' => $e['firmante'] ?? null, 'firmado_certificado_sha256' => $e['certificado_sha256'] ?? null,
                        'firmado_at' => $fecha, 'firmado_por' => $e['revision']['usuario_id'] ?? null, 'firmado_tramite_id' => $t->id])->save();
                    $auditoria->registrar('ACTUALIZAR_COPIA_FIRMADA', 'Registro Civil', $acta->id, 'Copia firmada recuperada de la solicitud '.$t->id.'.');
                });
                $actualizadas++;
                $this->line("Acta {$actaId}: copia firmada de la solicitud N.º ".str_pad((string) $t->id, 6, '0', STR_PAD_LEFT).' ('.$fecha->format('d/m/Y').').');
                break;
            }
        }
        $this->info("Actas actualizadas: {$actualizadas}.");
        return self::SUCCESS;
    }
}
