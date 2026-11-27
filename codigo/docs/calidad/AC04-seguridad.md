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

**Ejecución (Sprint 2, 27/11/2026):** OWASP ZAP 2.17.0 en modo automático, con dos planes versionados:

| Plan | Qué escanea | Trabajos |
|---|---|---|
| [`tests/seguridad/zap-portal.yaml`](../../tests/seguridad/zap-portal.yaml) | Portal ciudadano y login, **sin sesión** | Araña, araña AJAX, escaneo pasivo y **activo** |
| [`tests/seguridad/zap-panel.yaml`](../../tests/seguridad/zap-panel.yaml) | Panel del personal **con sesión** (rol asistente) | Araña, escaneo pasivo y **activo** (15 min) |

**Condiciones del escaneo:**
- Se escaneó una copia del código del Sprint 2 con `APP_DEBUG=false`, sin token de pruebas de carga
  (límites de intentos activos), detrás de nginx.
- La base fue un **clon** de la base de pruebas, porque el escaneo activo envía formularios con ataques.
- En el plan con sesión, ZAP renueva solo el campo CSRF `_token`. Se comprobó que **sí inició sesión**:
  1 `LOGIN` en la auditoría y 1 258 acciones del asistente (consultar actas, ver e imprimir PDF).

### 6.1 Primer escaneo (sin sesión): hallazgos y correcciones

Evidencia: [`eq04-zap-portal-antes.html`](../../../sprint-2/evidencias/eq04-zap-portal-antes.html).
**0 alertas de riesgo alto.** Las medias y bajas se corrigieron así:

| Alerta (riesgo) | Causa | Corrección |
|---|---|---|
| *CSP Header Not Set* y *X-Powered-By: PHP/8.4.25* (medio / bajo) en 404 de URL inexistentes y 419 sin token CSRF | `CabecerasSeguridad` estaba dentro del grupo `web`: lo que se responde antes de llegar a él salía sin cabeceras | Ahora es **middleware global** (`bootstrap/app.php`). Prueba: `EndurecimientoTest::test_las_cabeceras_tambien_van_en_errores_fuera_de_las_rutas_y_limitan_los_formularios` (falla sin la corrección) |
| *CSP: Failure to Define Directive with No Fallback* (medio) | Faltaba `form-action`, que no hereda de `default-src` | `form-action 'self'` más los dos únicos destinos externos tras un formulario: el checkout de Mercado Pago (`*.mercadopago.com.pe`, `*.mercadopago.com`, por sus redirecciones internas) y WhatsApp (`wa.me` → `api.whatsapp.com`, comprobado). **Pendiente:** confirmar en el navegador, con un pago de prueba, que el checkout abre con la nueva política |
| *.htaccess Information Leak* (medio) | nginx servía `public/.htaccess` | `location ~ /\.(?!well-known) { deny all; }` (403) |
| *Server Leaks Version* (bajo) | nginx mostraba `nginx/1.25.2` | `server_tokens off` |
| *X-Content-Type-Options Missing* (bajo) en CSS | Los estáticos no pasan por Laravel | `nosniff` en los estáticos desde nginx |

Las tres correcciones de nginx están en [`tests/carga/servidor-carga.ps1`](../../tests/carga/servidor-carga.ps1) y
son **requisito de la configuración del servidor del Sprint 3**.

### 6.2 Resultado final

Evidencias:
- portal y login sin sesión: [`eq04-zap-portal.html`](../../../sprint-2/evidencias/eq04-zap-portal.html);
- panel con sesión: [`eq04-zap-panel.html`](../../../sprint-2/evidencias/eq04-zap-panel.html);
- los mismos resultados en `.json`.

| Riesgo | Portal (sin sesión) | Panel (con sesión) |
|---|---|---|
| **Alto** | **0** | 1 → **falso positivo** (*Path Traversal*, confianza baja, §6.3) |
| Medio | 3 (aceptadas, §6.4) | 3 (las mismas, aceptadas) |
| Bajo | 3 (aceptadas) | 3 (aceptadas) |

