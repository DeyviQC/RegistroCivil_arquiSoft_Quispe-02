## Diagrama de Enfoque Arquitectónico · MVC con Servicios (Alcance Completo)

```mermaid
flowchart TD
    subgraph ACTORES ["ACTORES Y ROLES"]
        Personal["Personal administrativo<br/><i>Administrador y asistente</i>"] ~~~ Ciudadano["Ciudadano<br/><i>Solicitudes, seguimiento, descarga y consulta por QR</i>"]
    end

    subgraph MVC ["ENFOQUE MVC CON SERVICIOS"]
        subgraph VISTA_CTRL ["Capa de Presentación y Control"]
            Vista["VISTA · Blade + Bootstrap<br/>Actual: login, actas, usuarios y reportes<br/>Previsto: portal, solicitudes, entrega y QR"] <-->|"Petición / Respuesta"| Controlador["CONTROLADOR · Laravel<br/>Actual: Auth, Acta, Usuario, Auditoría y Reporte<br/>Previsto: Solicitud, Firma, Entrega y Validación"]
        end

        subgraph SERVICIOS ["SERVICIOS Y POLÍTICAS · apoyo a los controladores"]
            GestionActas["Gestión de actas<br/>Registro, numeración y PDF"] ~~~ AuthAuditoria["Autenticación y auditoría<br/>Usuarios, permisos y operaciones"] ~~~ ReportesServ["Reportes<br/>Actas y sobres; reportes de solicitudes previstos"]
            SolicitudEntrega["Solicitud y entrega<br/>Pendiente → revisión → atención"] ~~~ FirmaQR["Firma digital y QR<br/>Solo encargado autorizado; validación criptográfica"] ~~~ Notificaciones["Notificaciones<br/>Correo y WhatsApp al ciudadano"]
        end

        subgraph MODELO ["MODELO · Eloquent"]
            Eloquent["MODELO · Eloquent<br/>Actual: User, Acta, Nacimiento, Matrimonio, Defunción y Auditoria<br/>Previsto: Solicitud, FirmaDigital, Validación, Entrega y Notificación"]
        end

        Controlador --> SERVICIOS
        SERVICIOS --> MODELO
    end

    subgraph PERSISTENCIA ["DATOS Y PERSISTENCIA"]
        MySQL[("MySQL<br/>Persistencia y consulta de los registros")] ~~~ Storage[("Storage privado<br/>PDF originales y documentos firmados previstos")]
    end

    subgraph EXTERNOS ["SERVICIOS EXTERNOS"]
        DNIe["DNIe + lector<br/>Adaptador de firma compatible, certificados y verificación"] ~~~ Comunicacion["Servicios de comunicación<br/>SMTP: recuperación disponible; WhatsApp y avisos previstos"]
    end

    subgraph TRANSVERSAL ["CONTROLES TRANSVERSALES"]
        Transversales["Middleware: autenticación<br/>Form Requests: validación<br/>Policies: autorización<br/>Auditoría: trazabilidad"]
    end

    Personal --> Vista
    Ciudadano --> Vista
    MODELO --> PERSISTENCIA
    SERVICIOS --> EXTERNOS

    %% Estilos oscuros para subgrafos
    style ACTORES fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style MVC fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style VISTA_CTRL fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style SERVICIOS fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style MODELO fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style PERSISTENCIA fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style EXTERNOS fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style TRANSVERSAL fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff

    %% Estilos oscuros para nodos
    style Personal fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Ciudadano fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Vista fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Controlador fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style GestionActas fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style AuthAuditoria fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style ReportesServ fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style SolicitudEntrega fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style FirmaQR fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Notificaciones fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Eloquent fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style MySQL fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Storage fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style DNIe fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Comunicacion fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Transversales fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff