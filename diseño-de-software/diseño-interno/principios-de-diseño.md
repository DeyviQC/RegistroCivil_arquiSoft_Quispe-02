# Principios de diseño (SOLID)

## Objetivo

Explicar cómo las responsabilidades actuales se relacionan con SOLID y señalar los límites de la implementación sin atribuirle estructuras inexistentes.

## Contexto

- Estilo: monolito Laravel con MVC y servicios.
- Módulo de referencia: Actas y documentos PDF.
- Persistencia: modelos Eloquent sobre MySQL.

## SOLID en el módulo Actas

| Principio | Dónde se aplica (capa) | Cómo se cumple en Registro Civil | Archivos |
|---|---|---|---|
| S · Responsabilidad única | Control, servicios y políticas. | Se separan recepción, validación documental, almacenamiento y auditoría. Es una aplicación parcial: algunos controladores y modelos reúnen más de una responsabilidad. | `ActaController.php`, `PdfValidationService.php`, `PdfStorageService.php`, `AuditoriaService.php`, `ActaPolicy.php`. |
| O · Abierto/cerrado | Servicios e integraciones. | La separación de proveedores permite concentrar cambios, pero no se demuestra extensión sin modificar código: el registro distingue tipos mediante `match` y los conectores son concretos. | `ActaService.php`, `ApisPeruService.php` e integración de pagos Mercado Pago. |
| L · Sustitución de Liskov | Herencia del framework. | Los modelos extienden Eloquent; `Acta` especializa la inserción. No hay implementaciones intercambiables propias que permitan afirmar cumplimiento general; debe comprobarse el comportamiento heredado. | `Acta.php` y modelos de detalle. |
| I · Segregación de interfaces | Servicios. | Los servicios tienen operaciones enfocadas, pero no se identifica una familia de interfaces propias segregadas. No se crean contratos ficticios para justificar el principio. | `PdfValidationService.php`, `PdfStorageService.php`. |
| D · Inversión de dependencias | Control y servicios. | Hay inyección de colaboradores concretos. El servicio usa Eloquent y facilidades Laravel, por lo que la inversión hacia abstracciones independientes es limitada. | Constructores de `ActaController.php`, `ActaService.php`. |

## Qué pasaría sin separación de responsabilidades

| Situación real | Sin separación | Con la organización actual |
|---|---|---|
| Cambia una validación PDF | La regla puede repetirse en formularios y controladores. | La comprobación documental se concentra en `PdfValidationService`. |
| Cambia el nombre o destino del archivo | La operación de disco se dispersa. | `PdfStorageService` concentra el acceso al archivo. |
| Cambia un permiso | La vista podría permitir una operación sin control del servidor. | Middleware y políticas aplican restricciones del lado del servidor. |
| Se usa Mercado Pago en sandbox | La integración de pagos podría quedar mezclada con la interfaz o confundirse con cobros reales. | El proveedor se utiliza en entorno de pruebas; producción no está habilitada. |

## Relación con el enfoque y los patrones

| Principio | Lo cumple en el proyecto |
|---|---|
| Responsabilidad única | MVC y servicios ofrecen evidencia de separación parcial. |
| Abierto/cerrado y Liskov | No hay evidencia de intercambiabilidad general de proveedores; requiere contratos y verificación si se adopta. |
| Segregación de interfaces | No se acredita mediante interfaces propias en el módulo documentado. |
| Inversión de dependencias | Inyección existente de clases concretas; dependencia de Eloquent y Laravel explícita. |

Las mejoras se evaluarán cuando resuelvan un problema del proyecto. Esta documentación no exige migrar a Clean Architecture ni añadir repositorios, interfaces o capas para imitar la referencia.
