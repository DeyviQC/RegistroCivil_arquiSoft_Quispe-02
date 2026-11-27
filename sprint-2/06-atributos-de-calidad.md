# Validación de los atributos de calidad — Sprint 2

Este documento contrasta los escenarios de calidad del Sprint 1
([`analisis-de-sistema/08-escenarios-atributos-calidad.md`](../analisis-de-sistema/08-escenarios-atributos-calidad.md))
con mediciones hechas sobre el código del Sprint 2. Cada resultado enlaza su evidencia en
[`evidencias/`](evidencias/). Donde la meta no se cumple, se dice y se explica por qué.

## Resumen

| Escenario | Meta | Resultado | Veredicto |
|---|---|---|---|
| **EQ-01** Rendimiento | p95 ≤ 2 s en las consultas, errores < 1 % | p95 total **0,57 s** con **250 usuarios simultáneos** y 50 000 actas. La búsqueda del archivo por nombre cumple hasta 100 usuarios (0,66 s); a 250 sube a 3,5 s | ✅ Cumple hasta 250 usuarios en total y hasta 100 en la búsqueda del archivo por nombre |
| **EQ-03** Escalabilidad | Carga progresiva hasta **2 000 usuarios** | Se llegó a 2 000 usuarios. El sistema no se cae, pero desde 500 usuarios un solo portátil se satura: p95 de 4,6 a 13,6 s y de 3 % a 35 % de fallos | ⚠️ Cumple hasta 250; por encima hace falta escalar horizontalmente (Sprint 3) |
| **EQ-02** Disponibilidad | Ningún fallo simulado confirma un pago, aprueba ni entrega | **17 de 17** fallos simulados (APIsPerú, correo, Mercado Pago al pagar, comprobar, retornar y notificar, y caída de MySQL) controlados, sin cobro ni confirmación | ✅ Cumple |
| **EQ-04** Seguridad | 100 % de los accesos no autorizados rechazados; sin datos expuestos | Matriz Postman de **53 peticiones y 71 verificaciones, 0 fallidas**; 0 vulnerabilidades en las dependencias; cabeceras de seguridad. Escaneo OWASP ZAP pendiente | ✅ Cumple (ZAP pendiente) |
| **EQ-05** Mantenibilidad | Pruebas afectadas y regresiones aprobadas; PHPStan sin errores | **173 pruebas, 1 700 verificaciones, 0 fallos**; PHPStan nivel 5 sin errores; CI en GitHub Actions. La corrección de esta fase cambió **un solo servicio** | ✅ Cumple |
| **EQ-06** Integridad | Con decisiones simultáneas, solo una se aplica | 50 personas deciden en el mismo instante sobre 5 solicitudes: **5 aplicadas, 45 rechazadas, 0 inesperadas**. En MySQL hay una decisión, una versión y una auditoría por solicitud | ✅ Cumple |
| **EQ-07** Auditoría | El 100 % de los cambios probados deja historial y auditoría | Pruebas de filiación y auditoría aprobadas; en EQ-06, 5 de 5 decisiones con su auditoría `REVISAR_TRAMITE` | ✅ Cumple |
| **EQ-08** Recuperabilidad | RTO < 30 min, RPO ≤ 24 h, 100 % de hashes correctos | Restauración verificada: **21/21 tablas, 29/29 documentos (SHA-256), APP_KEY recuperada y 60/60 solicitudes descifradas**. RTO medido de **1 s** en la base de prueba y **11 s** con 50 000 actas; RPO ≤ 24 h | ✅ Cumple |

## 1. Entorno de medición

| Elemento | Valor |
|---|---|
| Equipo | Portátil Intel Core i5-12450HX: 4 núcleos de rendimiento + 4 de eficiencia, 12 hilos; Windows 11 Pro; antivirus activo |
| Servidor web | nginx de Laravel Herd delante de **20 procesos PHP 8.4 (FastCGI)**, levantado con [`codigo/tests/carga/servidor-carga.ps1`](../codigo/tests/carga/servidor-carga.ps1) |
| Aplicación | Copia del código del Sprint 2 con `APP_DEBUG=false`, configuración, rutas y vistas en caché (`php artisan optimize`), sesiones en MySQL, cola `database` |
| Base de datos | MySQL 9.7.1 en el mismo equipo, base `registro_civil_carga`: **50 000 actas** y 10 000 solicitudes |
| Herramienta | **k6 v2.4.0** en el mismo equipo. Sus procesos compiten por la CPU con el servidor: es un límite de la prueba |
| Límites de intentos | Activos en la aplicación. Solo las peticiones de k6 los omiten, con la cabecera secreta `X-Prueba-Carga` (ver SPEC-13) |

