# Requisitos Funcionales

## Lista de Requisitos Funcionales

| ID | Requisito funcional |
| :--- | :--- |
| **RF01** | El sistema deberá permitir registrar y administrar usuarios con roles de Administrador/Encargado y Asistente, permitiendo gestionar permisos y controlar accesos según el rol. |
| **RF02** | El sistema deberá permitir registrar nuevas actas de nacimiento almacenando datos del recién nacido y progenitores, generando un identificador único y asociando su respaldo físico. |
| **RF03** | El sistema deberá permitir registrar nuevas actas de matrimonio con la información de los contrayentes, generando un identificador único y asociando el documento digitalizado del acta física. |
| **RF04** | El sistema deberá permitir registrar nuevas actas de defunción con los datos de la persona fallecida y del fallecimiento, generando un identificador único y registrando la ubicación del documento físico. |
| **RF05** | El sistema deberá almacenar digitalmente las actas registradas, asociar archivos PDF o documentos digitalizados, mantener la ubicación del físico y conservar un historial de modificaciones. |
| **RF06** | El sistema deberá permitir al ciudadano solicitar virtualmente actas de nacimiento, matrimonio o defunción, registrando la solicitud, validando datos y permitiendo consultar su estado. |
| **RF07** | El sistema deberá permitir entregar el acta digital al ciudadano mediante la plataforma web, enviando una notificación por WhatsApp y registrando la fecha y hora de entrega. |
| **RF08** | El sistema deberá permitir que el único Administrador/Encargado autorizado firme digitalmente las actas mediante DNI electrónico y lector compatible, asociando la firma y permitiendo verificar autenticidad e integridad. |
| **RF09** | El sistema deberá permitir verificar un acta mediante un código único o QR, comprobando que no haya sido alterada tras la firma y mostrando información básica sin exponer datos personales innecesarios. |
| **RF10** | El sistema deberá enviar notificaciones sobre el estado de las solicitudes y la disponibilidad del acta mediante WhatsApp y dentro de la plataforma. |
| **RF11** | El sistema deberá registrar en una bitácora de auditoría las operaciones realizadas por los usuarios administrativos (usuario, operación, fecha, hora y documento afectado) y permitir consultar el historial. |
| **RF12** | El sistema deberá generar reportes de actas registradas, estadísticas por tipo de acta y reportes de solicitudes atendidas, pendientes y rechazadas. |

## Relación entre Historias de Usuario (HU) y Requisitos Funcionales (RF)

| Historia de usuario | Requisitos funcionales relacionados |
| :--- | :--- |
| **HU01** Solicitar acta virtual | RF06, RF07 |
| **HU02** Consultar estado de solicitud | RF06 |
| **HU03** Recibir notificaciones por WhatsApp | RF07, RF10 |
| **HU04** Validar acta vía código QR | RF09 |
| **HU05** Registrar nuevas actas | RF02, RF03, RF04, RF05 |
| **HU06** Atender solicitudes de actas | RF05, RF06, RF07 |
| **HU07** Firmar digitalmente actas con DNIe | RF08, RF09 |
| **HU08** Gestionar usuarios y permisos | RF01 |
| **HU09** Consultar auditoría e historial | RF11, RF12 |