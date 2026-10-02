# Arquitectura inicial del sistema

## Diagrama de arquitectura

```mermaid
flowchart TD
    subgraph ACTORES ["ACTORES"]
        Ciudadano["Ciudadano"] ~~~ Asistente["Asistente"] ~~~ Admin["Administrador"]
    end

    subgraph PRESENTACION ["PRESENTACIÓN"]
        Web["Aplicación Web (Blade + Bootstrap)"]
    end

    subgraph NEGOCIO ["LÓGICA DE NEGOCIO"]
        Usuarios["Usuarios"] ~~~ Actas["Registro de Actas"] ~~~ Solicitudes["Solicitudes"] ~~~ Firma["Firma Digital"] ~~~ Auditoria["Auditoría y Reportes"]
    end

    subgraph DATOS ["DATOS"]
        BD["Base de datos (MySQL)"]
    end

    subgraph EXTERNOS ["SISTEMAS EXTERNOS"]
        DNIe["Lector DNIe"] ~~~ WhatsApp["Servicio de WhatsApp"] ~~~ RENIEC["API RENIEC"]
    end

    ACTORES --> PRESENTACION
    PRESENTACION --> NEGOCIO
    NEGOCIO --> DATOS
    DATOS -->|"integraciones"| EXTERNOS

    style ACTORES fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style PRESENTACION fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style NEGOCIO fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style DATOS fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style EXTERNOS fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff

    style Ciudadano fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Asistente fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Admin fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Web fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Usuarios fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Actas fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Solicitudes fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Firma fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Auditoria fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style BD fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style DNIe fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style WhatsApp fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style RENIEC fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff 
```



## Descripción

La arquitectura inicial se organiza en tres capas principales:

- **Presentación:** Permite la interacción de los usuarios (Ciudadano, Asistente y Administrador) con el sistema mediante la aplicación web desarrollada en Blade y Bootstrap.
- **Lógica de negocio:** Contiene los principales módulos responsables de las funcionalidades del sistema: usuarios, registro de actas (nacimiento, matrimonio, defunción), solicitudes, firma digital con DNIe, y auditoría con reportes.
- **Datos:** Permite almacenar y consultar la información mediante una base de datos MySQL y el repositorio de almacenamiento de archivos.

- **Sistemas externos:** Además, el sistema se integra con componentes externos como el Lector DNIe para la firma digital, el servicio de 
      notificaciones por WhatsApp y la API de RENIEC para la consulta y validación de datos.
