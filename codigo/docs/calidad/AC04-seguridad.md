# AC04 — Seguridad y privacidad

**Escenario:** rechazar todos los casos no autorizados de la matriz de pruebas; no exponer PDF, códigos
ni datos personales en respuestas públicas revisadas. Aplicar autorización, límites de intentos, cifrado
de datos del trámite y TLS en conexiones externas.

**Meta:** 100 % de los casos no autorizados rechazados; OWASP ZAP sin alertas de riesgo **alto**;
sin PDF, códigos ni datos personales en respuestas públicas; librerías sin vulnerabilidades conocidas.

**Veredicto:** ver §7.

## 1. Datos de la ejecución

| Campo | Valor |
|---|---|
| Fecha | 26/11/2026 |
| Herramientas | Composer audit · Postman/Newman 6.2.3 · OWASP ZAP 2.17.0 (Java 17) · PHPUnit |
| Entorno | Laravel Herd (`http://registro-civil.test`), base `registro_civil_carga`, **`APP_DEBUG=false`** (como producción), límites de intentos activos, correo en `log` |

## 2. Librerías con vulnerabilidades conocidas (`composer audit`)

| Momento | Avisos | Paquetes |
|---|---|---|
| Antes | **15** | `laravel/framework` 12.69.2 y `symfony/*` 7.4.19 (http-foundation, http-kernel, mailer, mime, routing, yaml) |
| Después | **0** ✅ | Laravel **12.74.0** y Symfony **7.4.24** |

Los más relevantes para este sistema: inyección de cabeceras en correos (`symfony/mime`, severidad media) y
dos fallas que permitían a un intermediario saltarse el TLS obligatorio del correo (`symfony/mailer`).
Se actualizaron solo versiones de corrección dentro de la misma rama (sin cambios incompatibles); las
130 pruebas y PHPStan siguen en verde. Evidencia: [`ac04-composer-audit.txt`](evidencias/ac04-composer-audit.txt).

## 3. Matriz de autorización (Postman/Newman)

Colección: [`tests/seguridad/ac04-matriz-autorizacion.postman_collection.json`](../../tests/seguridad/ac04-matriz-autorizacion.postman_collection.json)
(se puede importar en Postman). **53 peticiones, 71 verificaciones, 0 fallidas** ✅ —
detalle caso por caso en [`ac04-newman-resumen.md`](evidencias/ac04-newman-resumen.md).

| Grupo | Casos | Resultado esperado | Resultado |
|---|---|---|---|
| 1. Panel sin sesión (inicio, actas, PDF, copia firmada, usuarios, auditoría, reportes, bandeja, API) | 10 | Redirige al login / 401 en la API | ✅ 10/10 |
| 2. Portal sin verificar (estado, descarga, Yape, mis solicitudes, código, enlace con firma falsa) | 7 | Redirige al seguimiento / 403 | ✅ 7/7 |
| 3. Formularios sin token CSRF | 3 | 419 | ✅ 3/3 |
| 4. Archivos internos (`.env`, `composer.json`, log, PDF privado) y webhooks con firma falsa o retirados | 6 | 404 / 403 / 401 — nunca 200 | ✅ 6/6 |
| 5. Límites de intentos (búsqueda y login) | 15 | 5 búsquedas y luego 429; tras 5 contraseñas erróneas, bloqueo | ✅ 15/15 |
| 6. Asistente intentando funciones de administrador | 9 | 403 en usuarios y auditoría; al cerrar sesión ya no entra | ✅ 9/9 |
| 7. Ciudadano intentando ver una solicitud ajena / enumerar | 3 | 403; respuesta genérica igual para datos inexistentes | ✅ 3/3 |

### Defecto encontrado y corregido: contador de intentos compartido

La primera ejecución completa falló en los límites: la búsqueda permitía **3** intentos en lugar de 5 y el
seguimiento quedaba bloqueado sin haberlo usado. Causa: Laravel identifica los límites escritos como
`throttle:N,M` **solo por la IP**, así que **todas esas rutas compartían un mismo contador**: descargar el
PDF o abrir el enlace del correo consumía intentos de búsqueda y de seguimiento. En uso real, un ciudadano
podía quedar bloqueado en «Seguir mi solicitud» sin equivocarse.

Corrección: `ThrottleConPruebasCarga` asigna a cada límite un contador propio por ruta. Prueba:
`PruebasCargaTest::test_cada_ruta_tiene_su_propio_contador_de_intentos`.

## 4. Cabeceras de seguridad y exposición de información

| Elemento | Antes | Después |
|---|---|---|
| Protección contra clickjacking (`X-Frame-Options`, `frame-ancestors`) | ❌ Ausente | ✅ `SAMEORIGIN` / `'self'` |
| `Content-Security-Policy` | ❌ Ausente | ✅ Solo recursos propios + Google Fonts; sin plugins (`object-src 'none'`), `base-uri 'self'` |
| `X-Content-Type-Options` en páginas | ❌ Solo en descargas | ✅ `nosniff` en todo |
| `Referrer-Policy` / `Permissions-Policy` | ❌ Ausentes | ✅ `strict-origin-when-cross-origin` / cámara, micrófono, ubicación y pago desactivados |
| Versión de PHP (`X-Powered-By: PHP/8.4.25`) | ❌ Expuesta | ✅ Eliminada |
| HSTS y COOP | — | ✅ Solo bajo HTTPS (los navegadores los ignoran en HTTP) |
| Cookie de sesión | ✅ `HttpOnly`, `SameSite=Lax` | Igual; `Secure` se activa en producción con `SESSION_SECURE_COOKIE=true` |
| Páginas de error con `APP_DEBUG=false` | ✅ Sin trazas ni rutas internas | Igual |

Implementación: middleware `CabecerasSeguridad` (aplicado a todas las respuestas web) y prueba
`EndurecimientoTest::test_las_respuestas_llevan_cabeceras_de_seguridad_y_no_revelan_php`. Se verificó en
el navegador que el portal, el login, el panel y el visor de PDF (pdf.js con *worker*) funcionan sin
violaciones de la política.

**Limitación de la CSP:** las vistas usan scripts en línea y atributos `style`, por lo que la política
permite `'unsafe-inline'`. Bloquea código de otros sitios, pero no sustituye a la protección contra XSS
de Blade (escape automático con `{{ }}`). Moverlos a archivos `.js` permitiría quitarla (mejora futura).

## 5. Cifrado y TLS

| Control | Verificación | Resultado |
|---|---|---|
| Datos del trámite cifrados | Lectura directa en MySQL de las solicitudes reales N.º 19 y 20: la columna `datos` contiene `eyJpdiI6…` (AES-256 con MAC de Laravel); **0 filas** contienen el DNI o el nombre en claro | ✅ |
| Búsqueda por DNI sin guardarlo en claro | Columna `dni_hash` (HMAC-SHA256 con la clave de la aplicación) | ✅ |
| Códigos de verificación | Se guardan como hash (`otp_hash`, bcrypt) y caducan | ✅ |
| Clave de cifrado respaldada | `app-key.enc` cifrado con `RESPALDO_FRASE` (ver AC08) | ✅ |
| TLS en ApisPeru, Mercado Pago, Yape y Firma Perú | `PortalHttpOptions::trusted()`: verificación de certificado siempre activa y redirecciones bloqueadas; no hay ningún `verify => false` en el código | ✅ |
| TLS en el correo | `require_tls = true` y verificación del certificado del servidor SMTP | ✅ |

## 6. Escaneo automático con OWASP ZAP

*(pendiente: se completa al terminar el escaneo)*

## 7. Veredicto

*(pendiente)*
