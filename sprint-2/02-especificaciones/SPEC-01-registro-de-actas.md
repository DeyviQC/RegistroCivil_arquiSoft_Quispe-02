# SPEC-01 — Registro de actas existentes con PDF

| Campo | Valor |
|---|---|
| Historias | HU05 |
| Requisitos | RF02, RF03, RF04, RF05 |
| Decisiones | ADR-004, ADR-005, ADR-007, ADR-008, ADR-012 |
| Atributos | AC05 Integridad, AC06 Trazabilidad |
| Estado | ✅ Implementado y probado |

## Objetivo
Digitalizar actas de nacimiento, matrimonio y defunción **que ya existen en papel**, asociando sus datos al
PDF original escaneado, sin duplicados y sin poder alterar después el acta registrada.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-01.1 | un asistente autenticado | registra un acta de cualquiera de los tres tipos con datos completos y un PDF válido | el acta queda `REGISTRADA`, con el PDF en almacenamiento privado y su hash guardado |
| CA-01.2 | un acta con número, año y tipo ya registrados | se intenta registrar otra igual | se rechaza y no se guarda nada |
| CA-01.3 | un PDF cuyo contenido (hash) ya existe en el archivo | se intenta registrar con otra acta | se rechaza como duplicado y se audita el intento |
| CA-01.4 | un archivo que no es PDF | se adjunta al registrar | se rechaza con mensaje claro |
| CA-01.5 | faltan datos obligatorios del tipo de acta | se registra | se informan los datos faltantes además del PDF |
| CA-01.6 | el acta no consigna DNI | se registra un nacimiento o una defunción | se exige marcar la ausencia explícitamente; no se inventan números |
| CA-01.7 | un acta ya registrada | se intenta editar o eliminar | la operación se niega |
| CA-01.8 | actas históricas con sobre y correlativo propios | se registran | conservan su numeración sin retroceder la secuencia por tipo |

## Reglas de negocio
- PDF obligatorio, hasta 10 MB (`config/actas.php`).
- Unicidad: (número, año, tipo), (tipo, sobre, correlativo) y hash del PDF.
- Registro y archivo en una transacción: si falla la base de datos, se elimina el PDF guardado (compensación).
- El original nunca se modifica después del registro.

## Diseño técnico
| Capa | Componentes |
|---|---|
| Presentación | `resources/views/actas/create.blade.php`, `show.blade.php` |
| Control | `ActaController@store`, `StoreActaRequest` y Form Requests por tipo, `ActaPolicy` |
| Negocio | `ActaService::registrar()`, `PdfValidationService`, `PdfStorageService`, `AuditoriaService` |
| Datos | Tablas `actas`, `acta_nacimientos`, `acta_matrimonios`, `acta_defunciones`, `acta_secuencias` |

## Tareas técnicas
- ✅ Formulario por tipo con validación específica.
- ✅ Validación y guardado privado del PDF con hash SHA-256.
- ✅ Restricciones de unicidad en base de datos y servicio.
- ✅ Numeración por tipo y numeración histórica.
- ✅ Auditoría de creación e intentos duplicados.

## Verificación
| Criterio | Prueba |
|---|---|
| CA-01.1 | `ActaTest::test_asistente_puede_crear_acta_de_nacimiento_con_pdf_valido`, `ActaDocumentoTest::test_registro_simple_de_los_tres_tipos_con_pdf` |
| CA-01.2 | `ActaTest::test_no_permite_acta_duplicada_por_numero_anio_tipo` |
| CA-01.3 | `ActaTest::test_detecta_pdf_duplicado_por_hash` |
| CA-01.4 | `ActaTest::test_rechaza_archivo_que_no_es_pdf` |
| CA-01.5 | `ActaDocumentoTest::test_reporta_los_datos_faltantes_ademas_del_pdf_en_los_tres_tipos` |
| CA-01.6 | `ActaDocumentoTest::test_sin_dni_exige_excepcion_y_la_guarda_solo_en_nacimiento_y_defuncion` |
| CA-01.7 | `ActaTest::test_acta_registrada_no_puede_modificarse`, `ActaTest::test_acta_registrada_no_puede_eliminarse` |
| CA-01.8 | `NumeracionHistoricaTest::test_sobre_historico_con_31_matrimonios_conserva_su_numero`, `NumeracionHistoricaTest::test_historicos_conservan_orden_y_sobre_sin_retroceder_la_secuencia` |

## Fuera de alcance
Rectificaciones judiciales y modificación de registros oficiales externos (RC06, RC07).