La alerta de inyección SQL apareció en el panel en un intento previo, cuando ZAP no había logrado iniciar
sesión y atacó `/recuperar-contrasena`. En el plan final esa ruta y `/login` se excluyen del panel, porque ya se
escanean sin sesión.

**Integridad tras el ataque:** se compararon con `CHECKSUM TABLE` todas las tablas de la base escaneada contra
la original. Solo cambiaron `auditoria`, donde quedaron registradas las propias acciones del escaneo, y
`sessions`. **Ningún dato de negocio fue modificado** por 15 minutos de ataques con la sesión del asistente.

### 6.3 Análisis de las alertas de riesgo alto (falsos positivos)

**Inyección SQL en `POST /recuperar-contrasena`, campo `email` (confianza media).** ZAP envió
`zaproxy@example.com`, `… AND 1=1 -- ` y `… OR 1=1 -- `, y vio respuestas distintas. Se reprodujo con las
mismas tres peticiones:
1. el correo válido recibe el aviso de que el correo no está configurado en local;
2. `… AND 1=1 -- ` recibe **«El campo correo electrónico debe ser una dirección de correo válida»**: la regla
   `email` de Laravel lo rechaza antes de cualquier consulta;
3. la tercera petición recibe **429**, por el límite de 5 intentos por minuto.

La diferencia la producen la validación y el límite de intentos, no la base de datos. Además, la consulta usa
parámetros enlazados (`User::where('email', $email)`); no hay SQL armado con texto del usuario.

**Path Traversal en `PATCH /actas/{id}/filiacion` (confianza baja).** ZAP envía como valor de cada campo el
nombre del propio recurso (`filiacion`, `/filiacion`, `\filiacion`) y compara respuestas. `FiliacionController`
no lee ni escribe archivos: valida con `GuardarFiliacionRequest` y guarda con Eloquent. En la base escaneada,
`acta_nacimientos` tiene el **mismo checksum** que la original, `filiacion_historial` sigue vacío y no hay
ninguna auditoría `COMPLETAR_FILIACION`. Ninguno de los 135 intentos cambió nada.

### 6.4 Alertas aceptadas (riesgo medio y bajo)

| Alerta | Por qué se acepta |
|---|---|
| CSP con `'unsafe-inline'` en scripts y estilos (medio) | Las vistas usan scripts en línea (limitación declarada en §4). La CSP igual bloquea código de otros dominios, los plugins y el enmarcado; el escape de Blade protege contra XSS. Mejora futura: mover los scripts a archivos `.js` |
| *Sub Resource Integrity Missing* en Google Fonts (medio) | Google sirve un CSS distinto según el navegador, así que no admite un hash fijo. Alternativa futura: alojar las fuentes en el propio servidor |
| Cookie `XSRF-TOKEN` sin `HttpOnly` (bajo) | Es intencional: el JavaScript de la página debe leerla para enviar el token CSRF. La cookie de sesión **sí** es `HttpOnly` |
| *Timestamp Disclosure* (bajo) | Es la versión de los archivos CSS y JS (`?v=` fecha de modificación), sin información sensible |
| *Big Redirect* (bajo) | Redirecciones de Laravel con una página mínima que incluye la URL de destino; no contienen datos |

## 7. Veredicto

| Criterio | Resultado |
|---|---|
| 100 % de los casos no autorizados rechazados (matriz Postman) | ✅ 53 peticiones, 71 verificaciones, 0 fallidas |
| OWASP ZAP sin alertas de riesgo **alto** | ✅ 0 confirmadas: las 2 alertas altas reportadas se analizaron y reprodujeron, y son falsos positivos (§6.3) |
| Sin PDF, códigos ni datos personales en respuestas públicas | ✅ Matriz Postman y revisión de los informes de ZAP |
| Librerías sin vulnerabilidades conocidas | ✅ `composer audit`: 0 |

**AC04 cumple.** Queda la mejora futura de quitar `'unsafe-inline'` de la CSP, y las cabeceras de nginx pasan a
ser requisito del despliegue (Sprint 3).
