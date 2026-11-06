# Registro de decisiones arquitectónicas (ADR)

Estado contrastado con el código y documentación de la copia de desarrollo al 22 de octubre de 2026. «Implementado localmente» no afirma despliegue ni validación de proveedores. Se mantienen los identificadores y se actualizan sus resultados.

| ID | Decisión arquitectónica | Driver relacionado | Justificación | Resultado |
| --- | --- | --- | --- | --- |
| ADR-001 | Aplicación monolítica Laravel con MVC y servicios | DA06 | Un despliegue y separación de responsabilidades; servicios y modelos siguen dependiendo del framework. | Implementado localmente. |
| ADR-002 | Interfaz Blade y Bootstrap | DA06 | Renderizar páginas desde Laravel y mantener una interfaz común para panel y trámite. | Implementado localmente. |
| ADR-003 | MySQL y modelos Eloquent | DA06, DA07 | Persistencia relacional y transacciones; requiere verificar migraciones y comportamiento en el entorno objetivo. | Implementado localmente; las pruebas automatizadas usan SQLite. |
| ADR-004 | PDF originales en almacenamiento privado | DA03, DA04 | Guardar ruta, tamaño y hash en MySQL; servir archivos con autorización y preservar el original. | Implementado localmente. |
| ADR-005 | Registrar actas existentes | DA03, DA07 | Asociar datos al PDF original sin modificarlo; mantener separada la copia firmada para entrega. | Registro implementado; carga manual, revisión y publicación de copia firmada implementadas localmente. |
| ADR-006 | Autenticación y autorización por roles | DA04 | Reservar archivo y atención al personal y gestión de usuarios al administrador; permitir edición de perfil/foto según permisos. | Implementado. Las rutas de carga, publicación y firma con PeruFirma admiten administrador y asistente; no hay exclusividad del administrador. Cuentas ciudadanas históricas conservadas. |
| ADR-007 | Transacciones y control de duplicados | DA07 | Evitar registros parciales y duplicados por número/año/tipo o hash; coordinar compensación del archivo ante fallo de persistencia. | Implementado localmente. |
| ADR-008 | Numeración por tipo y ubicación física | DA07 | Mantener orden, sobre y correlativo y conservar numeración histórica; no sustituir el número oficial. | Implementado localmente. |
| ADR-009 | Auditoría e historial específico | DA07 | Registrar operaciones, eventos, revisiones de filiación, carga, publicación y descarga del firmado. No se acredita bitácora criptográficamente inmutable. | Implementado localmente para archivo y entrega; revisión institucional documentada antes de publicar. |
| ADR-010 | Reportes con filtros por propósito | DA02, DA04 | Separar consultas de archivo, resúmenes, pendientes, sobres y actividad; esta última requiere permisos de auditoría. | Implementado con exportaciones; reportes de solicitudes pendientes. |
| ADR-011 | Recuperación por correo de administradores activos | DA04, DA05 | Enlaces temporales y de un uso, auditoría e invalidación de sesiones de base de datos. El envío depende del correo configurado. | Implementado; no se presume SMTP operativo en el entorno local. |
| ADR-012 | Ausencia de DNI/CUI explícita | DA07 | Representar datos no consignados sin números ficticios. | Implementado en nacimiento y defunción; filiación tiene estados propios. |
| ADR-013 | Trámite público sin crear cuentas | DA04, DA06 | Separar atención pública y archivo; recuperar solicitudes con DNI del solicitante y correo, verificar código y seleccionar trámite. | Implementado en /tramite; conserva flujo histórico con cuentas, seguimiento y descarga de documento publicado. |
| ADR-014 | Estados y decisiones con control de versión | DA07, DA08 | Distinguir pago, revisión y disponibilidad; evitar decisiones concurrentes desactualizadas y exigir sustento. | Implementado: pendiente de pago, pendiente, en revisión, observada, aprobada, LISTA y rechazada. Solo el controlador de entrega publica como LISTA; subsanación pública pendiente. |
| ADR-015 | Firma externa con PeruFirma y carga verificada | DA03 | El personal firma fuera del sistema, carga el PDF y el servidor verifica CMS, cobertura y correspondencia con el original. Confianza, revocación y facultades requieren revisión humana. | Recorrido manual implementado. No se realiza la firma mediante una integración web de la aplicación. No se reciben PIN ni claves privadas. |
| ADR-016 | Código y QR de autenticidad | DA03, DA04 | Consultar resultado de validación sin exponer documentos completos. Se distingue del QR de pago. | Propuesto, pendiente. |
| ADR-017 | Entrega privada autorizada | DA03, DA04 | Publicar tras carga, verificación y revisión expresa; conservar firmado y hash y exigir pago y sesión de seguimiento vigente para descarga. | Implementado localmente: LISTA habilita descarga, que se audita; no demuestra por sí solo recepción del ciudadano. |
| ADR-018 | Canales de entrega y correo configurable | DA05 | Guardar preferencias; enviar firmado al correo verificado tras publicación y comprobar hash. WhatsApp abre conversación y exige envío manual, sin API ni constancia automática de entrega. | Códigos, recuperación, correo adjunto y enlaces manuales implementados. Con log no hay envío real; automatización WhatsApp y colas/reintentos pendientes. |
| ADR-019 | Respaldo conjunto y restauración | DA09 | Recuperar MySQL, PDF y claves necesarias; una copia parcial no recupera todo el sistema. | Pendiente de procedimiento operativo y prueba de restauración. |
| ADR-020 | Configuración por entorno y HTTPS | DA04, DA09 | Mantener secretos fuera del repositorio y verificar TLS de proveedores. HTTPS público requiere despliegue y certificados. | Configuración local existente; publicación, separación nube/municipalidad y sincronización pendientes. |
| ADR-021 | Contraste de datos mediante APIsPerú | DA04, DA08 | Encapsular llamadas en `ApisPeruService`; el contraste de DNI y nombres no prueba identidad ni parentesco. | Conector existente, sin prueba real con credenciales; no es acceso directo a RENIEC. |
| ADR-022 | Pago mediante Mercado Pago en sandbox | DA08 | Usar el entorno de pruebas de Mercado Pago para procesar pagos de prueba y evitar atribuirlos a cobros reales. | Mercado Pago se utiliza en sandbox; no se habilitan cobros reales de producción. |
| ADR-023 | Filiación revisable con historial | DA03, DA07 | Modificar solo campos autorizados de padres; guardar valores anteriores/nuevos, operador y hash del PDF, con bloqueo y versión. | Implementado; conserva acta central, numeración y original. El cruce apoya revisión humana sin autorizar entrega. |

