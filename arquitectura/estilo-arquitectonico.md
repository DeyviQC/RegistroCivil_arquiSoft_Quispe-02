# PASO 5: DEFINIR PATRONES O ENFOQUE ARQUITECTÓNICO

Las dependencias internas mediante MVC con Servicios / Monolito Organizado por Capas (Clean Architecture simplificada)[cite: 10, 11].

| Elemento | Descripción aplicada al Registro Civil Municipal |
|---|---|
| **Patrón / enfoque arquitectónico** | Monolito por Capas (MVC con Servicios y Políticas)|
| **Objetivo** | Separar las responsabilidades del sistema (presentación, lógica de negocio y persistencia) dentro de un único despliegue centralizado, facilitando la atención administrativa y el portal ciudadano. |
| **¿Qué problema resuelve?** | Evita el acoplamiento directo entre la interfaz web en Blade/Bootstrap, los controladores de Laravel, los modelos Eloquent y los servicios externos (como correo SMTP, lector DNIe y API WhatsApp). |
| **Capas definidas** | • **Presentación / Vistas:** Panel administrativo y Portal ciudadano (Blade + Bootstrap).<br>• **Control:** Controladores, Middleware y Form Requests.<br>• **Aplicación:** Servicios, Políticas y reglas del negocio (gestión de actas, firmas, notificaciones y reportes).<br>• **Persistencia / Datos:** Modelos Eloquent, MySQL y almacenamiento privado. |
| **Beneficios** | • **Facilita el mantenimiento y las auditorías:** Permite registrar y trazar todas las operaciones sobre actas de nacimiento, matrimonio y defunción sin dispersar la lógica.<br>• **Permite escalar progresivamente:** Habilita la incorporación de nuevas funcionalidades (como el Portal Ciudadano, firma con DNIe y WhatsApp) sin modificar innecesariamente la estructura central.<br>• **Mejora la seguridad y organización:** Aísla el acceso al almacenamiento privado de los documentos PDF y aplica controles transversales de autorización por rol. |


## Diagrama de Arquitectura · Monolito por Capas (Alcance Completo)
## Diagrama de Arquitectura · Monolito por Capas (Alcance Completo)

```mermaid
flowchart TD
    %% Actores alineados horizontalmente arriba
    subgraph ACTORES ["ACTORES / ROLES"]
        direction LR
        Admin["Administrador<br/><i>Usuarios, atención y firma autorizada</i>"]
        Asistente["Asistente<br/><i>Registro y atención de solicitudes</i>"]
        Ciudadano["Ciudadano<br/><i>Solicita, consulta estado y descarga</i>"]
    end

    %% Bloque Central (Aplicación + Sistemas Externos alineados a la derecha)
    subgraph CONTENEDOR_CENTRAL [" "]
        direction LR

        subgraph MONOLITO ["UNA APLICACIÓN LARAVEL / UN DESPLIEGUE"]
            direction TD
            
            subgraph VISTAS ["Vistas / Interfaz"]
                direction LR
                PanelAdmin["Panel administrativo<br/>Blade + Bootstrap · Registro, usuarios y reportes"]
                PortalCiudadano["Portal ciudadano<br/>Blade + Bootstrap · Solicitudes y entrega digital"]
            end

            subgraph CONTROL ["Capa de Control"]
                Controladores["CONTROL: controladores + middleware + Form Requests<br/>Autenticación, validación de entradas y permisos por rol"]
            end

            subgraph APLICACION ["Capa de Aplicación: servicios, políticas y reglas de negocio"]
                direction TD
                subgraph APP_FILA1 [" "]
                    direction LR
                    Actas["Actas y documentos<br/>Nacimiento, matrimonio y defunción"]
                    UsuariosAuditoria["Usuarios y auditoría<br/>Acceso, acciones y trazabilidad"]
                    Reportes["Reportes<br/>Actas y sobres"]
                end
                subgraph APP_FILA2 [" "]
                    direction LR
                    Solicitudes["Solicitudes y entrega<br/>Estados, atención y descargas"]
                    Firma["Firma digital y validación<br/>DNIe, integridad y código / QR"]
                    Notificaciones["Notificaciones<br/>Avisos y seguimiento de envíos"]
                end
            end

            subgraph PERSISTENCIA ["Capa de Persistencia"]
                EloquentStorage["PERSISTENCIA: modelos Eloquent + Laravel Storage<br/>Actual: usuarios, actas y auditoría · Previsto: solicitudes, firmas, validaciones y notificaciones"]
            end

            VISTAS --> CONTROL
            CONTROL --> APLICACION
            APLICACION --> PERSISTENCIA
        end

        subgraph EXTERNOS ["SERVICIOS EXTERNOS / INTEGRACIONES"]
            direction TD
            SMTP["Correo SMTP<br/>Recuperación administrativa, avisos ciudadanos previstos"]
            DNIe["DNIe + lector<br/>Solución de firma compatible, Integración externa"]
            WhatsApp["API WhatsApp<br/>Notificaciones al ciudadano, Integración externa"]
        end
    end

    subgraph ALMACENAMIENTO ["ALMACENAMIENTO Y DATOS"]
        direction LR
        MySQL[("MySQL<br/>Datos actuales y tablas previstas del proyecto")]
        StoragePrivate[("Almacenamiento privado<br/>PDF originales y versiones firmadas previstas")]
    end

    %% Conexiones principales
    ACTORES --> MONOLITO
    PERSISTENCIA --> ALMACENAMIENTO
    APLICACION --> EXTERNOS

    %% Ocultar bordes de subgrafos auxiliares
    style CONTENEDOR_CENTRAL fill:none,stroke:none
    style APP_FILA1 fill:none,stroke:none
    style APP_FILA2 fill:none,stroke:none

    %% Estilos oscuros para subgrafos
    style ACTORES fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style MONOLITO fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style VISTAS fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style CONTROL fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style APLICACION fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style PERSISTENCIA fill:#222,stroke:#fff,stroke-width:1px,color:#fff
    style ALMACENAMIENTO fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff
    style EXTERNOS fill:#1a1a1a,stroke:#fff,stroke-width:1px,color:#fff

    %% Estilos oscuros para nodos
    style Admin fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Asistente fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Ciudadano fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style PanelAdmin fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style PortalCiudadano fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Controladores fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Actas fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style UsuariosAuditoria fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Reportes fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Solicitudes fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Firma fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style Notificaciones fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style EloquentStorage fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style MySQL fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style StoragePrivate fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style SMTP fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style DNIe fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff
    style WhatsApp fill:#2a2a2a,stroke:#fff,stroke-width:1px,color:#fff