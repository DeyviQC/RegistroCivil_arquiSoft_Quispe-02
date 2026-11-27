# AC05 — Integridad documental y transaccional

**Escenario:** rechazar duplicados y decisiones con versión desactualizada; conservar hash del PDF,
numeración y acta central al revisar filiación; verificar hash del firmado al publicar, descargar y
enviar por correo, y rechazar confirmaciones de pago no válidas.

**Meta:** 100 % de los casos rechazados correctamente; con decisiones simultáneas sobre la misma
solicitud, **solo una** se aplica. Se verifica en SQLite **y en MySQL** (motor de producción).

**Veredicto: ✅ CUMPLE**

## 1. Datos de la ejecución

| Campo | Valor |
|---|---|
| Fecha | 26/11/2026 |
| Herramientas | PHPUnit 11 (`php artisan test`) · k6 v2.4.0 |
| Motores | SQLite en memoria **y** MySQL 9.7 (base separada `registro_civil_prueba`) |
| Concurrencia | k6 contra Laravel Herd y la base `registro_civil_carga` |
| Equipo | Intel Core i5-12450HX, 23.7 GB RAM, Windows 11 Pro |

## 2. Suite automática en ambos motores

| Motor | Pruebas | Verificaciones | Resultado | Duración | Evidencia |
|---|---|---|---|---|---|
| SQLite (memoria) | 128 | 1 349 | ✅ 128 / 128 | 26.7 s | [`ac05-phpunit-sqlite.txt`](evidencias/ac05-phpunit-sqlite.txt) |
| **MySQL 9.7** | 128 | 1 349 | ✅ 128 / 128 | 46.1 s | [`ac05-phpunit-mysql.txt`](evidencias/ac05-phpunit-mysql.txt) |

Comprobado que la corrida en MySQL usó MySQL de verdad: la base `registro_civil_prueba` quedó con
22 tablas y 25 migraciones aplicadas. Desde ahora **GitHub Actions ejecuta la suite en ambos motores**
en cada cambio (`.github/workflows/tests.yml`).

> Esto responde a la limitación del documento de atributos («las pruebas usan SQLite y no demuestran
> funcionamiento en MySQL»): la misma suite pasa en MySQL.

## 3. Criterios del escenario y pruebas que los verifican

| Criterio | Prueba(s) | Resultado |
|---|---|---|
| Rechazar acta duplicada por número, año y tipo | `ActaTest::test_no_permite_acta_duplicada_por_numero_anio_tipo` | ✅ |
| Rechazar PDF duplicado (mismo hash) | `ActaTest::test_detecta_pdf_duplicado_por_hash` | ✅ |
| Rechazar archivos que no son PDF | `ActaTest::test_rechaza_archivo_que_no_es_pdf` | ✅ |
| Acta registrada no modificable | `ActaTest::test_acta_registrada_no_puede_modificarse` | ✅ |
| Rechazar decisiones con versión desactualizada | `MejorasFlujoTest::test_una_decision_con_version_desactualizada_se_rechaza_sin_cambiar_nada` (añadida en esta verificación) · `EntregaDocumentoTest::test_bloquea_preparacion_sin_aprobacion_o_sin_pago_y_version_obsoleta` · `MejorasFlujoTest::test_transicionar_sube_la_version_y_registra_el_evento` | ✅ |
| Transiciones de estado no permitidas | `MejorasFlujoTest::test_las_transiciones_no_permitidas_se_rechazan_en_un_solo_lugar` | ✅ |
| Filiación: conserva hash del PDF, numeración y acta central; no sobrescribe versión antigua | `FiliacionTest::test_actualiza_solo_filiacion_con_historial_y_no_sobrescribe_version_antigua` | ✅ |
| Hash del firmado al **cargar**: rechaza PDF sin firma o modificado | `EntregaDocumentoTest::test_rechaza_archivo_sin_firma_y_no_habilita_descarga_de_otra_solicitud` · `test_integridad_rechaza_documento_distinto_o_modificado` | ✅ |
| Hash del firmado al **publicar y descargar** | `EntregaDocumentoTest::test_flujo_carga_revision_y_descarga_con_correo_verificado` · `test_integridad_rechaza_documento_distinto_o_modificado` | ✅ |
| Hash del firmado al **enviar por correo**, sin doble envío | `CanalEntregaTest::test_envia_solo_el_firmado_publicado_al_correo_verificado_y_evita_doble_envio` | ✅ |
| Copia firmada reutilizada solo si el original no cambió | `CopiaFirmadaTest::test_si_el_original_cambia_o_la_copia_vence_hay_que_firmar_de_nuevo` | ✅ |
| Rechazar confirmaciones de pago con firma falsa | `MercadoPagoTest::test_webhook_valida_firma_y_confirma_una_sola_vez` | ✅ |
| Rechazar importe, moneda, cuenta o reembolso no válidos aunque vengan firmados | `MercadoPagoTest::test_rechaza_importe_moneda_cuenta_produccion_y_reembolso_aunque_este_firmado` | ✅ |
| No confiar en el estado que envía el navegador al volver del pago | `MercadoPagoTest::test_retorno_no_confia_en_status_y_no_confirma_otra_solicitud` | ✅ |
| Yape: solo órdenes verificadas, importe correcto, sin duplicar órdenes | `YapeOrdersTest::test_confirma_solo_orden_verificada…` · `test_orden_con_importe_incorrecto_no_confirma_ni_se_recrea` · `test_tras_un_timeout_reenvia_con_la_misma_clave_de_idempotencia` | ✅ |