Herd atiende con 5 procesos PHP fijos (ver AC03 del Sprint 1). Para medir algo parecido a un despliegue real se
levantó nginx con un número configurable de procesos PHP, usando los binarios de Herd y sin modificar su
configuración.

## 2. EQ-01 Rendimiento y EQ-03 Escalabilidad: carga progresiva hasta 2 000 usuarios

**Script:** [`codigo/tests/carga/eq-progresiva.js`](../codigo/tests/carga/eq-progresiva.js), ejecutado con
[`ejecutar-progresiva.ps1`](../codigo/tests/carga/ejecutar-progresiva.ps1). Este último mide además la CPU y la
RAM por proceso cada 2 s.

**Diseño:**
- **Niveles:** 50, 100, 250, 500, 1 000, 1 500 y 2 000 usuarios simultáneos.
- **Etapas:** cada nivel sube durante 30 s y se mantiene 90 s. Solo se mide la meseta, y cada petición
  lleva la etiqueta de su nivel.
- **Ciudadanos:** crecen con cada nivel. Cada ciudadano abre el portal, busca su acta, abre el seguimiento
  y pide su código. Piensa de 3 a 8 s entre pasos y cada iteración es una persona nueva (sin cookies).
- **Personal:** son siempre 10 personas, como en la municipalidad. Buscan en el archivo por nombre y por
  número y revisan la bandeja, con 2 a 5 s entre pasos.
- **Meta por nivel:** p95 ≤ 2 s y menos del 1 % de peticiones fallidas.

### 2.1 Resultado final

Evidencia: [`eq-progresiva-final.txt`](evidencias/eq-progresiva-final.txt),
[`eq-progresiva-final-resumen.json`](evidencias/eq-progresiva-final-resumen.json),
[`eq-progresiva-final-recursos.json`](evidencias/eq-progresiva-final-recursos.json).

| Usuarios | Pet./s | p95 total | Búsqueda pública | Seguimiento | Archivo (personal) | Bandeja (personal) | Fallidas | Meta p95 ≤ 2 s |
|---:|---:|---:|---:|---:|---:|---:|---:|---|
| 50 | 9,9 | 423 ms | 91 ms | 436 ms | 492 ms | 175 ms | 0,00 % | ✅ |
| 100 | 18,6 | 415 ms | 122 ms | 428 ms | 660 ms | 242 ms | 0,18 % | ✅ |
| 250 | 43,0 | **573 ms** | 354 ms | 552 ms | 3 483 ms | 1 312 ms | 0,36 % | ✅ total · ⚠️ archivo |
| 500 | 55,0 | 4 592 ms | 4 175 ms | 4 654 ms | 8 539 ms | 4 914 ms | 3,21 % | ❌ |
| 1 000 | 73,8 | 10 179 ms | 10 138 ms | 10 190 ms | 10 915 ms | 10 633 ms | 31,64 % | ❌ |
| 1 500 | 96,1 | 13 577 ms | 11 931 ms | 13 137 ms | 9 431 ms | 22 342 ms | 28,49 % | ❌ |
| 2 000 | 89,9 | 12 354 ms | 13 393 ms | 12 872 ms | 12 408 ms | 13 388 ms | 35,36 % | ❌ |

**Totales:** 51 491 peticiones, se alcanzaron 2 000 usuarios virtuales y el inicio de sesión del personal
funcionó 10 de 10 veces. Las peticiones fallidas son, sobre todo:
- conexiones rechazadas (6 091), porque se llenó la cola de conexiones de nginx;
- conexiones cerradas por el servidor (1 693 + 1 666 EOF).

Solo 8 peticiones agotaron los 30 s. **Ninguna** falló con error de la aplicación: el log no registró
excepciones durante la prueba.

**Recursos por nivel:** a partir de 250 usuarios la CPU queda en ~45 % del total y PHP en ~16–20 %. MySQL usa
~3 % y nginx ~1–2 %. k6 llega a 14 % en los niveles más altos. La RAM se mantiene estable en ~19–20 GB del
equipo.

