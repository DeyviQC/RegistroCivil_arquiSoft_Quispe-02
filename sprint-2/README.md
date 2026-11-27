# Sprint 2 — Implementación y validación de la arquitectura

> **¿Solo quieres ver los resultados?** → [**resultados/**](resultados/): veredicto por atributo, gráficos de
> carga y enlaces a todas las evidencias.

## Entregables

| # | Documento | Qué contiene |
|:-:|---|---|
| 01 | [API REST y Swagger](01-api-rest.md) | Endpoints `/api/v1`, contrato OpenAPI y cómo probarlo |
| 02 | [Especificaciones SDD](02-especificaciones/README.md) | 13 especificaciones (SPEC-01 … SPEC-13) con criterios de aceptación enlazados a sus pruebas |
| 03 | [Base de datos y caché](03-base-de-datos-y-cache.md) | Modelo, índices, transacciones y estrategia *cache-aside* |
| 04 | [SOLID y patrones](04-solid-y-patrones.md) | Principios y patrones de diseño aplicados, con su ubicación en el código |
| 05 | [Pruebas, cobertura y CI](05-pruebas-cobertura-y-ci.md) | Estrategia de pruebas, cobertura del 84 % y GitHub Actions |
| 06 | [Atributos de calidad](06-atributos-de-calidad.md) | Mediciones EQ-01 … EQ-08 (k6 hasta 2 000 usuarios, OWASP ZAP, concurrencia y restauración) y recomendaciones para el Sprint 3 |

## Carpetas

| Carpeta | Contenido |
|---|---|
| [`resultados/`](resultados/) | Resumen visual del sprint con gráficos generados desde las evidencias |
| [`evidencias/`](evidencias/) | Salidas originales de k6, ZAP, Newman, cobertura y del simulacro de restauración |
| [`02-especificaciones/`](02-especificaciones/) | Las especificaciones SDD |

## Dónde está el resto

- **Código:** [`../codigo/`](../codigo/). Las pruebas de carga están en `codigo/tests/carga` y las de seguridad en `codigo/tests/seguridad`.
- **Guía para repetir las pruebas:** [`codigo/docs/calidad/GUIA-EJECUCION.md`](../codigo/docs/calidad/GUIA-EJECUCION.md).
- **Integración continua:** [`.github/workflows/ci.yml`](../.github/workflows/ci.yml).

**Fuera del alcance de este sprint:** el despliegue es del Sprint 3. Tampoco se implementan la firma con
Firma Perú ni una pasarela de pago real, porque requieren cuentas de la municipalidad; los pagos funcionan en
el sandbox de Mercado Pago.
