<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Checklist of the settings that must be right before showing or deploying the system. */
class VerificarSistema extends Command
{
    protected $signature = 'sistema:verificar';

    protected $description = 'Revisa la configuración crítica (depuración, cola, respaldos, correo, tareas).';

    public function handle(): int
    {
        $produccion = app()->environment('production');
        $marca = rtrim((string) config('respaldo.ruta'), '/\\').'/ultimo-respaldo.txt';
        $respaldoReciente = is_file($marca) && filemtime($marca) > now()->subHours(36)->getTimestamp() && str_contains((string) file_get_contents($marca), 'OK');
        $pendientes = rescue(fn () => DB::table('jobs')->count(), null, false);
        $fallidos = rescue(fn () => DB::table('failed_jobs')->count(), null, false);

        $revisiones = [
            // [requisito, cumple, obligatorio en producción]
            ['APP_KEY configurada', (bool) config('app.key'), true],
            ['Depuración desactivada (APP_DEBUG=false)', ! config('app.debug'), $produccion],
            ['Nivel de log warning o superior', in_array(config('logging.channels.single.level'), ['warning', 'error', 'critical', 'alert', 'emergency'], true), $produccion],
            ['Cookies solo por HTTPS (SESSION_SECURE_COOKIE=true)', (bool) config('session.secure'), $produccion],
            ['Cola en segundo plano (QUEUE_CONNECTION distinto de sync)', config('queue.default') !== 'sync', true],
            ['Frase de respaldo configurada (RESPALDO_FRASE)', strlen((string) config('respaldo.frase')) >= 12, true],
            ['Respaldo correcto en las últimas 36 horas', $respaldoReciente, true],
            ['Correo real configurado (MAIL_MAILER distinto de log/array)', ! in_array(config('mail.default'), ['log', 'array'], true), true],
            ['Sin correos atascados en la cola (más de 50)', $pendientes !== null && $pendientes <= 50, false],
            ['Sin correos fallidos', $fallidos === 0, false],
        ];

        $fallas = 0;
        foreach ($revisiones as [$requisito, $cumple, $obligatorio]) {
            if ($cumple) {
                $this->line("  <fg=green>✔</> {$requisito}");
            } elseif ($obligatorio) {
                $fallas++;
                $this->line("  <fg=red>✘</> {$requisito}");
            } else {
                $this->line("  <fg=yellow>!</> {$requisito}");
            }
        }
        $this->newLine();
        $this->line($fallas ? "<fg=red>{$fallas} requisito(s) obligatorio(s) sin cumplir.</>" : '<fg=green>Configuración crítica correcta.</>');

        return $fallas ? self::FAILURE : self::SUCCESS;
    }
}
