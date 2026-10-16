# Enfoque arquitectónico: MVC con servicios

## Objetivo y aplicación

Registro Civil usa MVC de Laravel con servicios de apoyo para organizar presentación, control, reglas y persistencia. Blade y Bootstrap generan la interfaz desde la misma aplicación. Los servicios colaboran con Eloquent, Laravel Storage, correo y clientes HTTP; no se presupone un dominio independiente del framework.

| Elemento | Responsabilidad | Ejemplos del desarrollo |
|---|---|---|
| Vista | Mostrar datos y formularios y presentar respuestas sin exponer el archivo al público. | `resources/views/actas`, `portal`, `reportes`, `auth`. |
| Controlador | Recibir peticiones, coordinar validación y autorización y devolver vistas o archivos. | `ActaController`, `PortalPublicoController`, `AtencionPublicaController`, `FiliacionController`, `ReporteController`. |
| Servicio | Reunir operaciones reutilizables y coordinar tareas del negocio o integraciones. | `ActaService`, `PdfValidationService`, `PdfStorageService`, `AuditoriaService`, `CodigoTramiteService`, `CruceParentescoService`. |
| Modelo | Representar datos, relaciones y reglas asociadas al registro persistido. | `Acta`, `ActaNacimiento`, `ActaMatrimonio`, `ActaDefuncion`, `TramitePublico`, `PagoTramite`, `TramiteEvento`, `User`. |
| Controles transversales | Autenticar, validar entrada, limitar intentos, autorizar y registrar acciones. | Middleware, Form Requests, políticas y auditoría. |
| Persistencia e integración | Guardar datos y documentos y comunicarse con proveedores desde el servidor. | Eloquent/MySQL, Laravel Storage, correo, `ApisPeruService`, `PortalHttpOptions`; el componente Izipay está pendiente. |

`Solicitud` y `SolicitudEvento` conservan el flujo anterior con cuentas; el trámite público actual se representa mediante `TramitePublico`. No son nombres intercambiables para el mismo proceso.

## Diagrama de colaboración

```mermaid
flowchart TD
    Navegador["Navegador del personal o solicitante"]
    Rutas["Rutas y middleware"]
    Controlador["Controladores Laravel"]
    Validacion["Form Requests / validación"]
    Politicas["Policies / autorización"]
    Vistas["Vistas Blade + Bootstrap"]
    Servicios["Servicios de actas, PDF, códigos, parentesco y auditoría"]
    Modelos["Modelos Eloquent"]
    Storage["Laravel Storage"]
    Proveedores["Correo y conectores HTTP"]
    Futuro["Firma, entrega y QR: pendientes"]
    Navegador -->|"Petición HTTP"| Rutas
    Rutas --> Controlador
    Controlador --> Validacion
    Controlador --> Politicas
    Controlador --> Servicios
    Controlador --> Modelos
    Controlador -->|"Datos de respuesta"| Vistas
    Vistas -->|"HTML"| Navegador
    Servicios --> Modelos
    Servicios --> Storage
    Servicios --> Proveedores
    Modelos --> MySQL[("MySQL")]
    Storage --> PDF[("PDF privados")]
    Controlador -.-> Futuro
```

Las conexiones representan colaboraciones y acceso a datos; no afirman que toda consulta pasa por un servicio. La auditoría se invoca desde los responsables de cada operación, no desde la interfaz pública.

## Flujos representativos

1. **Registro de acta:** el controlador autoriza y valida la entrada; `ActaService` coordina validación del PDF, hash, duplicados, almacenamiento privado, persistencia transaccional y auditoría. Ante un fallo de persistencia se compensa el archivo guardado. El proceso registra un documento existente.
2. **Trámite público:** la búsqueda permite continuar solo con una coincidencia única y PDF disponible. Se guardan términos y datos, se verifica el correo con código. El paso de pago deberá integrarse con Izipay y confirmar su resultado mediante un mecanismo verificado del proveedor; permanece pendiente. La atención valida transición, versión y sustento cuando corresponde. Ninguna aprobación ejecuta firma ni entrega.
3. **Filiación:** el controlador valida y autoriza la revisión, bloquea el detalle de nacimiento, verifica versión y guarda los campos de padres con historial y hash del original. El cruce de parentesco es apoyo para la revisión humana.

## Estado y límites

El conector de APIsPerú existe, pero faltan pruebas reales con credenciales. El conector de Izipay aún debe implementarse. Correo `log` no envía mensajes. Firma DNIe, descarga protegida, QR de autenticidad, subsanación pública y colas/reintentos están pendientes; WhatsApp está pospuesto. Su incorporación deberá respetar MVC con servicios y la conservación del original.
