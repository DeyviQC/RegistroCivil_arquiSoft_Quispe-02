# 01. Actores del sistema

## Alcance y estado

Esta documentación describe el proyecto Registro Civil en su copia de desarrollo, revisada el 22 de octubre de 2026. «Implementado» significa que existe en el código local; no implica publicación ni validación operativa con proveedores. La aplicación conserva Laravel, MVC con servicios, Blade, Bootstrap y MySQL.

## Personas

| Actor | ¿Qué necesita realizar? |
| --- | --- |
| Ciudadano / solicitante | Iniciar un trámite en `/tramite` sin crear cuenta, buscar una coincidencia de acta, declarar datos y vínculo, verificar correo mediante código y consultar el seguimiento. Consultar sus solicitudes mediante DNI, correo y código; pagar con Izipay de prueba, elegir canales y descargar el PDF firmado una vez publicado. La verificación pública de autenticidad por QR sigue pendiente. |
| Asistente | Registrar actas existentes con PDF, buscar, consultar e imprimir documentos, revisar filiación, consultar reportes permitidos y atender trámites con decisiones auditadas, gestionar su perfil y foto y consultar incidencias. Las rutas actuales también permiten al asistente cargar y publicar PDF firmados tras revisión; no existe exclusividad de administrador para esas operaciones. |
| Administrador / encargado | Realizar las tareas administrativas, gestionar usuarios, consultar auditoría y reportes de actividad y recuperar su contraseña por correo cuando el envío esté configurado; gestionar su perfil y participar en la revisión y publicación de documentos firmados. |

El flujo anterior con cuentas de rol ciudadano y solicitudes se conserva para datos históricos. El acceso público vigente no exige crear esas cuentas. Aprobar un trámite no firma ni entrega el documento.

## Sistemas externos y dispositivos

| Elemento | Tipo | Interacción y estado |
|---|---|---|
| Servicio de correo | Sistema externo | Envío de códigos, recuperación administrativa y PDF firmado adjunto al correo verificado, según preferencia y publicación. En desarrollo, `log` registra mensajes sin enviarlos; SMTP depende de configuración. |
| APIsPerú | Sistema externo | Conector para contrastar DNI y nombres. Pendiente de prueba real con credenciales; no es una integración directa con RENIEC. |
| Izipay | Sistema externo seleccionado | Integración de prueba Mi Cuenta Web REST V4 con formulario embebido, retorno e IPN verificados. Producción bloqueada; operación interactiva completa pendiente de validar. |
| Firma Perú compatible con DNIe | Componente externo | Firma manual fuera del sistema y carga posterior con comprobación CMS y revisión institucional. Adaptador web existente, no activo sin credenciales institucionales ni validado de extremo a extremo. |
| DNIe y lector compatible | Credencial y dispositivo | Permiten firmar en la aplicación externa; el lector no decide ni autoriza trámites. |
| WhatsApp manual | Sistema externo | Enlaces para consulta y envío manual del documento según preferencias. No existe API ni envío automático; abrir el enlace no acredita entrega. |
