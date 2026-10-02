# Atributos de Calidad

## Caso / Escenario General
Durante un periodo de alta demanda (como campañas municipales de rectificación o picos de solicitudes virtuales de actas), la plataforma web podría recibir una gran cantidad de ciudadanos solicitando, consultando y verificando documentos digitalmente de manera simultánea.

## Lista de Atributos de Calidad

| ID | Atributo de calidad | Escenario de calidad |
| :--- | :--- | :--- |
| **AC01** | Rendimiento | Las operaciones habituales de consulta de actas y verificación de solicitudes deben responder en un tiempo adecuado y rápido para el usuario[cite: 17]. |
| **AC02** | Disponibilidad | La plataforma web debe permanecer disponible para solicitudes y consultas ciudadanas durante el horario definido por la institución[cite: 17]. |
| **AC03** | Escalabilidad | El sistema debe permitir incrementar progresivamente la cantidad de usuarios y documentos almacenados sin afectar significativamente su funcionamiento[cite: 17]. |
| **AC04** | Seguridad | El sistema debe utilizar autenticación segura (Laravel Auth), control de acceso por roles (RBAC), cifrado HTTPS/TLS y almacenamiento de documentos en un repositorio privado[cite: 17, 21]. |
| **AC05** | Integridad | Las actas electrónicas expedidas deben poder verificarse mediante firma digital con DNIe y código QR, garantizando que el documento no haya sido alterado[cite: 16, 17]. |
| **AC06** | Auditoría | Toda creación, modificación, firma, validación y entrega de actas debe quedar registrada con trazabilidad inmutable en el sistema[cite: 17]. |