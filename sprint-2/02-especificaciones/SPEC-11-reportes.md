# SPEC-11 — Reportes y exportaciones

| Campo | Valor |
|---|---|
| Historias | HU11 |
| Requisitos | RF12 |
| Decisiones | ADR-010 |
| Atributos | AC01 Rendimiento, AC04 Seguridad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que el personal revise el estado del archivo con reportes filtrados por propósito y los exporte.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-11.1 | actas registradas | el personal filtra por tipo, año, sobre, correlativo o usuario | el reporte muestra solo las que cumplen los filtros |
| CA-11.2 | un periodo | se pide el resumen | se agrupan totales por tipo y disponibilidad del PDF |
| CA-11.3 | actas sin PDF | se pide el reporte de pendientes | aparecen las pendientes documentales |
| CA-11.4 | cualquier reporte | se exporta | se descarga en PDF o Excel con los mismos datos filtrados |
| CA-11.5 | el reporte de actividad | lo solicita un asistente | se exige el permiso de auditoría (solo administrador) |

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `ReporteController@index/pdf/excel`, `ReporteRequest` |
| Negocio | Consultas agregadas sobre `actas` y `auditoria`; exportación con DomPDF y hoja de cálculo |

## Tareas técnicas
- ✅ Tipos de reporte: archivo, resumen, pendientes, sobres y actividad.
- ✅ Exportación PDF y Excel.
- ✅ Permiso diferenciado para el reporte de actividad.

## Verificación
| Criterio | Prueba |
|---|---|
| CA-11.1 a CA-11.4 | `ReporteTest::test_filtros_resumen_pendientes_y_exportaciones` |
| CA-11.5 | `UsuarioTest::test_asistente_no_puede_acceder_a_auditoria`, `AuditoriaTest::test_solo_administrador_puede_ver_la_auditoria` |
