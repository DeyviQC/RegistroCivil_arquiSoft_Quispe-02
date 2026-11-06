# Drivers arquitectónicos

Los drivers relacionan requisitos, calidad y restricciones con las decisiones arquitectónicas. Su influencia determina las decisiones de diseño; las ampliaciones pendientes no se presentan como implementadas.

| ID | Driver arquitectónico | Origen | ¿Por qué influye en la arquitectura? |
| --- | --- | --- | --- |
| DA01 | Crecimiento de usuarios y archivo documental | AC03, RC09 | Medir capacidad y optimizar consultas y almacenamiento dentro del monolito antes de justificar otro despliegue. |
| DA02 | Consulta y reportes con tiempos medibles | AC01, RF12, RF13 | Aplicar filtros, paginación e índices adecuados; verificar tiempos con datos y carga definidos. |
| DA03 | Conservar originales y verificar la copia firmada | AC05, RF05, RF08, RF09, RC04, RC10 | Separar PDF original y versión firmada con PeruFirma; comprobar CMS y hash y registrar revisión humana de confianza. No se impone PDF/A sin requisito acordado. |
| DA04 | Acceso mínimo y privacidad del trámite | AC04, RF01, RF06, RC02 | Middleware, políticas, PDF privados, datos cifrados y seguimiento autorizado mediante código; credenciales solo en configuración del servidor. |
| DA05 | Comunicaciones con fallos controlados | AC02, RF10, RC05 | Separar envío de correo de la atención; prever colas y reintentos sin afirmar que existen. WhatsApp usa enlaces y envío manual; la automatización mediante API no existe. |
| DA06 | Mantener Laravel MVC con servicios | RC03, AC07 | Un despliegue con Blade, controladores, servicios, Eloquent y MySQL; responsabilidades transversales de validación, autorización y auditoría. |
| DA07 | Consistencia, fidelidad y trazabilidad | AC05, AC06, RF02–RF05, RF11, RF15, RF17 | Transacciones, unicidad, hash, historial de filiación y control de versión para evitar sobrescrituras y preservar el archivo. |
| DA08 | Pagos Mercado Pago y contraste con proveedores | RF06, RF16, RC06, RC08 | Encapsular la integración del proveedor, verificar TLS y respuestas y prevenir doble procesamiento. Mercado Pago se utiliza en sandbox; no se habilitan cobros reales de producción. |
| DA09 | Recuperación y configuración por entorno | AC08, RC02, RC09 | Respaldar de forma conjunta base, PDF y claves; separar configuración y datos de desarrollo de una futura operación publicada. |