### 2.2 Lectura honesta del resultado

- **EQ-01 se cumple con margen hasta 250 usuarios simultáneos:** p95 total 0,57 s, menos de un tercio de la meta.
  La excepción es la **búsqueda del archivo por nombre**, que hace el personal: cumple hasta 100 usuarios
  (0,66 s) y a 250 sube a 3,5 s. Es la consulta más pesada, porque busca por texto entre 50 000 actas, y es
  la primera que siente la competencia por la CPU.
- **EQ-03 a 2 000 usuarios:** el sistema no se cae ni corrompe datos. Las verificaciones de contenido siguen
  acertando cuando la petición llega: por ejemplo, la búsqueda encuentra el acta correcta. Pero desde 500
  usuarios un solo equipo **no da abasto**:
  - las peticiones por segundo crecen poco, de 55 a 96;
  - la latencia sube a 5–14 s;
  - nginx empieza a rechazar conexiones.

  Llegar a 2 000 usuarios con p95 ≤ 2 s requiere repartir la carga entre varios servidores. Es decisión
  del Sprint 3 (ver §8).
- 2 000 usuarios a la vez es una cifra muy alta para un registro civil distrital. Los 250 usuarios
  simultáneos que sí se cumplen equivalen a ~43 peticiones por segundo, unas 150 000 por hora.

### 2.3 Cuellos de botella encontrados y corregidos

La primera corrida, con el mismo equipo y 20 procesos PHP
([`eq-progresiva-20php-resumen.json`](evidencias/eq-progresiva-20php-resumen.json)), cumplía solo hasta 100
usuarios. Se estancaba en **~46 peticiones por segundo con la CPU al 42 %**: el servidor esperaba algo que no
era la CPU. Se investigó paso a paso con pruebas de capacidad (40 usuarios sin pausas contra una sola ruta):

| # | Hallazgo | Cómo se midió | Corrección | Efecto |
|---|---|---|---|---|
| 1 | nginx en Windows servía solo **~160 archivos estáticos por segundo** (`robots.txt`); `php -S` servía 547 | Misma prueba contra los dos servidores | `open_file_cache`, `sendfile` y `multi_accept` en la configuración de nginx (`servidor-carga.ps1`) | **1 435 archivos/s** (×9) |
| 2 | **bcrypt dentro de un bloqueo de fila**: al pedir el código de seguimiento, `CodigoTramiteService::enviar()` calculaba el hash del código (~200 ms, lento a propósito) con la fila del trámite bloqueada (`lockForUpdate`). Las demás peticiones para la misma persona esperaban | `Innodb_row_lock_time` subió de 89 s a **405 s** (espera media 1,9 s, máxima 9,5 s) en dos corridas | El hash se calcula **antes** de abrir la transacción. Si el último envío es reciente, se rechaza sin calcularlo. El bloqueo solo cubre la comprobación y la escritura | Las esperas por bloqueo durante la prueba bajaron a **0** |
| 3 | **Windows 11 llevaba los procesos PHP a los núcleos de eficiencia.** Los procesos sin ventana se tratan como trabajo de fondo: los 4 núcleos de eficiencia estaban al 100 % y los de rendimiento al ~40 % | Uso por núcleo durante la carga, con `/api/v1/salud`: el techo de ~150 pet./s ya se alcanzaba con 5 usuarios | Prioridad `AboveNormal` para PHP y nginx al levantarlos (`servidor-carga.ps1`) | **160 → 376 pet./s** (×2,3) |

**Experimento descartado:** se probó subir a 48 procesos PHP
([`eq-progresiva-48php-resumen.json`](evidencias/eq-progresiva-48php-resumen.json)) y el resultado fue **peor**.
Más procesos competían por los mismos núcleos y por el bloqueo del hallazgo 2. Más procesos no compensan un
cuello de botella que no es la cantidad de procesos.

El hallazgo 2 es una **mejora real del código**, no solo del entorno. Su riesgo existe igual en Linux: bajo
carga, cualquier proceso que retiene un bloqueo mientras hace un cálculo lento bloquea a los demás. Los
hallazgos 1 y 3 son propios del servidor de pruebas en Windows. Un despliegue en Linux con PHP-FPM no los tiene,
pero se documentan para que la medición se pueda repetir.

## 3. EQ-02 Disponibilidad: fallos de los proveedores

