# 01. Actores del sistema

## Alcance y estado

Esta documentación describe el proyecto Registro Civil en su copia de desarrollo, actualizada el 27 de noviembre de 2026 (Sprint 2). «Implementado» significa que existe en el código local; no implica publicación ni validación operativa con proveedores. La aplicación conserva Laravel, MVC con servicios, Blade, Bootstrap y MySQL.

## Personas

| Actor | ¿Qué necesita realizar? |
| --- | --- |
| Ciudadano / solicitante | Iniciar un trámite en `/tramite` sin crear cuenta, buscar una coincidencia de acta, declarar datos y vínculo, verificar correo mediante código y consultar el seguimiento. Consultar sus solicitudes mediante DNI, correo y código; realizar pagos en el sandbox de Mercado Pago, elegir canales, responder observaciones (con documento opcional) y descargar el PDF firmado una vez publicado. Los avisos por correo incluyen un enlace firmado de 7 días para volver al seguimiento sin un nuevo código. La verificación pública de autenticidad por QR sigue pendiente. |
| Asistente | Registrar actas existentes con PDF, buscar, consultar e imprimir documentos, revisar filiación, consultar reportes permitidos y atender trámites con decisiones auditadas, gestionar su perfil y foto y consultar incidencias. Las rutas actuales también permiten al asistente cargar y publicar PDF firmados tras revisión; no existe exclusividad de administrador para esas operaciones. |
| Administrador / encargado | Realizar las tareas administrativas, gestionar usuarios, consultar auditoría y reportes de actividad y recuperar su contraseña por correo cuando el envío esté configurado; gestionar su perfil y participar en la revisión y publicación de documentos firmados. |

El módulo anterior de cuentas con rol ciudadano se eliminó en el Sprint 2 (no tenía datos); `/ciudadano/solicitudes` redirige al seguimiento público y el panel solo admite personal (administrador y asistente). Aprobar un trámite no firma ni entrega el documento.

## Sistemas externos y dispositivos

| Elemento | Tipo | Interacción y estado |
|---|---|---|
| Servicio de correo | Sistema externo | Envío inmediato de códigos y recuperación administrativa; avisos de estado en cola con reintentos; PDF firmado adjunto al correo verificado, enviado automáticamente al publicar si el ciudadano lo eligió. En desarrollo, `log` registra mensajes sin enviarlos; SMTP depende de configuración. |
| APIsPerú | Sistema externo | Conector para contrastar DNI y nombres. Pendiente de prueba real con credenciales; no es una integración directa con RENIEC. |
| Mercado Pago | Sistema externo seleccionado | Integración de pagos utilizada en entorno sandbox; no procesa cobros reales de producción. |
| PeruFirma | Componente externo | Firma manual fuera del sistema; luego se carga el PDF y se revisa su firma e integridad antes de publicarlo. |
| DNIe y lector compatible | Credencial y dispositivo | Permiten firmar en la aplicación externa; el lector no decide ni autoriza trámites. |
| WhatsApp manual | Sistema externo | El ciudadano inicia el chat con el número municipal mediante un enlace con mensaje preparado; el personal responde en esa conversación con un mensaje listo y adjunta el PDF manualmente. No existe API ni envío automático; abrir el enlace no acredita entrega. |
