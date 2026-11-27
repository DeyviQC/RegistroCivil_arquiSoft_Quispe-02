# AC07 — Mantenibilidad

**Escenario:** concentrar cambios en las responsabilidades afectadas y aprobar las pruebas pertinentes
sin romper registro, autorización ni conservación documental.

**Meta:** PHPStan nivel 5 sin errores y todas las pruebas aprobadas en integración continua.

**Veredicto: ✅ CUMPLE** — de 197 hallazgos iniciales a **0** en nivel 5, con 128 pruebas aprobadas y el
análisis estático incorporado a GitHub Actions.

## 1. Datos de la ejecución

| Campo | Valor |
|---|---|
| Fecha | 26/11/2026 |
| Herramientas | PHPStan 2.3.7 + Larastan 3 (extensión para Laravel) · PHPUnit · GitHub Actions |
| Configuración | [`phpstan.neon`](../../phpstan.neon): nivel 5 sobre `app/`, `routes/` y `database/migrations/` |
| Comando | `vendor/bin/phpstan analyse --memory-limit=1G` |

## 2. Análisis estático (PHPStan nivel 5)

| Momento | Hallazgos | Evidencia |
|---|---|---|
| Primera ejecución | **197** | [`ac07-phpstan-nivel5.txt`](evidencias/ac07-phpstan-nivel5.txt) |
| Tras tipar los modelos | 60 | — |
| Tras correcciones puntuales | **0** ✅ | [`ac07-phpstan-final.txt`](evidencias/ac07-phpstan-final.txt) |

### Causas encontradas

| Causa | Hallazgos | Corrección |
|---|---|---|
| Relaciones sin tipo de retorno (`acta()`, `pago()`, `eventos()`…): el analizador no las reconocía | ~70 | Tipos `BelongsTo<…>`, `HasOne<…>`, `HasMany<…>` en `TramitePublico`, `PagoTramite`, `TramiteEvento`, `Acta`, `Auditoria` |
| Campo `datos` cifrado (`encrypted:array`) interpretado como texto | ~70 | Documentación de propiedades (`@property array $datos`, fechas como `Carbon`) |
| `match` sin caso por defecto con datos externos | 2 | `default` explícito en `ActaController` y `BusquedaPublicaService` (un tipo inválido falla con un mensaje claro) |
| Código muerto o condiciones siempre verdaderas | 5 | Eliminadas en `CrearRespaldo`, `RecuperarCopiasFirmadas` y `ReporteController` |
| Bloqueos de caché y transporte SMTP sin comprobación de tipo | 5 | Comprobaciones reales en tiempo de ejecución (`instanceof`) en lugar de forzar el tipo |
| Modelo sobrante del módulo antiguo (`SolicitudEvento`) | — | Eliminado |

**Excepción documentada (17 casos, `nullsafe.neverNull`):** uso de `?->` sobre valores que el esquema
garantiza (por ejemplo, toda solicitud tiene acta). Es estilo defensivo e inofensivo: si faltara una fila,
el sistema no cae. Se ignora solo esa categoría en `phpstan.neon`, con su justificación.

**Referencia:** en nivel 6 (más estricto, exige tipos de los elementos de arreglos) hay 159 hallazgos.
Queda como mejora futura; no es parte de la meta.

## 3. Pruebas e integración continua

| Indicador | Valor |
|---|---|
| Pruebas automáticas | **128 aprobadas** (1 349 verificaciones) en SQLite y en MySQL |
| Integración continua | GitHub Actions en cada cambio: **PHPUnit en SQLite y MySQL** + **PHPStan nivel 5** |
| Cobertura de código | No medida: el entorno no tiene Xdebug ni PCOV (limitación) |

Áreas críticas protegidas por pruebas (lo que el escenario pide «no romper»):

| Área | Pruebas |
|---|---|
| Registro de actas | `ActaTest`, `ActaDocumentoTest`, `FiliacionTest` |
| Autorización | `UsuarioTest`, `EndurecimientoTest`, `RespuestaObservacionTest`, `MejorasFlujoTest` |
| Conservación documental | `EntregaDocumentoTest`, `CopiaFirmadaTest`, `AvisosMantenimientoRespaldoTest` (respaldo y clave) |

## 4. Localidad de los cambios

Archivos modificados por cada cambio reciente (`git log --shortstat`):

| Cambio | Archivos | Tipo |
|---|---|---|
| Pantalla de Yape como pago real | 4 | Funcionalidad acotada |
| Yape con saldo insuficiente / WhatsApp en cualquier navegador | 4 | Corrección |
| Decidir con un clic y mensaje de WhatsApp | 5 | Funcionalidad acotada |
| Firma y entrega más ágil | 6 | Funcionalidad acotada |
| Reutilizar la copia firmada del acta | 12 | Funcionalidad nueva (incluye migración, comando, pruebas y documentación) |
| Endurecimiento de prioridad alta | 56 | Refactorización transversal planificada (incluye eliminar módulos y servir recursos locales) |

Las correcciones y funcionalidades acotadas tocan **4–6 archivos**: el cambio se concentra en la
responsabilidad afectada. Los cambios grandes son refactorizaciones transversales deliberadas.

Mejoras estructurales que reducen el costo de cambio:
- Un único punto para cambiar de estado una solicitud: `TramitePublico::transicionar()`.
- Un único control de acceso del ciudadano: middleware `portal.acceso`.
- Código muerto eliminado (Izipay, Taypi, módulo de cuentas ciudadanas).

## 5. Observaciones

- Algunos controladores concentran mucha lógica (`EntregaDocumentoController`, 210 líneas; `PortalPublicoController`,
  201). Mover la lógica de entrega a un servicio facilitaría futuros cambios.
- Código: 76 archivos PHP en `app/` (4 432 líneas) y 30 archivos de prueba (2 418 líneas):
  proporción pruebas/código de 0.55.
