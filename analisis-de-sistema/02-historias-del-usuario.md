# Historias de usuario

Las historias describen el desarrollo actual y las funciones previstas. Los conectores existentes no se consideran operativos hasta validar su funcionamiento externo. Se mantienen los identificadores originales.

| ID | Historia de usuario |
| --- | --- |
| HU01 | Como ciudadano, quiero solicitar un acta desde la web para iniciar el trámite sin acudir presencialmente. |
| HU02 | Como ciudadano, quiero consultar mi trámite para conocer su avance. |
| HU03 | Como ciudadano, quiero recibir un aviso por WhatsApp cuando el documento esté disponible para conocer su entrega. |
| HU04 | Como ciudadano, quiero consultar el código o QR del documento para verificar su autenticidad. |
| HU05 | Como asistente, quiero registrar actas existentes con su PDF para organizar el archivo municipal. |
| HU06 | Como asistente, quiero revisar trámites para decidir su atención con sustento. |
| HU07 | Como encargado autorizado, quiero firmar el documento mediante DNIe para incorporar una firma criptográfica verificable. |
| HU08 | Como administrador, quiero gestionar cuentas y roles para controlar el acceso del personal. |
| HU09 | Como administrador, quiero consultar la auditoría para identificar quién realizó cada operación. |
| HU10 | Como personal autorizado, quiero buscar, visualizar e imprimir actas para atender consultas del archivo. |
| HU11 | Como personal autorizado, quiero consultar y exportar reportes para revisar el registro documental. |
| HU12 | Como administrador activo, quiero recuperar mi contraseña por correo para restablecer el acceso. |
| HU13 | Como personal autorizado, quiero revisar los datos de padres para conservar una transcripción fiel del nacimiento. |
| HU14 | Como ciudadano autorizado, quiero descargar el documento aprobado y firmado para recibirlo de forma privada. |

Las historias de seguridad, fidelidad del archivo y atención actual tienen prioridad sobre las ampliaciones de firma, entrega y avisos. Las prioridades de estas ampliaciones deberán acordarse antes de implementarlas.

HU03 está pospuesta. HU04, HU07 y HU14 están pendientes. HU01 y HU06 dependen de la integración de pago Izipay pendiente; APIsPerú aún requiere validación externa. Las demás historias describen funciones locales existentes; el envío real de correo depende de su configuración.
