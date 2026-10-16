# Patrones de diseño

## Objetivo

Identificar patrones presentes en el desarrollo de Registro Civil y distinguirlos de futuras incorporaciones.

## Contexto

- Estilo: aplicación monolítica Laravel.
- Enfoque interno: MVC con servicios, Blade, Bootstrap y Eloquent/MySQL.
- Base: [componentes C4](../../modelo-c4/Nivel3-Diagrama-de-Componentes.md).

## Patrones aplicados

| Patrón | Dónde se usa | Problema que resuelve | Se implementa con | Archivos en el código |
|---|---|---|---|---|
| MVC | Interfaz, control y datos del archivo. | Distribuir responsabilidades de presentación, petición y persistencia. | Blade, controladores Laravel y Eloquent. | `ActaController.php`, `Acta.php`, `resources/views/actas/`. |
| Service Layer | Registro de actas y operaciones de apoyo. | Reunir coordinación y operaciones reutilizables fuera de la vista. | Servicios inyectados en controladores y otros servicios. | `ActaService.php`, `PdfValidationService.php`, `PdfStorageService.php`, `AuditoriaService.php`. |
| Active Record | Persistencia y relaciones. | Representar registros y operaciones de base de datos mediante modelos. | Eloquent ORM. | `Acta.php`, `ActaNacimiento.php`, `TramitePublico.php`. |
| Inyección de dependencias | Colaboración entre controlador y servicios. | Proporcionar colaboradores sin construirlos dentro de cada operación. | Constructor y contenedor de Laravel. | `ActaController.php`, `ActaService.php`. |
| Adapter, como función de integración | Contraste con APIsPerú. | Encapsular llamadas y traducir la respuesta de un proveedor. | Cliente HTTP dentro de un servicio concreto. | `ApisPeruService.php`, `PortalHttpOptions.php`. |

## Cómo funciona cada patrón

| Patrón | En pocas palabras |
|---|---|
| MVC | La vista presenta, el controlador coordina y el modelo representa datos y persistencia. |
| Service Layer | El controlador delega operaciones como registrar, validar o guardar PDF. |
| Active Record | `Acta` conoce su tabla, relaciones y operaciones Eloquent. |
| Inyección de dependencias | Laravel entrega los servicios que requiere el constructor. |
| Adapter | Un servicio concentra el lenguaje y respuesta del proveedor; no se afirma que exista una interfaz intercambiable para todos los proveedores. |

## Beneficio

Estas decisiones permiten localizar responsabilidades y probar operaciones concretas. La inyección de clases concretas facilita composición, pero no demuestra por sí sola inversión completa de dependencias. No existe evidencia suficiente para afirmar una capa Repository propia, Decorator de caché, Strategy de pagos o un dominio aislado de Eloquent.

## Patrones previstos para siguientes iteraciones

| Patrón | Dónde se aplicaría | Cuándo |
|---|---|---|
| Adapter | Integración Izipay. | Al definir la modalidad y contrato del proveedor; integración pendiente. |
| Observer / eventos y listeners | Avisos por cambios del trámite y entrega. | Si se implementa ese flujo y se decide separar suscriptores; no se presenta como mecanismo actual de notificaciones. |

La firma DNIe requiere seleccionar un componente compatible. No se añaden patrones solo para reproducir los del marketplace.
