# Restricciones del sistema

## Condiciones técnicas y de operación

| ID | Restricción | Descripción |
| --- | --- | --- |
| RC01 | Aplicación web | Acceso desde navegador mediante páginas Blade y Bootstrap; panel administrativo y trámite público pertenecen a la misma aplicación. |
| RC02 | Control de versiones | Documentación y código se gestionan con Git. Las credenciales, claves, documentos reales y datos privados no forman parte del material académico publicable. |
| RC03 | Stack y organización | PHP, Laravel, MVC con servicios, Eloquent, Blade, Bootstrap y MySQL. Las integraciones HTTP no implican separar el frontend ni adoptar Clean Architecture. |
| RC04 | Firma prevista con DNIe | La futura firma exige una solución compatible con DNIe y lector del encargado autorizado. Su selección, integración y validación permanecen pendientes. |
| RC05 | Canales de comunicación | Correo obligatorio para el trámite y WhatsApp opcional como dato. WhatsApp está pospuesto; con correo `log` no se envían mensajes. SMTP requiere configuración y prueba. |
| RC08 | Pagos mediante Izipay | Izipay es el proveedor seleccionado. Antes de operar se deben definir modalidad, credenciales, confirmación del pago, pruebas y condiciones municipales. La integración está pendiente; no se asumen tarifa, QR ni mecanismos técnicos del proveedor anterior. |
| RC09 | Entorno local único | La copia usa su propia base `registro_civil_desarrollo` y almacenamiento. No hay separación operativa nube/municipalidad ni sincronización implementada. |
| RC10 | Conservación documental | El registro se refiere a actas existentes con PDF. El original y la numeración se conservan; la revisión de filiación modifica únicamente sus campos autorizados y deja historial. |

## Límites de alcance

| ID | Exclusión o límite | Descripción |
|---|---|---|
| RC06 | Sistemas gubernamentales | No se modifican registros oficiales externos ni se integra todo el ecosistema gubernamental. APIsPerú es un proveedor externo con conector pendiente de prueba; no equivale a acceso directo a RENIEC. |
| RC07 | Procesos judiciales | No se automatizan procesos judiciales ni rectificaciones oficiales. Revisar una transcripción no altera el acta original. |
| RC11 | Capacidades pendientes | Firma DNIe, descarga protegida, QR de autenticidad, subsanación pública, avisos de entrega, colas y reintentos, respaldo operativo y publicación no están terminados. |

Los requisitos de identidad, representación, conservación, cancelaciones y devoluciones requieren definición municipal antes de operar el servicio. Los términos de demostración y la aceptación del formulario no sustituyen esa definición. El contraste de datos, pago o revisión de parentesco no autorizan por sí solos una entrega.
