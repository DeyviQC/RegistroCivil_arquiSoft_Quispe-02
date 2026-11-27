<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Requieren que el equipo ejecute cada minuto:  php artisan schedule:run
| En Windows: scripts\windows\registrar-tareas.ps1 (Programador de tareas).
| En Linux: cron "* * * * * php artisan schedule:run". En desarrollo: php artisan schedule:work
|
| Pensado para una laptop que no siempre está encendida: las tareas diarias se
| comprueban cada hora y solo se ejecutan si toca, en vez de depender de una hora fija.
*/

// Correos en cola (avisos de estado). Procesa lo pendiente y termina.
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping(5);

// Respaldo diario de la base, la APP_KEY cifrada y los PDFs privados:
// se ejecuta en cuanto el equipo esté encendido si el último tiene más de 20 horas.
Schedule::command('respaldo:crear')->hourly()->withoutOverlapping()->onOneServer()
    ->when(fn () => ! is_file($marca = rtrim((string) config('respaldo.ruta'), '/\\').'/ultimo-respaldo.txt')
        || filemtime($marca) < now()->subHours(20)->getTimestamp());

// Vence solicitudes sin pago iniciado y borra códigos de verificación expirados (idempotente).
Schedule::command('tramites:mantenimiento')->hourly()->withoutOverlapping();

// Limpieza de enlaces de recuperación de contraseña vencidos.
Schedule::command('auth:clear-resets')->daily();

// Conserva los trabajos de correo fallidos 30 días para poder revisarlos.
Schedule::command('queue:prune-failed --hours=720')->weekly();
