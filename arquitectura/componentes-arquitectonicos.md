# Componentes arquitectónicos

Vista de responsabilidades de Registro Civil dentro de una aplicación Laravel MVC con servicios. Los componentes agrupan clases existentes; no representan servicios desplegados por separado.

```mermaid
flowchart TD
    Web["Panel y trámite público: Blade + Bootstrap"]
    Entrada["Rutas y controladores Laravel"]
    Acceso["Autenticación, validación y políticas"]
    Actas["Registro y consulta de actas"]
    PDF["Validación y almacenamiento de PDF"]
    Filiacion["Filiación y apoyo al cruce de parentesco"]
    Tramites["Trámites, seguimiento y atención"]
    Codigos["Códigos de correo"]
    Reportes["Reportes y auditoría"]
    Modelos["Modelos Eloquent"]
    Web --> Entrada
    Entrada --> Acceso
    Entrada --> Actas
    Entrada --> Filiacion
    Entrada --> Tramites
    Entrada --> Reportes
    Actas --> PDF
    Actas --> Modelos
    Filiacion --> Modelos
    Tramites --> Modelos
    Reportes --> Modelos
    Tramites --> Codigos
    Codigos --> Correo["Servicio de correo configurable"]
    Tramites --> API["Conector APIsPerú: validación externa pendiente"]
    Tramites -.-> Pago["Izipay: integración pendiente"]
    Tramites -.-> Futuro["Firma DNIe, entrega y QR: pendientes"]
    Modelos --> BD[("MySQL")]
    PDF --> Disco[("Almacenamiento privado")]
```

Las líneas discontinuas indican componentes pendientes. Con correo `log` no se envían mensajes. La integración anterior de pagos queda descartada: no se representa como implementación de Izipay. La aprobación actual no firma ni entrega documentos. WhatsApp está pospuesto.

El detalle de responsabilidades, carpetas y relaciones se encuentra en el [nivel 3 de C4](../modelo-c4/Nivel3-Diagrama-de-Componentes.md). Las decisiones se mantienen en el [registro ADR](../analisis-de-sistema/07-decisiones-arquitectonicas.md).
