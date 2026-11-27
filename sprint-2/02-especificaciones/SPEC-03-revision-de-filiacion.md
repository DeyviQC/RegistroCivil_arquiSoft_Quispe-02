# SPEC-03 — Revisión de filiación con historial

| Campo | Valor |
|---|---|
| Historias | HU13 |
| Requisitos | RF15, RF11 |
| Decisiones | ADR-012, ADR-023 |
| Atributos | AC05 Integridad, AC06 Trazabilidad |
| Estado | ✅ Implementado y probado |

## Objetivo
Completar o corregir la **transcripción** de los datos de padre y madre de un nacimiento sin tocar el acta
central, su numeración ni su PDF, dejando historial de cada cambio.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-03.1 | un nacimiento con PDF y versión vigente | el personal guarda nombres y DNI de padres | solo cambian esos campos; el acta central, la numeración y el PDF quedan iguales |
| CA-03.2 | una corrección guardada | se revisa el historial | constan valores anteriores y nuevos, operador, fecha y hash de referencia del PDF |
| CA-03.3 | otra persona guardó antes | se envía el formulario con una versión antigua | se rechaza sin sobrescribir |
| CA-03.4 | un nacimiento nuevo | se registra | se exige indicar el estado de los cuatro campos de padres (registrado o desconocido) |
| CA-03.5 | una solicitud ciudadana de un padre o madre | se compara con la filiación | solo se compara el progenitor declarado y no se exponen sus datos |

## Reglas de negocio
- Estados explícitos por campo (`REGISTRADO`, `DESCONOCIDO`…); sin DNI ficticios.
- Control de versión (`filiacion_version`) con bloqueo de fila.
- El cruce de parentesco apoya la revisión humana; **nunca** autoriza por sí solo una entrega.

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `FiliacionController@edit/update`, `GuardarFiliacionRequest` |
| Negocio | `CruceParentescoService`, `AuditoriaService` |
| Datos | Campos de filiación en `acta_nacimientos` y tabla `filiacion_historial` |

## Tareas técnicas
- ✅ Formulario limitado a los campos autorizados.
- ✅ Historial con valores previos/nuevos y hash.
- ✅ Bloqueo optimista por versión.
- ✅ Cruce de parentesco con resultado `COINCIDE / NO_COINCIDE / SIN_DATOS / NO_APLICA`.

## Verificación
| Criterio | Prueba |
|---|---|
| CA-03.1, CA-03.2, CA-03.3 | `FiliacionTest::test_actualiza_solo_filiacion_con_historial_y_no_sobrescribe_version_antigua` |
| CA-03.4 | `FiliacionTest::test_nuevo_nacimiento_exige_revisar_los_cuatro_campos_de_padres` |
| CA-03.5 | `CruceParentescoTest::test_compara_solo_el_progenitor_declarado_y_no_expone_sus_datos`, `CruceParentescoTest::test_sin_resultados_es_distinto_de_fallo_de_servicio_y_nunca_es_contrastado` |

## Fuera de alcance
La bitácora no es criptográficamente inmutable (limitación declarada en AC06).
