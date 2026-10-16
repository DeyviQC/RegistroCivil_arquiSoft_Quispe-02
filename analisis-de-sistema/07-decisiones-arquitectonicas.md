# Registro de decisiones arquitectónicas (ADR)

Estado contrastado con el código y documentación de la copia de desarrollo al 15 de octubre de 2026. «Implementado localmente» no afirma despliegue ni validación de proveedores. Se mantienen los identificadores y se actualizan sus resultados.

| ID | Decisión arquitectónica | Driver relacionado | Justificación | Resultado |
| --- | --- | --- | --- | --- |
| ADR-001 | Aplicación monolítica Laravel con MVC y servicios | DA06 | Un despliegue y separación de responsabilidades; servicios y modelos siguen dependiendo del framework. | Implementado localmente. |
| ADR-002 | Interfaz Blade y Bootstrap | DA06 | Renderizar páginas desde Laravel y mantener una interfaz común para panel y trámite. | Implementado localmente. |
| ADR-003 | MySQL y modelos Eloquent | DA06, DA07 | Persistencia relacional y transacciones; requiere verificar migraciones y comportamiento en el entorno objetivo. | Implementado localmente; las pruebas automatizadas usan SQLite. |
| ADR-004 | PDF originales en almacenamiento privado | DA03, DA04 | Guardar ruta, tamaño y hash en MySQL; servir archivos con autorización y preservar el original. | Implementado localmente. |
| ADR-005 | Registrar actas existentes | DA03, DA07 | Asociar datos al PDF original sin emitir una nueva acta oficial ni alterar el registro de otra institución. | Implementado; firma y emisión de copia digital autorizada pendientes. |
| ADR-006 | Autenticación y autorización según rol | DA04 | Reservar archivo y atención al personal y gestión de usuarios al administrador. | Implementado; cuentas ciudadanas históricas conservadas. El nuevo trámite no crea cuentas. |
| ADR-007 | Transacciones y control de duplicados | DA07 | Evitar registros parciales y duplicados por número/año/tipo o hash; coordinar compensación del archivo ante fallo de persistencia. | Implementado localmente. |
| ADR-008 | Numeración por tipo y ubicación física | DA07 | Mantener orden, sobre y correlativo y conservar numeración histórica; no sustituir el número oficial. | Implementado localmente. |
| ADR-009 | Auditoría e historial específico | DA07 | Registrar operaciones, eventos del trámite y revisiones de filiación. Exige control de acceso; no acredita bitácora criptográficamente inmutable. | Implementado para operaciones actuales; firma y entrega pendientes. |
| ADR-010 | Reportes con filtros por propósito | DA02, DA04 | Separar consultas de archivo, resúmenes, pendientes, sobres y actividad; esta última requiere permisos de auditoría. | Implementado con exportaciones; reportes de solicitudes pendientes. |
| ADR-011 | Recuperación por correo de administradores activos | DA04, DA05 | Enlaces temporales y de un uso, auditoría e invalidación de sesiones de base de datos. El envío depende del correo configurado. | Implementado; no se presume SMTP operativo en el entorno local. |
| ADR-012 | Ausencia de DNI/CUI explícita | DA07 | Representar datos no consignados sin números ficticios. | Implementado en nacimiento y defunción; filiación tiene estados propios. |
| ADR-013 | Trámite público sin crear cuentas | DA04, DA06 | Separar atención pública del archivo privado; usar correo y código para seguimiento. Mantener el flujo antiguo por compatibilidad histórica. | Implementado en `/tramite`; registro ciudadano anterior redirige al flujo actual. |
| ADR-014 | Estados y decisiones con control de versión | DA07, DA08 | Distinguir pago de atención; impedir decisiones concurrentes desactualizadas y exigir respuesta y revisión de vínculo cuando corresponda. | Implementado: pendiente de pago, pendiente, en revisión, observada, aprobada y rechazada. Aprobar no firma ni entrega; subsanación pública pendiente. |
| ADR-015 | Firma mediante solución compatible con DNIe | DA03 | Conservar original, versión firmada y evidencia; restringir firma al encargado. Requiere selección e integración del componente. | Propuesto, pendiente. |
| ADR-016 | Código y QR de autenticidad | DA03, DA04 | Consultar resultado de validación sin exponer documentos completos. Se distingue del QR de pago. | Propuesto, pendiente. |
| ADR-017 | Entrega privada autorizada | DA03, DA04 | Vincular descarga a trámite, versión firmada y permiso de entrega; dejar constancia. | Propuesto, pendiente. |
| ADR-018 | Correo actual y notificaciones futuras | DA05 | Códigos y recuperación usan correo; con `log` no hay envío real. Colas y reintentos requieren desarrollo adicional. | Correo implementado localmente; avisos de entrega pendientes y WhatsApp pospuesto. |
| ADR-019 | Respaldo conjunto y restauración | DA09 | Recuperar MySQL, PDF y claves necesarias; una copia parcial no recupera todo el sistema. | Pendiente de procedimiento operativo y prueba de restauración. |
| ADR-020 | Configuración por entorno y HTTPS | DA04, DA09 | Mantener secretos fuera del repositorio y verificar TLS de proveedores. HTTPS público requiere despliegue y certificados. | Configuración local existente; publicación, separación nube/municipalidad y sincronización pendientes. |
| ADR-021 | Contraste de datos mediante APIsPerú | DA04, DA08 | Encapsular llamadas en `ApisPeruService`; el contraste de DNI y nombres no prueba identidad ni parentesco. | Conector existente, sin prueba real con credenciales; no es acceso directo a RENIEC. |
| ADR-022 | Pago mediante Izipay | DA08 | Usar el proveedor seleccionado para el trámite; definir importe en servidor, confirmación verificable y prevención de doble procesamiento según su modalidad de integración. No confiar en el retorno del navegador. | Decisión actualizada: Izipay seleccionado, integración y validación pendientes. TAYPI descartado; su conector local no constituye implementación de Izipay. |
| ADR-023 | Filiación revisable con historial | DA03, DA07 | Modificar solo campos autorizados de padres; guardar valores anteriores/nuevos, operador y hash del PDF, con bloqueo y versión. | Implementado; conserva acta central, numeración y original. El cruce apoya revisión humana sin autorizar entrega. |