**Pruebas:** [`codigo/tests/Feature/DisponibilidadProveedoresTest.php`](../codigo/tests/Feature/DisponibilidadProveedoresTest.php).
Cada proveedor se hace fallar de tres formas: sin conexión, error 500 y error 503. Se revisa qué ve la persona y
qué quedó guardado.

| Fallo simulado | Casos | Respuesta | Qué se comprueba en la base | Resultado |
|---|---|---|---|---|
| APIsPerú (contraste del DNI) no responde | 3 | «La consulta DNI no está disponible… no se ha generado ningún cobro» | 0 solicitudes, 0 pagos, 0 correos | ✅ 3/3 |
| El servidor de correo falla al enviar el código | 1 | «No se pudo enviar el código. Puedes solicitar un reenvío…» | La solicitud queda **sin verificar**, sin pago, y no se puede pagar (403) | ✅ 1/1 |
| Mercado Pago no responde al abrir el pago | 3 | «No se pudo abrir Mercado Pago. La solicitud se conserva…» | Orden en `CREANDO` sin enlace de pago; solicitud en `PENDIENTE_PAGO` sin eventos | ✅ 3/3 |
| Mercado Pago no responde al pulsar «Ya pagué, comprobar» | 3 | «No se pudo consultar a Mercado Pago… no realices otro pago» | Pago `PENDIENTE`, sin `pagado_at` | ✅ 3/3 |
| El navegador vuelve con `status=approved` pero Mercado Pago no lo respalda | 3 | Vuelve al estado **sin** «pago confirmado» | Pago `PENDIENTE` | ✅ 3/3 |
| Aviso (webhook) firmado con Mercado Pago caído | 3 | Responde 5xx para que Mercado Pago **reintente** más tarde | Pago `PENDIENTE` | ✅ 3/3 |
| MySQL caído | 1 | `/api/v1/salud` responde **503** `degradado` y `base_datos: error`, sin `SQLSTATE` ni direcciones internas | — | ✅ 1/1 |
| **Total** | **17** | | **0 pagos confirmados, 0 aprobaciones, 0 entregas** | ✅ **17/17** |

Estas pruebas completan las que ya existían para Yape y las firmas:
- `YapeOrdersTest::test_tras_un_timeout_reenvia_con_la_misma_clave_de_idempotencia` y
  `test_rechazo_no_aprueba_y_permite_reintentar_con_yape_o_tarjeta`;
- el aviso de Mercado Pago con firma falsa (`MercadoPagoTest::test_webhook_valida_firma_y_confirma_una_sola_vez`).

La disponibilidad mensual y el horario de servicio siguen **por acordar** con la municipalidad: dependen del
despliegue (Sprint 3).

## 4. EQ-04 Seguridad

Detalle completo en [`codigo/docs/calidad/AC04-seguridad.md`](../codigo/docs/calidad/AC04-seguridad.md).

| Control | Resultado |
|---|---|
| Matriz de autorización con Postman/Newman: panel sin sesión, portal sin verificar, CSRF, archivos internos, límites de intentos, asistente contra funciones de administrador, solicitudes ajenas | **53 peticiones, 71 verificaciones, 0 fallidas** ✅ |
| Dependencias (`composer audit`) | **0 vulnerabilidades** ✅ (también en CI, en cada cambio) |
| Cabeceras de seguridad, sin versión de PHP expuesta, errores sin trazas | ✅ (`EndurecimientoTest`) |
| Datos personales cifrados en la base y DNI buscado por HMAC | ✅ |
| Escaneo automático OWASP ZAP | ⏳ **Pendiente.** No se presenta resultado hasta ejecutarlo |

## 5. EQ-05 Mantenibilidad

| Indicador | Resultado |
|---|---|
| Pruebas automáticas | **173 pruebas, 1 700 verificaciones, 0 fallos**: 156 del cierre de la Fase 6 más las 17 de EQ-02 |
| Cobertura | 84,15 % al cierre de la Fase 6; el mínimo del 80 % lo controla la CI ([`05-pruebas-cobertura-y-ci.md`](05-pruebas-cobertura-y-ci.md)) |
| Análisis estático | PHPStan/Larastan nivel 5: **sin errores** |
| Integración continua | GitHub Actions: pruebas en SQLite y MySQL, cobertura, PHPStan, auditoría y Newman |

