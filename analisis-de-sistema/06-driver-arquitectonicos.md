# Drivers Arquitectónicos

## Identificar drivers arquitectónicos
**Objetivo:** integrar los elementos identificados anteriormente y determinar cuáles tienen una influencia significativa en las decisiones de arquitectura.

| ID | Driver arquitectónico | Origen | ¿Por qué influye en la arquitectura? |
| :--- | :--- | :--- | :--- |
| **DA01** | El sistema debe permitir incrementar progresivamente la cantidad de usuarios y documentos sin degradar su funcionamiento. | AC03 – Escalabilidad | Puede influir en la estrategia de almacenamiento privado de PDFs y optimización de consultas en MySQL. |
| **DA02** | Las operaciones habituales de consulta deben responder en un tiempo adecuado y rápido. | AC01 – Rendimiento | Influye en el diseño de las consultas a la base de datos y la estructuración del almacenamiento de actas digitalizadas. |
| **DA03** | Las actas electrónicas deben verificarse mediante firma digital con DNIe e incluir código QR. | AC05 – Integridad | Influye en la integración con lectores de tarjetas inteligentes, generación de hash criptográfico y empaquetado PDF/A. |
| **DA04** | El sistema debe proteger las cuentas de usuarios, accesos por roles y repositorios documentales. | AC04 – Seguridad | Influye en la implementación de autenticación segura (Laravel Auth), control de accesos RBAC y repositorios privados no públicos. |
| **DA05** | El sistema debe integrarse con el servicio de WhatsApp para notificar el estado de las solicitudes. | RC05 – Servicio de notificaciones | Condiciona la forma de comunicación e integración asíncrona mediante APIs web externas. |
| **DA06** | El backend debe desarrollarse en Laravel y el frontend en Blade con Bootstrap sobre MySQL. | RC03 – Stack tecnológico | Limita la tecnología base y define la arquitectura del sistema bajo el patrón MVC del framework. |