## Evidencia de implementación

Las rutas siguientes pertenecen al proyecto Laravel de desarrollo, no a carpetas de este repositorio documental:

| Decisiones | Evidencia principal |
|---|---|
| ADR-001–ADR-008, ADR-012 | `routes/web.php`, `app/Http/Controllers/ActaController.php`, `app/Services/ActaService.php`, `app/Services/PdfValidationService.php`, `app/Services/PdfStorageService.php`, `app/Policies/ActaPolicy.php`, modelos y migraciones. |
| ADR-009–ADR-011, ADR-018 | `app/Services/AuditoriaService.php`, `app/Http/Controllers/ReporteController.php`, `app/Http/Controllers/PasswordResetController.php`, `app/Services/CodigoTramiteService.php`. |
| ADR-013–ADR-014 | `app/Http/Controllers/PortalPublicoController.php`, `app/Http/Controllers/AtencionPublicaController.php`, `app/Models/TramitePublico.php`; `Solicitud` conserva el flujo anterior. |
| ADR-020–ADR-023 | `config/portal.php`, `app/Services/PortalHttpOptions.php`, `app/Services/ApisPeruService.php`, `app/Http/Controllers/FiliacionController.php`, `app/Services/CruceParentescoService.php`. |

La evidencia indica ubicación del comportamiento, no resultados de ejecución. No se incorporan datos personales, credenciales ni documentos reales al material académico.

La selección de Izipay reemplaza TAYPI. El código local conserva el conector anterior, que queda fuera de la solución elegida; su sustitución no se ha realizado en esta actualización documental. Los detalles de Izipay deberán definirse según la modalidad contratada, sin trasladar automáticamente firmas, endpoints, QR ni tarifas del proveedor descartado.