**Ejemplo real de cambio de regla:** la corrección del hallazgo 2 (§2.3) cambió **un solo archivo**,
`app/Services/CodigoTramiteService.php`. El envío de códigos solo vive en ese servicio. Las pruebas del portal y
del seguimiento pasaron sin tocarlas, que es lo que pide el escenario EQ-05.

## 6. EQ-06 Integridad: decisiones simultáneas

**Script:** [`codigo/tests/carga/ac05-concurrencia.js`](../codigo/tests/carga/ac05-concurrencia.js), repetido sobre el
código del Sprint 2 (en el Sprint 1 se midió sobre el código anterior a la refactorización).

**Diseño:**
- Hay 5 solicitudes pagadas y pendientes (N.º 9912, 9918, 9919, 9925 y 9926).
- 10 personas del personal por solicitud, 50 en total, inician sesión y abren la solicitud con la misma
  versión.
- **En el mismo instante** envían su decisión: la mitad aprueba y la otra mitad rechaza.

**k6** ([`eq06-concurrencia.txt`](evidencias/eq06-concurrencia.txt)):

| Métrica | Esperado | Obtenido |
|---|---|---|
| Decisiones aplicadas | 5 (una por solicitud) | **5** ✅ |
| Decisiones rechazadas («La solicitud cambió») | 45 | **45** ✅ |
| Respuestas inesperadas | 0 | **0** ✅ |

**MySQL** ([`eq06-concurrencia-bd.txt`](evidencias/eq06-concurrencia-bd.txt)): cada solicitud tiene **exactamente
una** decisión, su versión subió una sola vez (1 → 2) y tiene **una** auditoría `REVISAR_TRAMITE`. Quedaron 5
avisos al ciudadano en la cola de correo, uno por decisión.

**Dos problemas del entorno, encontrados y resueltos durante la medición:**
- **La cola de correo estaba en `sync`.** En la copia de carga se había configurado así por error, mientras
  que el proyecto usa `database` (`.env.example`). Con `sync`, el correo al ciudadano se generaba dentro de la
  misma petición. Tres decisiones ya guardadas respondieron con error 500, aunque la base de datos ya mostraba
  una sola decisión por solicitud. Se repitió con `database`, como en el despliegue real.
- **Las plantillas Blade chocaban al compilarse en Windows.** Cuando 50 procesos compilan la misma plantilla
  por primera vez, Windows rechaza el renombrado del archivo compilado («Acceso denegado»). El script ahora
  hace una visita previa en `setup()`. En Linux esto no ocurre.

En todas las corridas, incluidas las de esos dos problemas, la regla de integridad se mantuvo: **nunca hubo dos
decisiones sobre la misma solicitud**.

## 7. EQ-07 Auditoría y EQ-08 Recuperabilidad

### EQ-07

| Prueba / evidencia | Qué comprueba |
|---|---|
| `FiliacionTest::test_actualiza_solo_filiacion_con_historial_y_no_sobrescribe_version_antigua` | La corrección de la filiación guarda los datos anteriores y nuevos, el operador y la fecha, y no cambia el acta central ni su PDF |
| `AuditoriaTest` (inicio de sesión, crear acta, solo el administrador ve la auditoría) | Responsable, acción y fecha en `auditoria`, y acceso restringido |
| EQ-06 en MySQL | 5 de 5 decisiones con su auditoría `REVISAR_TRAMITE` |

La bitácora no se presenta como criptográficamente inmutable (limitación ya declarada en el Sprint 1).

### EQ-08

**Script:** [`codigo/tests/recuperacion/simulacro-restauracion.ps1`](../codigo/tests/recuperacion/simulacro-restauracion.ps1),
que llama a [`verificar-restauracion.php`](../codigo/tests/recuperacion/verificar-restauracion.php). Pasos:

1. Crea un respaldo real con el comando del sistema, `php artisan respaldo:crear`: base de datos, documentos
   privados y APP_KEY cifrada con una frase.
2. Lo restaura en **otra base de datos y otra carpeta**, como en un equipo nuevo, y mide el tiempo.
3. Verifica:
   - tabla por tabla: filas y `CHECKSUM TABLE`;
   - documento por documento: SHA-256;
   - que la APP_KEY se recupere con la frase y que una frase incorrecta se rechace;
   - que **cada solicitud cifrada se pueda leer** con la clave recuperada y su índice de DNI se reconstruya.

   Las claves solo se comparan por su huella; nunca se muestran.

