# SPEC-12 — API REST v1 documentada con OpenAPI

| Campo | Valor |
|---|---|
| Historias | HU06, HU08 (acceso por sistemas externos) |
| Requisitos | RF19 |
| Decisiones | ADR-029 |
| Atributos | AC04 Seguridad, AC07 Mantenibilidad |
| Estado | ✅ Implementado y probado (Sprint 2) |

## Objetivo
Exponer las operaciones principales como una API REST versionada (`/api/v1`), autenticada con token
Bearer, documentada con OpenAPI 3 y Swagger UI, y que **reutilice las mismas reglas** que el panel web.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-12.1 | cualquier cliente | consulta `GET /api/v1/salud` | recibe 200 con el estado de la base de datos y del almacenamiento, sin autenticarse |
| CA-12.2 | personal activo | envía usuario y clave a `POST /auth/token` | recibe 201 con un token que vence en 480 min; usuarios inactivos o ciudadanos no reciben token |
| CA-12.3 | 5 intentos fallidos | se intenta de nuevo | se bloquea por usuario + IP |
| CA-12.4 | una petición sin token, con token revocado o vencido | llega a una ruta protegida | responde 401 en JSON (nunca redirección HTML) |
| CA-12.5 | un token válido | consulta `GET /actas` | obtiene actas paginadas y filtrables **sin la ruta del PDF** |
| CA-12.6 | un token válido | consulta `GET /tramites` | solo ve trámites pagados y sin código de acceso, correo, DNI ni PDF |
| CA-12.7 | un token válido | decide con `POST /tramites/{id}/decision` | se aplica `DecisionTramiteService`: mismas transiciones, conflicto 409 por versión, validación 422 |
| CA-12.8 | un ciudadano | consulta `POST /consultas/disponibilidad` | sabe si hay coincidencia sin ver datos del archivo (límite 5/min) |
| CA-12.9 | el contrato `docs/api/openapi.yaml` | se ejecutan las pruebas | cada ruta implementada está documentada y viceversa; las protegidas declaran Bearer y 401 |
| CA-12.10 | el personal | abre `/api/documentacion` | ve Swagger UI servido localmente, sin CDN |

## Diseño técnico
| Capa | Componentes |
|---|---|
| Rutas | `routes/api.php` (prefijo `v1`, nombres `api.v1.*`, `throttle` por ruta) |
| Seguridad | Laravel Sanctum, middleware `ForzarRespuestaJson`, `role:administrador,asistente`, `CabecerasSeguridad` |
| Control | `Api\V1\SaludController`, `ConsultaPublicaController`, `AutenticacionController`, `ActaController`, `TramiteController` |
| Presentación | `ActaResource`, `TramiteResource` (filtrado de campos privados) |
| Negocio | `DecisionTramiteService`, `BusquedaPublicaService` (compartidos con la web) |
| Contrato | `docs/api/openapi.yaml`, `resources/views/api/documentacion.blade.php` |

## Tareas técnicas
- ✅ Tokens Sanctum con caducidad y revocación.
- ✅ Extracción de la regla de decisión a un servicio compartido.
- ✅ Contrato OpenAPI y Swagger UI local.
- ✅ Colección Postman (18 peticiones, 38 aserciones) ejecutada con Newman.

## Verificación
| Criterio | Prueba |
|---|---|
| CA-12.1 | `ApiV1Test::test_salud_es_publica_y_reporta_sus_componentes` |
| CA-12.2 | `ApiV1Test::test_el_token_se_emite_solo_al_personal_activo_y_caduca` |
| CA-12.3 | `ApiV1Test::test_cinco_intentos_fallidos_bloquean_el_usuario` |
| CA-12.4 | `ApiV1Test::test_sin_token_o_con_token_revocado_o_vencido_no_hay_acceso` |
| CA-12.5 | `ApiV1Test::test_actas_se_buscan_y_consultan_sin_exponer_el_pdf` |
| CA-12.6 | `ApiV1Test::test_tramites_solo_pagados_y_sin_datos_privados` |
| CA-12.7 | `ApiV1Test::test_decidir_por_la_api_usa_la_misma_regla_que_el_panel` |
| CA-12.8 | `ApiV1Test::test_consulta_publica_de_disponibilidad_no_revela_el_archivo` |
| CA-12.9 | `ContratoOpenApiTest::test_el_contrato_es_openapi_3_y_tiene_seguridad_bearer`, `ContratoOpenApiTest::test_cada_ruta_implementada_esta_documentada_y_viceversa`, `ContratoOpenApiTest::test_las_rutas_protegidas_declaran_token_y_respuesta_401` |
| CA-12.10 | `ContratoOpenApiTest::test_swagger_ui_y_el_contrato_se_sirven_desde_el_sistema` |
| Todos | Colección `tests/api/registro-civil-api.postman_collection.json`, evidencia `sprint-2/evidencias/api-postman-newman.html` |

## Fuera de alcance
Registro de actas y firma por la API (siguen siendo operaciones del panel con revisión humana).