## Evidencia de implementación

Las rutas siguientes pertenecen al proyecto Laravel de desarrollo, no a carpetas de este repositorio documental:

| Decisiones | Evidencia principal |
|---|---|
| ADR-001–ADR-008, ADR-012 | `routes/web.php`, `app/Http/Controllers/ActaController.php`, `app/Services/ActaService.php`, `app/Services/PdfValidationService.php`, `app/Services/PdfStorageService.php`, `app/Policies/ActaPolicy.php`, modelos y migraciones. |
| ADR-009–ADR-011, ADR-018 | `app/Services/AuditoriaService.php`, `app/Http/Controllers/ReporteController.php`, `app/Http/Controllers/PasswordResetController.php`, `app/Services/CodigoTramiteService.php`, `app/Http/Controllers/CanalEntregaController.php`, `app/Services/FirmaDocumentoService.php`, `app/Http/Controllers/EntregaDocumentoController.php`. |
| ADR-013–ADR-014 | `app/Http/Controllers/PortalPublicoController.php`, `app/Http/Controllers/AtencionPublicaController.php`, `app/Models/TramitePublico.php`; `Solicitud` conserva el flujo anterior. |
| ADR-020–ADR-023 | `config/portal.php`, `app/Services/PortalHttpOptions.php`, servicio y controlador de pagos de Mercado Pago, `app/Services/FirmaPeruService.php`, `app/Services/ApisPeruService.php`, `app/Http/Controllers/FiliacionController.php`, `app/Services/CruceParentescoService.php`. |

La evidencia indica ubicación del comportamiento, no resultados de ejecución. No se incorporan datos personales, credenciales ni documentos reales al material académico.

Mercado Pago es el proveedor de pagos utilizado en sandbox; ese entorno no procesa cobros reales de producción. La firma se realiza externamente con PeruFirma y el PDF se carga al sistema para su revisión. Firma y entrega conservan evidencia en datos cifrados y archivos privados; el QR público de autenticidad sigue pendiente. Perfil/foto e incidencias forman parte de las responsabilidades administrativas existentes; esta documentación no cambia código ni permisos.
