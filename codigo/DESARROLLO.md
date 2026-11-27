# Copia para desarrollo

Esta carpeta es independiente de la versión preparada para entrega.

- Base de datos: `registro_civil_desarrollo`.
- Documentos: sus propias copias en `storage/app`.
- URL prevista: `http://127.0.0.1:8001`.
- Correo: `log`, para evitar envíos reales durante pruebas.

## Iniciar localmente

1. Abra DBngin e inicie el servidor MySQL habitual del proyecto.
2. Abra Herd para usar la versión de entrega en registro-civil.test.
3. Inicie esta copia de desarrollo con el comando indicado abajo.

No utilice el servicio MySQL80 de Windows como sustituto: las bases y credenciales corresponden al servidor administrado por DBngin.

Desde esta carpeta, en PowerShell:

```powershell
.\iniciar-desarrollo.cmd
```

Mantenga esa terminal abierta y visite `http://127.0.0.1:8001`.

## Trabajo previsto

Portal ciudadano, solicitudes y estados, firma digital, validación por QR,
entrega autorizada y notificaciones por WhatsApp.

Las credenciales SMTP y demás secretos están en `.env`; no deben subirse a Git.
La versión de entrega conserva su carpeta y su base `registro_civil`.
También puede abrir iniciar-desarrollo.cmd con doble clic. Si el servidor ya está iniciado en el puerto 8001, no abra una segunda instancia.
