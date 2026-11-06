# Restricciones del sistema

## Condiciones técnicas y de operación

| ID | Restricción | Descripción |
| --- | --- | --- |
| RC01 | Aplicación web | Acceso desde navegador mediante páginas Blade y Bootstrap; panel administrativo y trámite público pertenecen a la misma aplicación. |
| RC02 | Control de versiones | Documentación y código se gestionan con Git. Las credenciales, claves, documentos reales y datos privados no forman parte del material académico publicable. |
| RC03 | Stack y organización | PHP, Laravel, MVC con servicios, Eloquent, Blade, Bootstrap y MySQL. Las integraciones HTTP no implican separar el frontend ni adoptar Clean Architecture. |
| RC04 | Firma con DNIe | El recorrido principal firma externamente con PeruFirma y carga el PDF; no se reciben PIN ni claves privadas. Carga y publicación admiten ambos roles internos; la revisión de confianza y facultades es humana. |
| RC05 | Canales de comunicación | Correo obligatorio para el trámite y WhatsApp opcional como dato. Hay consultas y entrega manual por WhatsApp, sin API automática. Las preferencias permiten correo, WhatsApp, ambos o ninguno; el correo adjunta el firmado publicado. Con correo `log` no se envían mensajes; SMTP requiere configuración y prueba. |
| RC08 | Pagos mediante Mercado Pago | Mercado Pago se utiliza en entorno sandbox para pruebas. No se procesan cobros reales de producción; las condiciones de cobro deben confirmarse antes de habilitar producción. |
| RC09 | Entorno local único | La copia usa su propia base `registro_civil_desarrollo` y almacenamiento. No hay separación operativa nube/municipalidad ni sincronización implementada. |
| RC10 | Conservación documental | El registro se refiere a actas existentes con PDF. El original y la numeración se conservan; la revisión de filiación modifica únicamente sus campos autorizados y deja historial. |

## Límites de alcance

| ID | Exclusión o límite | Descripción |
|---|---|---|
| RC06 | Sistemas gubernamentales | No se modifican registros oficiales externos ni se integra todo el ecosistema gubernamental. APIsPerú es un proveedor externo con conector pendiente de prueba; no equivale a acceso directo a RENIEC. |
| RC07 | Procesos judiciales | No se automatizan procesos judiciales ni rectificaciones oficiales. Revisar una transcripción no altera el acta original. |
| RC11 | Capacidades pendientes | Siguen pendientes QR de autenticidad, subsanación pública, automatización WhatsApp, colas y reintentos, respaldo operativo y despliegue público. La firma se realiza externamente con PeruFirma; la carga manual, publicación del firmado, descarga protegida y correo adjunto tienen implementación local. |

Los requisitos de identidad, representación, conservación, cancelaciones y devoluciones requieren definición municipal antes de operar el servicio. Los términos de demostración y la aceptación del formulario no sustituyen esa definición. El contraste de datos, pago o revisión de parentesco no autorizan por sí solos una entrega.