Resultado ([`eq08-restauracion.json`](evidencias/eq08-restauracion.json), base `registro_civil_sprint2_prueba`):

| Comprobación | Resultado |
|---|---|
| Tablas de negocio idénticas (filas y checksum) | **21 de 21** ✅ (sesiones y caché se excluyen: cambian mientras el sistema se usa) |
| Documentos idénticos (SHA-256) | **29 de 29** ✅ |
| APP_KEY recuperada y correcta / frase incorrecta rechazada | ✅ / ✅ |
| Solicitudes descifradas con la clave recuperada | **60 de 60**, 0 fallidas ✅ |
| Índice de DNI reconstruido correctamente | **60 de 60** ✅ |
| Respaldo / restauración (RTO) | 1 s / **1 s** |

**RTO con más datos** ([`eq08-rto-50k.txt`](evidencias/eq08-rto-50k.txt)): la base de carga, con 50 000 actas y
10 000 solicitudes (31 MB), se vuelca en ~1 s y se restaura en **11,2 s**, con filas y checksums idénticos. La
meta es **< 30 min**: queda muy por debajo. El tiempo real dependerá de descargar el respaldo desde donde se
guarde y de reinstalar la aplicación, que corresponde al Sprint 3.

**RPO:** el programador ejecuta `respaldo:crear` cada hora y genera un respaldo nuevo cuando el último tiene más
de 20 h (`routes/console.php`). La pérdida máxima de datos es, por tanto, de **≤ 24 h**, que es la meta. Se
verificó en la configuración; no se observó durante días.

## 8. Recomendaciones para el Sprint 3 (no implementadas aquí)

Las mediciones indican qué haría falta para pasar de 250 a 2 000 usuarios con p95 ≤ 2 s. Esto es despliegue y
**se deja para el Sprint 3**:

1. **Servidor Linux con PHP-FPM** y nginx. Elimina los hallazgos 1 y 3, que son propios de Windows, y permite
   ajustar los procesos a los núcleos reales.
2. **Escalado horizontal:** varias instancias de la aplicación detrás de un balanceador. El monolito ya lo
   permite: las sesiones están en la base de datos, el caché es compartible, el respaldo usa `onOneServer()` y
   no hay estado en memoria.
3. **Caché y sesiones en Redis**, en lugar de archivos y MySQL, cuando haya más de una instancia.
4. **MySQL en su propio servidor**, para que no compita con PHP por la CPU.
5. **Búsqueda del archivo por nombre:** si con el volumen real sigue siendo la consulta más lenta, evaluar un
   índice de texto completo (FULLTEXT). Ver [`03-base-de-datos-y-cache.md`](03-base-de-datos-y-cache.md).
6. **Generador de carga en otra máquina**, para que k6 no reste CPU al servidor medido.

## 9. Cómo repetir las mediciones

```powershell
cd codigo
# 1. Servidor de carga (copia del código con APP_DEBUG=false, PRUEBAS_CARGA_TOKEN y base registro_civil_carga)
powershell -ExecutionPolicy Bypass -File tests\carga\servidor-carga.ps1 -Raiz C:\ruta\copia\codigo -Trabajadores 20
# 2. EQ-01 / EQ-03: carga progresiva hasta 2 000 usuarios (~15 min)
powershell -ExecutionPolicy Bypass -File tests\carga\ejecutar-progresiva.ps1 -Token <PRUEBAS_CARGA_TOKEN> -Etiqueta eq-progresiva-final
# 3. EQ-06: decisiones simultáneas sobre 5 solicitudes pagadas y pendientes
k6 run -e BASE=http://127.0.0.1:8020 -e TOKEN=<token> -e TRAMITES=<5 números> tests\carga\ac05-concurrencia.js
# 4. Detener el servidor de carga
powershell -ExecutionPolicy Bypass -File tests\carga\servidor-carga.ps1 -Detener
# 5. EQ-02, EQ-05 y EQ-07: pruebas automáticas
php artisan test
# 6. EQ-08: simulacro de restauración (solo bases cuyo nombre contenga "prueba" o "carga")
powershell -ExecutionPolicy Bypass -File tests\recuperacion\simulacro-restauracion.ps1 -Base registro_civil_sprint2_prueba
```

El token de pruebas de carga, la APP_KEY y la frase del respaldo **nunca** se escriben en estos documentos ni en
las evidencias.
