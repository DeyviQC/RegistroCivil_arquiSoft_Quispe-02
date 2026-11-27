# Datos de madre y padre

Solo se amplió la copia de desarrollo y su base `registro_civil_desarrollo`.

El registro de un nuevo nacimiento requiere revisar cuatro campos: nombre y DNI de madre y padre. Cada uno admite registrado, no consignado, no se conoce según el acta o ilegible. Los registros anteriores inician pendientes hasta ser revisados. No se inventan DNI.

En la ficha de nacimiento aparece «Completar / revisar datos de padres». Administrador y asistente pueden actualizar solo estos campos. La operación registra los valores anteriores y nuevos, operador, fecha y hash del PDF de referencia. El control de versión rechaza modificaciones basadas en una ficha desactualizada. La tabla central del acta, su numeración y el PDF original no cambian.

Los 22 nacimientos existentes se completaron el 13 de octubre de 2026 a partir de revisión visual de sus PDF. En un acta ambos DNI de padres figuran como «NO SE CONOCE»: se conservan como desconocidos, sin números ficticios. Los nombres se transcribieron del apartado de padres, conservando su escritura, sin corregirla con fuentes externas.

La transcripción y el respaldo anterior están en `storage/app/private/importaciones/filiacion`. El historial inicial no atribuye la transcripción automatizada a un usuario municipal. Las revisiones posteriores sí registran al operador autenticado. Se comprobó que las 78 actas centrales y los hashes de todos sus PDF se mantienen.

Estos datos permiten apoyar la revisión de parentesco; no acreditan por sí solos la identidad de quien presenta el trámite ni autorizan automáticamente una entrega. No se publican en el portal ciudadano.
