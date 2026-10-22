# Historias de usuario

Las historias describen el desarrollo actual y las funciones previstas. Los conectores existentes no se consideran operativos hasta validar su funcionamiento externo. Se mantienen los identificadores originales.

| ID | Historia de usuario |
| --- | --- |
| HU01 | Como ciudadano, quiero solicitar un acta desde la web para iniciar el trámite sin acudir presencialmente. |
| HU02 | Como ciudadano, quiero consultar mis solicitudes mediante DNI, correo y código para conocer su avance y seleccionar la que deseo revisar. |
| HU03 | Como ciudadano, quiero elegir correo o WhatsApp como canales para recibir mi documento publicado; el correo adjunta el PDF y WhatsApp requiere envío manual. |
| HU04 | Como ciudadano, quiero consultar el código o QR del documento para verificar su autenticidad. |
| HU05 | Como asistente, quiero registrar actas existentes con su PDF para organizar el archivo municipal. |
| HU06 | Como asistente, quiero revisar trámites para decidir su atención con sustento. |
| HU07 | Como personal autorizado, quiero cargar el PDF firmado externamente con DNIe y revisar su evidencia antes de publicarlo, para conservar el original y entregar una versión firmada. |
| HU08 | Como administrador, quiero gestionar cuentas y roles para controlar el acceso del personal; como personal autorizado, quiero actualizar mi perfil y foto dentro de mis permisos. |
| HU09 | Como administrador, quiero consultar la auditoría para identificar quién realizó cada operación. |
| HU10 | Como personal autorizado, quiero buscar, visualizar e imprimir actas para atender consultas del archivo y revisar incidencias documentales sin alterar el original. |
| HU11 | Como personal autorizado, quiero consultar y exportar reportes para revisar el registro documental. |
| HU12 | Como administrador activo, quiero recuperar mi contraseña por correo para restablecer el acceso. |
| HU13 | Como personal autorizado, quiero revisar los datos de padres para conservar una transcripción fiel del nacimiento. |
| HU14 | Como ciudadano autorizado, quiero descargar el documento aprobado y firmado para recibirlo de forma privada. |

Las historias de seguridad, fidelidad del archivo y atención actual mantienen prioridad. La carga manual de firma, publicación, descarga y canales ya tienen implementación local; deben distinguirse de su validación operativa y de las ampliaciones de firma web y QR.

HU04 sigue pendiente. HU03, HU07 y HU14 tienen implementación local con revisión de firma y publicación; WhatsApp es manual y el correo real depende de configuración. HU01 y HU06 incluyen Izipay Mi Cuenta Web en modo de prueba, pendiente de validar una operación interactiva completa. APIsPerú requiere validación externa. El adaptador web Firma Perú existe, pero su activación y firma real requieren credenciales institucionales. Las rutas de firma y publicación admiten administrador y asistente; no se atribuye exclusividad al administrador.
