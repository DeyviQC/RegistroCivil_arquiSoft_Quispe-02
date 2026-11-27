# Guía para ejecutar las pruebas de calidad

Pasos para repetir cada prueba y mostrarla. Todo se ejecuta en **PowerShell** dentro de la carpeta del
proyecto (`E:\Municipalidad\registro-civil-completofinal\registro-civil-desarrollo`).

> Si `php` no se reconoce, usa la ruta completa: `C:\Users\LOQ\.config\herd\bin\php84\php.exe`.

## 0. Preparación (una sola vez)

1. **Abre Herd** (menú Inicio → Herd) y **DBngin** con MySQL encendido.
2. Comprueba que el sitio responde: abre `http://registro-civil.test/tramite` en el navegador.
3. Herramientas ya instaladas en el equipo: **k6**, **OWASP ZAP**, **PHPStan** (en el proyecto).
   Falta **Newman** (Postman por consola):
   ```powershell
   npm install -g newman
   ```
   Si da error de certificado (por el antivirus), ejecuta antes:
   `$env:NODE_EXTRA_CA_CERTS = "storage\app\private\certificates\windows-ca.pem"`
4. Base de pruebas con datos ficticios (ya existe; solo si la borras):
   ```powershell
   $env:DB_DATABASE='registro_civil_carga'; php artisan migrate --force
   php artisan pruebas:generar-datos --actas=10000 --tramites=2000
   Remove-Item Env:DB_DATABASE
   ```

### Modo prueba del `.env` (para AC01, AC03, AC04 y la concurrencia de AC05)

Las pruebas de carga y seguridad **no** se hacen sobre tus datos reales. Antes de ellas:

1. Copia tu `.env` como respaldo: `Copy-Item .env .env.backup`
2. Abre `.env` y cambia:
   - `DB_DATABASE=registro_civil_carga`
   - `MAIL_MAILER=log` (para no enviar correos reales)
   - Solo para AC01/AC03/AC05: agrega `PRUEBAS_CARGA_TOKEN=` seguido de 32 letras y números inventados
   - Solo para AC04: `APP_DEBUG=false` y **sin** `PRUEBAS_CARGA_TOKEN`
3. Ejecuta `php artisan config:clear`

**Al terminar, siempre:** `Copy-Item .env.backup .env -Force` y `php artisan config:clear`.

---

## AC01 — Rendimiento (k6)

Con el `.env` en modo prueba (con `PRUEBAS_CARGA_TOKEN`):
```powershell
k6 run -e BASE=http://registro-civil.test -e TOKEN=<tu PRUEBAS_CARGA_TOKEN> -e VUS=20 -e DURACION=2m tests/carga/ac01-rendimiento.js
```
**Qué mostrar:** al final, k6 marca con ✓ cada meta `p(95)<1000` (menos de 1 segundo) y `http_req_failed` en 0 %.

## AC03 — Escalabilidad (k6 + CPU/RAM)

Mismo `.env` de prueba. Cambia la cantidad de usuarios y guarda la evidencia:
```powershell
powershell -ExecutionPolicy Bypass -File tests\carga\ejecutar-carga.ps1 -Usuarios 50 -Duracion 2m -Etiqueta demo-50 -Datos datos-50k.json -Token <tu token>
```
Muestra el p95 de cada operación, los errores y el uso de CPU y RAM. Resultados completos en `AC03-escalabilidad.md`.

## AC04 — Seguridad

**a) Librerías vulnerables** (no necesita modo prueba):
```powershell
php -d openssl.cafile=storage\app\private\certificates\windows-ca.pem C:\Users\LOQ\.config\herd\bin\composer.phar audit
```
Debe decir: *No security vulnerability advisories found.*

**b) Matriz de autorización** — con el `.env` en modo prueba de AC04 (`APP_DEBUG=false`, sin token):
```powershell
php artisan cache:clear
newman run tests/seguridad/ac04-matriz-autorizacion.postman_collection.json --env-var base=http://registro-civil.test --env-var acta=1045 --env-var tramite=1 --env-var codigo=f688219b-887e-4bf7-8cd8-615d9f4d2194
```
Debe terminar con **71 assertions, 0 failed**. Para mostrarlo de forma visual: abre **Postman** →
*Import* → elige el mismo archivo `.json` → *Run collection* (configura las variables `base`, `acta`,
`tramite` y `codigo` con los valores de arriba).

