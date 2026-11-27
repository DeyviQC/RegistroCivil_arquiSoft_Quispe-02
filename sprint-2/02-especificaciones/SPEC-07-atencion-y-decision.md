# SPEC-07 — Atención y decisión de solicitudes

| Campo | Valor |
|---|---|
| Historias | HU06 |
| Requisitos | RF17, RF11 |
| Decisiones | ADR-014, ADR-009, ADR-029 |
| Atributos | AC05 Integridad, AC06 Trazabilidad, AC07 Mantenibilidad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que el personal decida cada solicitud pagada (aprobar, observar o rechazar) **con una sola regla**, segura
ante decisiones simultáneas, compartida por el panel web y la API.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-07.1 | la bandeja del personal | se abre | solo muestra solicitudes con pago confirmado, filtrables por estado, número y DNI, sin exponer el código de acceso |
| CA-07.2 | una solicitud `PENDIENTE` | el personal la aprueba | pasa a `APROBADA` sin paso previo de «revisión» y sin exigir mensaje |
| CA-07.3 | una solicitud | se observa o rechaza sin motivo | se exige el motivo; hay plantillas de motivos frecuentes |
| CA-07.4 | un cambio no permitido (por ejemplo, a `LISTA`) | se intenta | se rechaza en un único lugar (`transicionar()`) |
| CA-07.5 | dos personas abren la misma versión | ambas deciden a la vez | se aplica **una sola** decisión; la otra recibe conflicto (409 en la API) y nada se sobrescribe |
| CA-07.6 | un tercero o un menor | se aprueba | se exige confirmar la revisión del vínculo y su sustento |
| CA-07.7 | una decisión aplicada | se revisa | hay un evento en el historial, una auditoría `REVISAR_TRAMITE` y el aviso en cola al ciudadano (salvo «pendiente») |

## Reglas de negocio
- Transiciones del personal: `PENDIENTE` → {`OBSERVADA`, `APROBADA`, `RECHAZADA`}; `OBSERVADA` → {`APROBADA`, `RECHAZADA`}.
- Bloqueo de fila (`lockForUpdate`) y comparación de `version` dentro de la transacción.
- Aprobar significa «pendiente de firma»: no firma ni entrega (SPEC-08).

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `AtencionPublicaController@index/show/atender`, `Api\V1\TramiteController@decidir`, `DecidirTramiteRequest` |
| Negocio | `DecisionTramiteService` (regla única), `TramitePublico::transicionar()`, `CruceParentescoService`, `AuditoriaService` |
| Errores | `ConflictoVersionException` (error de formulario en web, 409 en API) |

## Tareas técnicas
- ✅ Decisión con un clic y motivo solo al observar o rechazar.
- ✅ Plantillas de motivos (`config/portal.php`).
- ✅ Servicio compartido web/API (refactor Sprint 2).
- ✅ Prueba de concurrencia con 50 decisiones simultáneas (k6, AC05).

## Verificación
| Criterio | Prueba / evidencia |
|---|---|
| CA-07.1 | `BandejaManualTest::test_bandeja_solo_muestra_pagados_sin_exponer_uuid_y_filtra_estado_y_numero`, `EndurecimientoTest::test_la_bandeja_filtra_por_dni` |
| CA-07.2, CA-07.3 | `AvisosMantenimientoRespaldoTest::test_aprobar_no_exige_mensaje_pero_observar_o_rechazar_si`, `MejorasFlujoTest::test_el_personal_aprueba_directo_sin_iniciar_revision_y_ve_plantillas` |
| CA-07.4 | `MejorasFlujoTest::test_las_transiciones_no_permitidas_se_rechazan_en_un_solo_lugar`, `MejorasFlujoTest::test_transicionar_sube_la_version_y_registra_el_evento` |
| CA-07.5 | `MejorasFlujoTest::test_una_decision_con_version_desactualizada_se_rechaza_sin_cambiar_nada`, `ApiV1Test::test_decidir_por_la_api_usa_la_misma_regla_que_el_panel`, prueba k6 `tests/carga/ac05-concurrencia.js` |
| CA-07.6 | `PortalPublicoTest::test_padre_de_menor_puede_pagar_pero_no_aprobarse_sin_revision_del_vinculo` |
| CA-07.7 | `AvisosMantenimientoRespaldoTest::test_el_ciudadano_recibe_un_aviso_en_cola_cuando_el_personal_observa_su_solicitud`, `AvisosMantenimientoRespaldoTest::test_no_se_avisa_en_revision_ni_a_correos_sin_verificar` |
