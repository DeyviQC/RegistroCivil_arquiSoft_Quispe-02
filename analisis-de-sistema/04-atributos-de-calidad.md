# Atributos de calidad

## Contexto

La aplicación atiende el archivo municipal y el trámite público dentro de un mismo despliegue Laravel. Se deben proteger los documentos, conservar la consistencia de decisiones simultáneas y controlar fallos de proveedores. Los criterios siguientes son objetivos de verificación: no constituyen resultados de pruebas ejecutadas ni garantías de producción.

| ID | Atributo de calidad | Escenario de calidad |
| --- | --- | --- |
| AC01 | Rendimiento | Medir búsquedas, seguimiento y consultas con datos representativos; registrar percentil 95, errores y concurrencia. |
| AC02 | Disponibilidad | Ante fallos externos, informar el error sin aprobar trámites, confirmar pagos ni entregar documentos incorrectamente; medir interrupciones. |
| AC03 | Escalabilidad | Aumentar progresivamente usuarios y documentos y comparar latencia, errores, almacenamiento y recursos con AC01. |
| AC04 | Seguridad y privacidad | Rechazar todos los casos no autorizados de la matriz de pruebas; no exponer PDF, códigos ni datos personales en respuestas públicas revisadas. Aplicar autorización, límites de intentos, cifrado de datos del trámite y TLS en conexiones externas. |
| AC05 | Integridad documental y transaccional | Rechazar duplicados y decisiones con versión desactualizada; conservar hash del PDF, numeración y acta central al revisar filiación; verificar hash del firmado al publicar, descargar y enviar por correo, y rechazar confirmaciones de pago no válidas. |
| AC06 | Trazabilidad | Para cada operación incluida en la prueba, verificar responsable, acción, fecha y registro; en filiación, valores anteriores y nuevos y hash de referencia; en entrega, carga, publicación y descarga del PDF firmado. |
| AC07 | Mantenibilidad | Concentrar cambios en las responsabilidades afectadas y aprobar las pruebas pertinentes sin romper registro, autorización ni conservación documental. |
| AC08 | Recuperabilidad | Restaurar conjuntamente MySQL, PDF privados y claves necesarias en un entorno separado, verificando relaciones y hashes. |

Las pruebas funcionales existentes usan SQLite en memoria y, cuando corresponde, almacenamiento de prueba. Su existencia no demuestra rendimiento, restauración, interoperabilidad de proveedores ni funcionamiento en MySQL de producción. Los resultados deben registrarse al ejecutar cada verificación.

Las metas de rendimiento, disponibilidad y capacidad aún requieren acordar carga, horario y entorno. La carga manual de firma, verificación CMS, publicación y entrega tienen código local; confianza, revocación y facultades requieren revisión institucional y la firma web completa sigue pendiente de validación; la auditoría existente no acredita una bitácora criptográficamente inmutable. El respaldo operativo y su restauración están pendientes. Estas metas no representan resultados medidos.
