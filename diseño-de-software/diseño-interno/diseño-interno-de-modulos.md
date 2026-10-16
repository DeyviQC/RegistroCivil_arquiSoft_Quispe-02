# Diseño interno de módulos

> Proyecto: Registro Civil · Backend: PHP / Laravel · Módulo de referencia: Actas.
> Enfoque: MVC con servicios dentro de una aplicación monolítica.

## 1. Propósito

Explicar dónde se ubican las responsabilidades del registro documental y cómo colaboran. «Módulo» designa una funcionalidad; la implementación distribuye archivos en las carpetas convencionales de Laravel y no exige una carpeta independiente por módulo.

## 2. Estructura de carpetas

```text
app/
├── Http/
│   ├── Controllers/ActaController.php
│   ├── Controllers/PdfController.php
│   └── Requests/
│       ├── StoreActaRequest.php
│       ├── StoreNacimientoRequest.php
│       ├── StoreMatrimonioRequest.php
│       └── StoreDefuncionRequest.php
├── Models/
│   ├── Acta.php
│   ├── ActaNacimiento.php
│   ├── ActaMatrimonio.php
│   └── ActaDefuncion.php
├── Policies/ActaPolicy.php
└── Services/
    ├── ActaService.php
    ├── PdfValidationService.php
    ├── PdfStorageService.php
    └── AuditoriaService.php
resources/views/actas/
routes/web.php
database/migrations/
tests/Feature/
```

Estas rutas pertenecen al proyecto de desarrollo; este repositorio contiene su documentación académica.

| Archivo | Capa | Responsabilidad | Importa de |
|---|---|---|---|
| `ActaController.php` | Control | Autorizar, recibir datos y coordinar registro o consulta. | Requests, modelos, servicios y Laravel. |
| `StoreActaRequest.php` y Requests por tipo | Validación | Definir reglas generales y específicas de entrada. | Laravel y reglas/modelos necesarios. |
| `ActaPolicy.php` | Autorización | Permitir operaciones al personal autorizado y rechazar edición/eliminación ordinaria. | `User`, `Acta`. |
| `ActaService.php` | Servicios | Coordinar duplicados, PDF, persistencia y auditoría. | Servicios de PDF y auditoría, modelos y transacciones Laravel. |
| `PdfValidationService.php` | Servicios | Comprobar extensión, MIME, tamaño, cabecera, hash y duplicados. | `UploadedFile`, `Acta`, configuración Laravel. |
| `PdfStorageService.php` | Servicios | Generar nombre físico y guardar, leer o eliminar archivos. | Laravel Storage y utilidades. |
| `Acta.php` | Modelo / persistencia | Datos centrales, relaciones y asignación de numeración con bloqueo. | Eloquent y modelos relacionados. |
| Modelos de detalle | Modelo / persistencia | Datos de nacimiento, matrimonio o defunción. | Eloquent. |
| `AuditoriaService.php` | Servicios | Registrar la operación y su contexto. | Modelo de auditoría y Laravel. |
| Vistas de actas | Presentación | Formularios, listado y ficha. | Blade, Bootstrap y datos del controlador. |

## 3. Reglas de dependencias

- Las rutas aplican middleware antes de atender operaciones protegidas.
- Controladores coordinan validación, autorización, servicios y respuesta; algunas consultas existentes acceden directamente a Eloquent.
- Los servicios pueden usar modelos, otros servicios y facilidades de Laravel. No se presupone independencia respecto del framework.
- Eloquent mantiene las relaciones y persistencia en MySQL; Storage conserva archivos privados fuera del acceso público directo.
- La vista no decide permisos ni confirma operaciones de pago.

## 4. Flujo: registrar un acta

1. El personal envía el formulario con PDF a `ActaController::store`.
2. Se valida entrada general y específica, se autoriza y se obtiene el año desde la fecha del acta.
3. `ActaService::registrar` comprueba duplicado por número/año/tipo y exige PDF.
4. Se valida PDF, se calcula SHA-256 y se comprueba duplicado por hash.
5. Se guarda el archivo mediante `PdfStorageService`.
6. Dentro de una transacción se guardan acta, detalle y auditoría; `Acta` asigna numeración con bloqueo de secuencia.
7. Si falla la transacción, se intenta eliminar el archivo previamente guardado. El sistema de archivos no participa en la transacción MySQL.
8. El controlador redirige a la ficha con mensaje de resultado.

## 5. Colaboración con otros módulos

Filiación revisa únicamente campos autorizados del detalle de nacimiento con versión e historial, manteniendo PDF y acta central. El trámite público usa búsqueda controlada y revisión humana sin exponer el archivo. Reportes consultan actas y disponibilidad documental con sus propios filtros. La firma, entrega y QR de autenticidad siguen pendientes; Izipay requiere integración.

## 6. Correspondencia con el código

El [nivel 4 de C4](../../modelo-c4/Nivel4-DiagramadeCodigo.md) presenta las clases y secuencia del flujo. Se documenta el comportamiento actual; los comentarios antiguos sobre borradores no cambian que el registro vigente exige PDF para los tres tipos. Las constantes históricas del modelo no implican que todos esos estados estén disponibles en la interfaz actual.
