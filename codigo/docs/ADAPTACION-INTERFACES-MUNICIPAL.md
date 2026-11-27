# Adaptación de interfaces de la entrega municipal

Se consultó como referencia de solo lectura la carpeta hermana `registro-civil`. Los cambios se aplicaron únicamente en `registro-civil-desarrollo`.

Se adaptaron estilos de legibilidad, descripciones de pantallas, búsqueda responsive con acciones con texto, historial de actividades con nombres legibles, pantallas de usuarios y perfil con foto opcional. El perfil conserva permisos: asistentes editan su nombre y foto; el administrador tiene los campos adicionales protegidos por validación. Las fotos se guardan privadas y solo se sirven con sesión y permisos.

Se adaptó la consulta de incidencias a los datos que ya existan en desarrollo. No se copiaron sus lotes ni documentos. No se ejecutó ninguna importación ni se sustituyeron bases de datos.

La migración `2026_10_22_000019_add_foto_to_users.php` añade únicamente el campo opcional `users.foto`. Se comprobó el nombre de base `registro_civil_desarrollo` antes de aplicarla y se mantuvieron las 79 actas existentes. No se migró ni modificó la base de la entrega municipal.

Se conservaron las diferencias propias de este proyecto: portal de trámites, filiación de madre y padre, acceso ciudadano y flujo de firma/entrega. No se sustituyeron sus modelos ni reglas por los de la entrega; no se importó la numeración de libros históricos ni el módulo de cargas masivas.

La revisión de solicitudes se simplificó con un encabezado de expediente, datos esenciales en una ficha, historial y sustento desplegables, y acción contextual de atención o firma. La revisión del vínculo sigue siendo obligatoria al aprobar; la advertencia de discrepancia continúa visible. Los ajustes del logo y la explicación de la habilitación de Firma Perú se muestran solo al desplegarlos. La publicación conserva los controles de revisión y acceso protegido.

Se compararon hashes de los archivos de código de la referencia antes y después de la adaptación: cero cambios en la entrega municipal. No se copiaron PDFs, actas escaneadas, fotos de usuarios, secretos ni cuentas.
