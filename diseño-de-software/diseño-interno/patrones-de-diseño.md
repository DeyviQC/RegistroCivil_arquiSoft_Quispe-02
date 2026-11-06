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
| Adapter, como función de integración | Contraste APIsPerú y pagos Mercado Pago en sandbox. | Encapsular llamadas y traducir respuestas del proveedor. | Cliente HTTP y servicios concretos. La firma con PeruFirma se realiza externamente y el PDF se carga manualmente. | `ApisPeruService.php`, `PortalHttpOptions.php` y la integración de pagos. |

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
| Adapter | Evolución de las integraciones con Mercado Pago y APIsPerú. | Revisar los adaptadores concretos antes de cambiar proveedores o habilitar producción; PeruFirma se usa fuera de la aplicación y no mediante una integración web. |
| Observer / eventos y listeners | Avisos por cambios del trámite y entrega. | Si se decide separar suscriptores de los envíos actuales; el correo adjunto es síncrono y WhatsApp manual, sin afirmar que exista ese mecanismo de eventos. |

El recorrido principal firma manualmente con PeruFirma y luego carga el PDF verificado. No se añaden patrones solo para reproducir los del marketplace.
