# Avisos por correo, tareas programadas y respaldos

## Avisos automáticos al ciudadano

Cada cambio de estado de una solicitud del portal crea un evento en `tramite_eventos`. Al crearse, se envía un correo en cola (`App\Mail\EstadoTramiteMail`) al correo **verificado** del solicitante para estos estados:

| Estado | Aviso |
| --- | --- |
| PENDIENTE | Pago recibido, pendiente de revisión |
| OBSERVADA | Observación del personal (incluye su mensaje) |
| APROBADA | Aprobada, pendiente de firma |
| LISTA | Documento firmado disponible en el seguimiento |
| RECHAZADA | Rechazo (incluye el motivo) |

`EN_REVISION` y `VENCIDA` no generan correo. El aviso no incluye datos del acta ni enlaces de descarga: solo el número de solicitud, el estado, el mensaje del personal y el enlace al seguimiento, donde el ciudadano vuelve a verificar su correo.

El correo se encola después de confirmar la transacción y se reintenta hasta 5 veces (1 min, 5 min, 15 min, 1 h).

## Cola de correos

- Desarrollo: `QUEUE_CONNECTION=sync` envía al momento, sin procesos extra.
- Producción: `QUEUE_CONNECTION=database` y un proceso siempre activo:

```powershell
php artisan queue:work --tries=5 --max-time=3600
```

Debe reiniciarse automáticamente si se detiene (Supervisor en Linux; un servicio o una tarea programada "al iniciar" en Windows). Los trabajos fallidos se revisan con `php artisan queue:failed` y se reintentan con `php artisan queue:retry all`.

## Tareas programadas

Definidas en `routes/console.php`. El servidor debe ejecutar cada minuto:

```powershell
php artisan schedule:run
```

En Windows: Programador de tareas → repetir cada 1 minuto. En Linux: `* * * * * cd /ruta && php artisan schedule:run`. En desarrollo basta `php artisan schedule:work`.

| Hora | Tarea |
| --- | --- |
| 02:00 | `respaldo:crear` |
| 03:00 | `tramites:mantenimiento`: vence solicitudes sin ninguna orden de pago tras `PORTAL_DIAS_PARA_PAGAR` días (7) y borra códigos de verificación expirados |
| Diario | `auth:clear-resets` |
| Semanal | `queue:prune-failed` (conserva 30 días) |

Las solicitudes con una orden de pago creada **no** se vencen automáticamente: un webhook tardío podría confirmar ese pago, así que se revisan a mano.

## Respaldos

`php artisan respaldo:crear`:

1. Vuelca la base MySQL con `mysqldump --single-transaction` comprimido en `base-datos/` y conserva `RESPALDO_DIAS_BD` días (14).
2. Copia en espejo `storage/app/private` (PDFs de actas, firmas y fotos) en `archivos/`. Es incremental: solo copia lo nuevo o modificado y nunca borra del espejo.
3. Escribe `ultimo-respaldo.txt` con la fecha y el resultado.

Configuración en `.env`:

- `RESPALDO_RUTA`: carpeta destino. **En producción debe estar en otro disco** (unidad externa, de red o sincronizada con la nube). Un respaldo en el mismo disco no protege de su falla. Por defecto `storage/app/respaldos`.
- `RESPALDO_MYSQLDUMP`: ruta de `mysqldump` si no está en el PATH (en DBngin: `.../com.tinyapp.DBngin/Binaries/mysql/<versión>/bin/mysqldump.exe`).

La contraseña de la base se pasa por variable de entorno, no en la línea de comandos.

### Restaurar

```powershell
# Base de datos (descomprimir el .sql.gz y cargarlo)
mysql -u root registro_civil < mysql-AAAAMMDD-HHMMSS.sql
# Documentos: copiar el contenido de archivos/ en storage/app/private
```

Conviene probar una restauración completa en otra máquina al menos una vez al mes.