## 4. Prueba de concurrencia (k6)

**Diseño:** [`tests/carga/ac05-concurrencia.js`](../../tests/carga/ac05-concurrencia.js). Para **5 solicitudes**
pendientes (N.º 2, 8, 22, 30 y 44), 10 usuarios del personal cada una (**50 en total**) inician sesión,
abren la solicitud con la misma versión y, **en el mismo instante**, envían su decisión: la mitad aprueba
y la otra mitad rechaza.

**Resultado de k6** ([`ac05-concurrencia.txt`](evidencias/ac05-concurrencia.txt)):

| Métrica | Esperado | Obtenido |
|---|---|---|
| Decisiones aplicadas | 5 (una por solicitud) | **5** ✅ |
| Decisiones rechazadas («La solicitud cambió») | 45 | **45** ✅ |
| Respuestas inesperadas | 0 | **0** ✅ |

**Verificación en la base de datos** ([`ac05-concurrencia-bd.txt`](evidencias/ac05-concurrencia-bd.txt)):

| Solicitud | Estado final | Versión | Decisiones registradas | Auditorías `REVISAR_TRAMITE` |
|---|---|---|---|---|
| N.º 2 | APROBADA | 2 | 1 | 1 |
| N.º 8 | APROBADA | 2 | 1 | 1 |
| N.º 22 | APROBADA | 2 | 1 | 1 |
| N.º 30 | RECHAZADA | 2 | 1 | 1 |
| N.º 44 | APROBADA | 2 | 1 | 1 |

Cada solicitud tiene **exactamente una** decisión, su estado coincide con su evento, la versión subió una
sola vez (1 → 2) y hay una sola auditoría. El mecanismo es el bloqueo de fila (`lockForUpdate`) más la
comparación de versión dentro de la transacción (`TramitePublico::transicionar()`).

## 5. Observaciones

- Las pruebas usan proveedores **simulados** (Mercado Pago, Firma Perú): verifican que el sistema rechaza
  lo inválido, no la interoperabilidad real con el proveedor.
- La verificación de firma comprueba **integridad criptográfica**; la confianza en el certificado,
  su revocación y las facultades del firmante requieren revisión institucional (limitación declarada).

## 6. Cómo repetir

```powershell
php artisan test                                    # SQLite
$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='registro_civil_prueba'; php artisan test   # MySQL (base dedicada)
# Concurrencia: .env temporal como en AC01 y luego
k6 run -e BASE=http://registro-civil.test -e TOKEN=<token> -e TRAMITES=2,8,22,30,44 tests/carga/ac05-concurrencia.js
```
