# Requisitos funcionales

## Requisitos y estado

El estado corresponde al código de desarrollo revisado el 15 de octubre de 2026, sin afirmar funcionamiento en producción. Se conservan RF01–RF12 y se precisan funciones actuales.

| ID | Requisito funcional |
| --- | --- |
| RF01 | Administrar usuarios y roles; restringir la gestión de cuentas al administrador y las operaciones del archivo al personal autorizado. |
| RF02 | Registrar actas de nacimiento existentes con datos del titular, datos de padres, PDF obligatorio y referencia física, sin inventar documentos de identidad ausentes. |
| RF03 | Registrar actas de matrimonio existentes con datos de contrayentes, PDF obligatorio y referencia física. |
| RF04 | Registrar actas de defunción existentes con sus datos, PDF obligatorio y referencia física. |
| RF05 | Conservar PDF originales en almacenamiento privado y metadatos en MySQL; impedir duplicados por número, año y tipo y por hash del PDF. Proteger el acta central y su numeración frente a edición y eliminación ordinarias. |
| RF06 | Permitir un trámite público sin cuenta, con términos versionados, búsqueda de coincidencia única con PDF, datos del solicitante y vínculo, verificación de correo y seguimiento privado. |
| RF07 | Entregar una versión autorizada y firmada mediante descarga protegida y registrar documento, responsable y fecha de entrega. |
| RF08 | Permitir firma criptográfica mediante una solución compatible con DNIe, restringida al encargado autorizado, conservando el PDF original y la evidencia de la versión firmada. |
| RF09 | Verificar el documento mediante código o QR de autenticidad y resultado de comprobación de firma e integridad, con exposición mínima de datos. |
| RF10 | Comunicar el seguimiento en la plataforma y enviar códigos por correo. Incorporar avisos de entrega y WhatsApp cuando estén integrados. |
| RF11 | Registrar y consultar operaciones administrativas y de atención con responsable, fecha, acción y registro afectado; registrar revisiones de filiación con valores anteriores, nuevos y hash de referencia. |
| RF12 | Generar reportes filtrados de actas, resúmenes por periodo, pendientes documentales, sobres y actividad; permitir exportaciones. |
| RF13 | Buscar y consultar actas por criterios habilitados, visualizar e imprimir el PDF mediante rutas autorizadas. |
| RF14 | Restablecer la contraseña de administradores activos mediante enlace temporal y de un solo uso, con auditoría e invalidación de sesiones de base de datos. |
| RF15 | Completar o revisar nombres y DNI de padres con estados explícitos, control de versión e historial; conservar intactos el acta central, su numeración y PDF. |
| RF16 | Procesar pagos mediante Izipay con importe definido en servidor y prevención de doble procesamiento; confirmar el resultado mediante un mecanismo verificado del proveedor y validar referencia, moneda e importe. No confiar en la redirección del navegador como prueba de pago. |
| RF17 | Atender trámites pagados con transiciones y versión controladas, respuesta obligatoria y revisión humana del vínculo cuando corresponda. |

## Reglas del trámite público

- La búsqueda no expone el archivo municipal. Una ausencia de coincidencia digital no prueba inexistencia del acta física.
- El código de correo tiene seis dígitos, vence en diez minutos, admite cinco intentos y exige un minuto entre reenvíos. Se conserva su hash.
- El flujo distingue `PENDIENTE_PAGO`, `PENDIENTE`, `EN_REVISION`, `OBSERVADA`, `APROBADA` y `RECHAZADA`. El paso de pago a atención deberá habilitarse únicamente con confirmación verificada de Izipay; esta integración está pendiente.
- Desde `PENDIENTE` se pasa a `EN_REVISION`; desde revisión se puede observar, aprobar o rechazar. Una observada puede volver a revisión administrativa. La subsanación pública está pendiente.
- `APROBADA` significa pendiente de firma, sin descarga disponible. Pago, contraste de DNI o coincidencia de parentesco no autorizan automáticamente la entrega.
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

RF07, RF08, RF09 y RF16 están pendientes de implementación. RF10 combina seguimiento y códigos locales con avisos futuros; WhatsApp está pospuesto. RF12 cubre actualmente archivo y actividad, con reportes de solicitudes pendientes. El flujo público de RF06 requiere integrar Izipay y validar el contraste externo; aprobar no firma ni entrega. La recuperación RF14 depende del correo configurado.
