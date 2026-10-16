# 01. Actores del sistema

## Alcance y estado

Esta documentación describe el proyecto Registro Civil en su copia de desarrollo, revisada el 15 de octubre de 2026. «Implementado» significa que existe en el código local; no implica publicación ni validación operativa con proveedores. La aplicación conserva Laravel, MVC con servicios, Blade, Bootstrap y MySQL.

## Personas

| Actor | ¿Qué necesita realizar? |
| --- | --- |
| Ciudadano / solicitante | Iniciar un trámite en `/tramite` sin crear cuenta, buscar una coincidencia de acta, declarar datos y vínculo, verificar correo mediante código y consultar el seguimiento. Se prevén firma, descarga autorizada y verificación de autenticidad; el pago Izipay está pendiente de integración. |
| Asistente | Registrar actas existentes con PDF, buscar, consultar e imprimir documentos, revisar filiación, consultar reportes permitidos y atender trámites con decisiones auditadas. |
| Administrador / encargado | Realizar las tareas administrativas, gestionar usuarios, consultar auditoría y reportes de actividad y recuperar su contraseña por correo cuando el envío esté configurado. |

El flujo anterior con cuentas de rol ciudadano y solicitudes se conserva para datos históricos. El acceso público vigente no exige crear esas cuentas. Aprobar un trámite no firma ni entrega el documento.

## Sistemas externos y dispositivos

| Elemento | Tipo | Interacción y estado |
|---|---|---|
| Servicio de correo | Sistema externo | Envío de códigos y recuperación administrativa. En desarrollo, `log` registra mensajes sin enviarlos; SMTP depende de configuración. |
| APIsPerú | Sistema externo | Conector para contrastar DNI y nombres. Pendiente de prueba real con credenciales; no es una integración directa con RENIEC. |
| Izipay | Sistema externo seleccionado | Pasarela elegida para los pagos del trámite. Integración y validación pendientes; modalidad, credenciales y mecanismo de confirmación por definir. |
| Solución de firma compatible con DNIe | Componente externo previsto | Firma criptográfica y validación de evidencia; selección e integración pendientes. |
| DNIe y lector compatible | Credencial y dispositivo | Apoyarán la firma del encargado; el lector no decide ni autoriza trámites. |
| Servicio de WhatsApp | Sistema externo previsto | Integración pospuesta. Informar un número opcional no produce notificaciones. |
