# Requisitos funcionales

## Requisitos y estado

El estado corresponde al código de desarrollo revisado el 22 de octubre de 2026, sin afirmar funcionamiento en producción. Se conservan RF01–RF12 y se precisan funciones actuales.

| ID | Requisito funcional |
| --- | --- |
| RF01 | Administrar usuarios y roles; restringir la gestión de cuentas al administrador y las operaciones del archivo al personal autorizado; permitir actualizar perfil y foto según permisos. |
| RF02 | Registrar actas de nacimiento existentes con datos del titular, datos de padres, PDF obligatorio y referencia física, sin inventar documentos de identidad ausentes. |
| RF03 | Registrar actas de matrimonio existentes con datos de contrayentes, PDF obligatorio y referencia física. |
| RF04 | Registrar actas de defunción existentes con sus datos, PDF obligatorio y referencia física. |
| RF05 | Conservar PDF originales en almacenamiento privado y metadatos en MySQL; impedir duplicados por número, año y tipo y por hash del PDF. Proteger el acta central y su numeración frente a edición y eliminación ordinarias. |
| RF06 | Permitir un trámite público sin cuenta, con términos versionados, búsqueda de coincidencia única con PDF, datos del solicitante y vínculo, verificación de correo y seguimiento privado recuperado con DNI del solicitante y correo, con selección entre sus solicitudes tras verificar el código. |
| RF07 | Entregar una versión autorizada y firmada mediante descarga protegida y registrar documento, revisión, publicación y descargas. La publicación habilita LISTA sin acreditar por sí sola la descarga. |
| RF08 | Permitir al personal cargar una copia firmada externamente mediante DNIe, verificar integridad CMS y conservar original y evidencia para revisión previa a publicación. Las rutas actuales admiten administrador y asistente; el adaptador web Firma Perú existe, pero requiere activación y validación institucional. |
| RF09 | Verificar el documento mediante código o QR de autenticidad y resultado de comprobación de firma e integridad, con exposición mínima de datos. |
| RF10 | Comunicar el seguimiento, enviar códigos y permitir elegir canales de entrega; enviar el PDF publicado al correo verificado a petición del ciudadano y habilitar consultas y entrega manual por WhatsApp. No hay envío automático mediante API de WhatsApp. |
| RF11 | Registrar y consultar operaciones administrativas y de atención con responsable, fecha, acción y registro afectado; registrar revisiones de filiación con valores anteriores, nuevos y hash de referencia. |
| RF12 | Generar reportes filtrados de actas, resúmenes por periodo, pendientes documentales, sobres y actividad; permitir exportaciones. |
| RF13 | Buscar y consultar actas por criterios habilitados, visualizar e imprimir el PDF mediante rutas autorizadas y consultar incidencias documentales con sus filtros y acceso privado. |
| RF14 | Restablecer la contraseña de administradores activos mediante enlace temporal y de un solo uso, con auditoría e invalidación de sesiones de base de datos. |
| RF15 | Completar o revisar nombres y DNI de padres con estados explícitos, control de versión e historial; conservar intactos el acta central, su numeración y PDF. |
| RF16 | Procesar pagos de prueba mediante Izipay Mi Cuenta Web REST V4 y formulario embebido con formToken del servidor; verificar HMAC del retorno e IPN, tienda, modo TEST, referencia, importe, moneda y estado PAID/CLOSED, con actualización transaccional e idempotente. El retorno solo confirma tras verificación; producción bloqueada. |
| RF17 | Atender trámites pagados con transiciones y versión controladas, respuesta obligatoria y revisión humana del vínculo cuando corresponda. |

## Reglas del trámite público

- La búsqueda no expone el archivo municipal. Una ausencia de coincidencia digital no prueba inexistencia del acta física.
- El código de correo tiene seis dígitos, vence en diez minutos, admite cinco intentos y exige un minuto entre reenvíos. Se conserva su hash.
- El flujo distingue `PENDIENTE_PAGO`, `PENDIENTE`, `EN_REVISION`, `OBSERVADA`, `APROBADA`, `LISTA` y `RECHAZADA`. El pago de prueba confirmado por retorno verificado o IPN válida de Izipay habilita la atención; falta validar la operación interactiva completa.
- Desde `PENDIENTE` se pasa a `EN_REVISION`; desde revisión se puede observar, aprobar o rechazar. Una observada puede volver a revisión administrativa. La subsanación pública está pendiente.
- `APROBADA` significa pendiente de firma. Tras carga, verificación y revisión expresa se publica como `LISTA`; solo entonces se permite descarga privada con sesión de seguimiento vigente y hash válido. Pago, contraste de DNI o coincidencia de parentesco no autorizan automáticamente la entrega.
- El flujo anterior con cuentas usa `Solicitud` y sus propios estados; se conserva por compatibilidad histórica y no debe confundirse con `TramitePublico`.

## Trazabilidad HU–RF

| Historia | Requisitos |
|---|---|
| HU01 | RF06, RF16 |
| HU02 | RF06, RF10 |
| HU03 | RF10 |
| HU04 | RF09 |
| HU05 | RF02, RF03, RF04, RF05 |
| HU06 | RF11, RF17 |
| HU07 | RF08 |
| HU08 | RF01 |
| HU09 | RF11 |
| HU10 | RF05, RF13 |
| HU11 | RF12 |
| HU12 | RF14 |
| HU13 | RF02, RF11, RF15 |
| HU14 | RF07 |

RF07 y el recorrido manual de RF08 están implementados localmente; el adaptador web Firma Perú requiere credenciales y validación real. La confianza del certificado, revocación y facultades del firmante requieren revisión institucional: la comprobación CMS no las acredita automáticamente. RF09 sigue pendiente. RF16 tiene integración de prueba y validación interactiva pendiente. RF10 incluye correo y WhatsApp manual según preferencias; con log no hay envío real de correo. RF12 mantiene reportes de solicitudes pendientes. APIsPerú y correo requieren sus validaciones operativas; aprobar por sí solo no firma ni entrega.
