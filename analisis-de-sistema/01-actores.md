# 01. Actores del Sistema

## Identificar actores
**Objetivo:** determinar quiénes interactúan con el sistema.

| Actor | ¿Qué necesita realizar? |
| :--- | :--- |
| **Ciudadano** | Solicitar actas (nacimiento, matrimonio, defunción), consultar el estado de su solicitud, recibir notificaciones por WhatsApp, descargar el acta digital firmada y verificar la autenticidad del documento mediante código QR. |
| **Asistente** | Registrar actas (nacimiento, matrimonio, defunción), consultar actas, atender solicitudes ciudadanas, digitalizar documentos y preparar los expedientes en estado "PENDIENTE DE FIRMA". |
| **Administrador / Encargado** | Administrar la plataforma, gestionar usuarios y permisos, configurar el sistema, revisar y atender solicitudes, firmar digitalmente las actas mediante DNI electrónico (DNIe) y consultar reportes y registros de auditoría. |
| **Lector DNIe** | Proveer la interfaz de hardware y autenticación criptográfica para la lectura del DNI electrónico del Administrador durante el proceso de firma digital. |
| **Servicio de Notificaciones (WhatsApp)** | Enviar notificaciones automáticas al ciudadano sobre el estado de sus solicitudes, confirmaciones y disponibilidad de entrega del acta. |