> `php artisan cache:clear` antes de cada ejecución: reinicia los contadores de intentos.

**c) OWASP ZAP (escaneo automático)** — sobre una **copia de prueba** con `APP_DEBUG=false`, sin
`PRUEBAS_CARGA_TOKEN` y con una base clonada: el escaneo activo envía formularios con ataques. La copia se
levanta en el puerto 8030, que es el que usan los planes:
```powershell
powershell -ExecutionPolicy Bypass -File tests\carga\servidor-carga.ps1 -Raiz C:\ruta\copia\codigo -Trabajadores 6 -Puerto 8030 -PuertoFastCgi 9301
```
- **Forma visual (para mostrar en clase), con el mismo plan:** abre **ZAP** → pestaña **Automation**
  (si no se ve: *View → Show Tab → Automation*) → **Load Plan…** → elige `tests\seguridad\zap-portal.yaml` →
  **Run Plan**. Se ven los trabajos (araña, pasivo, activo) y al terminar las alertas en la pestaña *Alerts*. El
  informe queda en `sprint-2\evidencias\eq04-zap-portal.html`. Repite con `zap-panel.yaml` para el panel con
  sesión.
- **Forma automática (sin ventana).** Usa otro puerto si el ZAP de escritorio está abierto, y guarda la sesión
  de ZAP en un disco con espacio: el escaneo con sesión ocupa ~3 GB.
  ```powershell
  $env:JAVA_HOME = "C:\Program Files\Eclipse Adoptium\jre-17.0.21.10002-hotspot"
  & "C:\Program Files\ZAP\Zed Attack Proxy\zap.bat" -cmd -port 8091 -dir E:\zap-temp -autorun "$PWD\tests\seguridad\zap-portal.yaml"
  & "C:\Program Files\ZAP\Zed Attack Proxy\zap.bat" -cmd -port 8091 -dir E:\zap-temp -autorun "$PWD\tests\seguridad\zap-panel.yaml"
  ```
  Antes del plan con sesión, ejecuta `php artisan cache:clear` en la copia, para reiniciar los límites de intentos
  del login.
- **Si ZAP no abre** porque no encuentra Java, usa el Java 17 instalado:
  ```powershell
  $env:JAVA_HOME = "C:\Program Files\Eclipse Adoptium\jre-17.0.21.10002-hotspot"
  & "C:\Program Files\ZAP\Zed Attack Proxy\zap.bat"
  ```
**Meta:** ninguna alerta de riesgo **High** (alto). Anota las alertas en la sección 6 de `AC04-seguridad.md`.

## AC05 — Integridad

**a) Pruebas automáticas en los dos motores** (no necesita modo prueba):
```powershell
php artisan test
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='registro_civil_prueba'; php artisan test; Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```
Las dos deben decir **130 passed**.

**b) Concurrencia** (modo prueba con token). Primero busca 5 solicitudes pendientes en la base de carga:
```powershell
$env:DB_DATABASE='registro_civil_carga'; php artisan tinker --execute="echo App\Models\TramitePublico::where('estado','PENDIENTE')->whereHas('acta', fn(`$q)=>`$q->where('tipo','MATRIMONIO'))->limit(5)->pluck('id')->implode(',');"; Remove-Item Env:DB_DATABASE
k6 run -e BASE=http://registro-civil.test -e TOKEN=<tu token> -e TRAMITES=<los 5 números> tests/carga/ac05-concurrencia.js
```
Debe mostrar **decisiones_aplicadas: 5** y **decisiones_rechazadas: 45**.

## AC07 — Mantenibilidad (PHPStan)

```powershell
php vendor/bin/phpstan analyse --memory-limit=1G
```
Debe decir **[OK] No errors**.

## Estado

Todos los atributos tienen resultado. **AC02**, **AC06** y **AC08**, la carga progresiva hasta 2 000 usuarios y
la repetición de AC05 sobre el código del Sprint 2 están en
[`sprint-2/06-atributos-de-calidad.md`](../../../sprint-2/06-atributos-de-calidad.md), con sus comandos
(sección 9). El resultado de ZAP y el veredicto de **AC04** están en `AC04-seguridad.md`, §6 y §7.
