# SPEC-02 — Búsqueda, consulta e incidencias del archivo

| Campo | Valor |
|---|---|
| Historias | HU10 |
| Requisitos | RF13 |
| Decisiones | ADR-004, ADR-028 |
| Atributos | AC01 Rendimiento, AC03 Escalabilidad, AC04 Seguridad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que el personal encuentre rápidamente un acta, vea o imprima su PDF de forma autorizada y detecte las
actas con problemas documentales (sin PDF, históricas pendientes).

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-02.1 | actas registradas | el personal busca por número, año, tipo, nombre, DNI o rango de fechas | ve resultados paginados (15) que respetan los filtros |
| CA-02.2 | un acta registrada | el personal la consulta | ve su ficha y puede abrir el PDF mediante una ruta autorizada y auditada |
| CA-02.3 | una persona sin sesión o sin rol del personal | intenta ver el archivo o un PDF | se le niega el acceso |
| CA-02.4 | actas históricas o fichas sin PDF | se abre la vista de incidencias | aparecen sin duplicados y filtrables por situación |
| CA-02.5 | una incidencia de acta sin PDF | se adjunta el original | el aviso se resuelve y se conserva el antecedente |
| CA-02.6 | un documento de incidencia | se solicita con una ruta externa o sin permiso | se rechaza |
| CA-02.7 | 50 000 actas | se busca por nombre | la consulta responde en ~0.14 s (antes ~0.8 s) gracias a índices y consultas reescritas |

## Reglas de negocio
- Los `%`, `_` y `\` escritos por el usuario se tratan como texto literal en las búsquedas.
- El PDF se sirve solo por rutas autorizadas, con `nosniff` y sin caché; nunca desde una URL pública.

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `ActaController@index/show`, `PdfController@ver/vista`, `IncidenciaController` |
| Negocio | `ActaService::buscar()`, `IncidenciaService`, `PdfStorageService` |
| Datos | Índices en fechas de detalle y `actas.estado` (migración `2026_11_26_000025`) |
| API | `GET /api/v1/actas`, `GET /api/v1/actas/{acta}` (SPEC-12) |

## Tareas técnicas
- ✅ Filtros combinables y paginación.
- ✅ Visor y descarga autorizada del PDF con auditoría.
- ✅ Vista de incidencias con caché por contenido de los lotes históricos.
- ✅ Índices y búsqueda por nombre/DNI en una sola pasada por tabla de detalle (AC03).

## Verificación
| Criterio | Prueba / evidencia |
|---|---|
| CA-02.1 | `ActaTest::test_puede_buscar_actas_por_numero`, `ApiV1Test::test_actas_se_buscan_y_consultan_sin_exponer_el_pdf` |
| CA-02.2 | `ActaTest::test_puede_consultar_un_acta_registrada`, `ActaTest::test_puede_visualizar_el_pdf_del_acta` |
| CA-02.3 | `AuthTest::test_usuario_no_autenticado_es_redirigido_al_login`, matriz Postman AC04 (grupo 1) |
| CA-02.4 | `IncidenciaTest::test_reune_historicos_y_fichas_sin_pdf_sin_duplicar_y_filtra_por_situacion` |
| CA-02.5 | `IncidenciaTest::test_adjuntar_original_resuelve_el_aviso_y_conserva_el_antecedente` |
| CA-02.6 | `IncidenciaTest::test_documentos_privados_y_detalles_requieren_permiso_y_no_admiten_rutas_externas` |
| CA-02.7 | Perfilado de consultas con 50 000 actas (evidencia de AC03) |
