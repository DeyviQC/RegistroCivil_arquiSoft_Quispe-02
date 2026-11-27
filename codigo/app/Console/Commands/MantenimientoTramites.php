<?php

namespace App\Console\Commands;

use App\Models\TramitePublico;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MantenimientoTramites extends Command
{
    protected $signature = 'tramites:mantenimiento';

    protected $description = 'Vence solicitudes que nunca iniciaron el pago y borra códigos de verificación expirados.';

    public function handle(AuditoriaService $auditoria): int
    {
        $limite = now()->subDays(max(1, (int) config('portal.dias_para_pagar', 7)));
        $vencidas = 0;

        // Only requests with no payment record at all: once a gateway order exists,
        // a late webhook could still confirm it, so those are left for manual review.
        TramitePublico::where('estado', 'PENDIENTE_PAGO')->where('created_at', '<', $limite)
            ->whereDoesntHave('pago')->select('id')->chunkById(100, function ($lote) use (&$vencidas, $auditoria) {
                foreach ($lote as $fila) {
                    DB::transaction(function () use ($fila, &$vencidas, $auditoria) {
                        $tramite = TramitePublico::lockForUpdate()->find($fila->id);
                        if (! $tramite || $tramite->estado !== 'PENDIENTE_PAGO' || $tramite->pago()->exists()) {
                            return;
                        }
                        $tramite->fill(['otp_hash' => null, 'otp_expira_at' => null])
                            ->transicionar('VENCIDA', 'La solicitud venció porque no se inició el pago. No se realizó ningún cobro.');
                        $auditoria->registrar('VENCER_TRAMITE', 'Portal ciudadano', $tramite->id, 'Vencida automáticamente por falta de pago.');
                        $vencidas++;
                    });
                }
            });

        $codigos = TramitePublico::whereNotNull('otp_hash')->where('otp_expira_at', '<', now())
            ->update(['otp_hash' => null, 'otp_expira_at' => null]);

        $this->info("Solicitudes vencidas: {$vencidas}. Códigos expirados eliminados: {$codigos}.");

        return self::SUCCESS;
    }
}
