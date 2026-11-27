# Portal ciudadano: primera etapa

Implementado exclusivamente en la copia de desarrollo. No modifica las actas históricas ni su numeración.

## Accesos

- Registro de cuentas: `/ciudadano/registro`.
- Inicio de sesión existente por usuario y contraseña; el ciudadano se dirige a sus solicitudes.
- Portal: `/ciudadano/solicitudes`.
- Bandeja administrativa: `/solicitudes`, para administrador y asistente.

El registro público siempre asigna ciudadano; no admite elegir roles ni acceder al archivo municipal. El administrador puede gestionar o desactivar cuentas desde Usuarios. Registrar una cuenta no verifica identidad ni acredita derecho a recibir documentos.

## Estados

Presentada → En revisión → Observada, Aprobada o Rechazada.

El propietario de una solicitud observada puede corregir sus datos y volver a presentarla. Cada presentación conserva una copia de sus datos en el historial. Los cambios administrativos requieren una respuesta visible para el solicitante. Hay bloqueo transaccional y control de versión para evitar sobrescribir decisiones simultáneas.

Aprobada no significa entregada: aún no existe descarga ciudadana ni firma integrada. No se vincula un acta ni se entrega un PDF en esta etapa.

## Verificación

Las pruebas usan SQLite en memoria y almacenamiento de prueba cuando corresponde. Nunca ejecutar migraciones de reinicio o seeders de actas en la base con registros reales.

## Pendiente antes de publicación

- Validación de identidad y requisitos municipales, recuperación de contraseña ciudadana y verificación de correo.
- Entrega privada por solicitud; firma criptográfica del administrador con DNIe, validación y QR.
- Portal independiente en la nube y sincronización con el servidor municipal (la laptop lo representa en pruebas). Esta etapa sigue siendo un solo despliegue local; no implementa aún la separación ni la sincronización.
- Notificaciones con proveedores, colas y reintentos; no hay mensajes externos de prueba.
- Cloudflare, dominio, hosting, HTTPS, respaldos y restauración.
- Preparar paquete público limpio: `public/img` contiene documentos e instaladores ajenos al sitio que no deben publicarse. No usar esta carpeta completa como paquete de entrega.

La versión institucional de entrega se mantiene separada. La nube de demostración debe usar documentos ficticios salvo autorización para los reales.
