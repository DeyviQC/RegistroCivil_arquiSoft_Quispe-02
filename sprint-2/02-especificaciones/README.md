# Sprint 2 · Actividad 3 — Especificaciones SDD

**SDD (Spec-Driven Development):** cada funcionalidad se describe primero como una especificación
verificable —qué debe hacer, bajo qué reglas y cómo se comprueba— y luego se implementa y se prueba contra
ella. Cada criterio de aceptación de estas especificaciones está enlazado a la **prueba automática** que lo
verifica en `codigo/tests/Feature/`.

## Plantilla usada

Cada especificación (`SPEC-xx`) contiene:

1. **Ficha:** historias de usuario (HU), requisitos (RF), decisiones (ADR) y atributos de calidad (AC) relacionados, y estado.
2. **Objetivo:** el problema que resuelve, en una o dos frases.
3. **Criterios de aceptación:** formato *Dado / Cuando / Entonces*, numerados `CA-xx.n`.
4. **Reglas de negocio:** restricciones que el código hace cumplir.
5. **Diseño técnico:** rutas, componentes por capa y datos.
6. **Tareas técnicas:** lista de trabajo realizado (✅) o pendiente (⬜).
7. **Verificación:** cada criterio con la prueba que lo comprueba.
8. **Fuera de alcance:** lo que la especificación no cubre.

## Índice

| ID | Especificación | Historias | Requisitos | Módulo |
|---|---|---|---|---|
| [SPEC-01](SPEC-01-registro-de-actas.md) | Registro de actas existentes con PDF | HU05 | RF02–RF05 | Archivo |
| [SPEC-02](SPEC-02-consulta-del-archivo.md) | Búsqueda, consulta e incidencias del archivo | HU10 | RF13 | Archivo |
| [SPEC-03](SPEC-03-revision-de-filiacion.md) | Revisión de filiación con historial | HU13 | RF15, RF11 | Archivo |
| [SPEC-04](SPEC-04-solicitud-publica.md) | Solicitud pública y verificación de correo | HU01 | RF06 | Trámite |
| [SPEC-05](SPEC-05-pago-sandbox.md) | Pago con Mercado Pago y Yape (sandbox) | HU01 | RF16 | Trámite |
| [SPEC-06](SPEC-06-seguimiento-y-subsanacion.md) | Seguimiento ciudadano y respuesta a observaciones | HU02 | RF06, RF18 | Trámite |
| [SPEC-07](SPEC-07-atencion-y-decision.md) | Atención y decisión de solicitudes | HU06 | RF17, RF11 | Atención |
| [SPEC-08](SPEC-08-firma-publicacion-entrega.md) | Firma, publicación, reutilización y descarga | HU07, HU14 | RF07, RF08 | Entrega |
| [SPEC-09](SPEC-09-canales-de-entrega.md) | Canales de entrega: correo y WhatsApp | HU03 | RF10 | Entrega |
| [SPEC-10](SPEC-10-usuarios-y-auditoria.md) | Usuarios, roles, auditoría y recuperación | HU08, HU09, HU12 | RF01, RF11, RF14 | Seguridad |
| [SPEC-11](SPEC-11-reportes.md) | Reportes y exportaciones | HU11 | RF12 | Archivo |
| [SPEC-12](SPEC-12-api-rest.md) | API REST v1 con token | HU06, HU10 | RF06, RF13, RF17 | Transversal |
| [SPEC-13](SPEC-13-operacion-y-respaldo.md) | Avisos en cola, tareas programadas y respaldo | HU02, HU03 | RF10, RF19 | Operación |

**Pendiente (no especificado como implementado):** HU04 / RF09 — verificación por código o QR de autenticidad (ADR-016).

## Matriz de trazabilidad

| Historia | Especificación | Clases de prueba |
|---|---|---|
| HU01 Solicitar un acta | SPEC-04, SPEC-05 | `PortalPublicoTest`, `MercadoPagoTest`, `YapeOrdersTest`, `PagoLimiteTest` |
| HU02 Consultar mis solicitudes | SPEC-06, SPEC-13 | `SeguimientoCiudadanoTest`, `RespuestaObservacionTest`, `MejorasFlujoTest` |
| HU03 Elegir canales | SPEC-09 | `CanalEntregaTest`, `WhatsAppConsultaTest` |
| HU05 Registrar actas | SPEC-01 | `ActaTest`, `ActaDocumentoTest`, `NumeracionHistoricaTest` |
| HU06 Decidir trámites | SPEC-07, SPEC-12 | `MejorasFlujoTest`, `AvisosMantenimientoRespaldoTest`, `PortalPublicoTest`, `ApiV1Test` |
| HU07 Cargar el firmado | SPEC-08 | `EntregaDocumentoTest`, `BandejaManualTest`, `CopiaFirmadaTest` |
| HU08 Gestionar cuentas | SPEC-10 | `UsuarioTest`, `PerfilInterfazTest`, `AuthTest` |
| HU09 Consultar auditoría | SPEC-10 | `AuditoriaTest` |
| HU10 Buscar y consultar actas | SPEC-02, SPEC-12 | `ActaTest`, `IncidenciaTest`, `ApiV1Test` |
| HU11 Reportes | SPEC-11 | `ReporteTest` |
| HU12 Recuperar contraseña | SPEC-10 | `PasswordRecoveryTest` |
| HU13 Revisar filiación | SPEC-03 | `FiliacionTest`, `CruceParentescoTest` |
| HU14 Descargar el documento | SPEC-08 | `EntregaDocumentoTest`, `CopiaFirmadaTest` |

Ejecutar todas las pruebas: `cd codigo` y `php artisan test` (142 pruebas).
