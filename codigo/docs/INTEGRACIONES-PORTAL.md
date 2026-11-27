# Trámite público y proveedores

Implementación en desarrollo: `/tramite`. La antigua pantalla de registro y `/ciudadano/solicitudes` redirigen al portal. El módulo de cuentas ciudadanas se eliminó (no tenía datos); el panel solo admite personal (administrador y asistente).

> **Pasarelas retiradas (nov. 2026):** TAYPI e Izipay se eliminaron del código. Los pagos de prueba se hacen solo con Mercado Pago (Checkout Pro y Yape Orders, sandbox); ver `MERCADO-PAGO.md`. Las referencias a TAYPI de este documento son históricas.

## Implementado

- Aceptación versionada de términos de demostración, sin casilla preseleccionada.
- Búsqueda por tipo, fecha del hecho y nombres normalizados, sin devolver datos del archivo. Solo coincidencia única con PDF disponible permite continuar. No encontrado no significa inexistente en físico.
- Datos del solicitante cifrados en base de datos mediante APP_KEY. El respaldo diario guarda `app-key.enc` cifrado con `RESPALDO_FRASE`; se recupera con `php artisan respaldo:clave`. El DNI tiene además un índice ciego (`dni_hash`, HMAC) para buscar sin descifrar.
- APIsPERU: contraste DNI, nombres y dígito si el proveedor lo devuelve. Nacimiento y ubigeo no están en su esquema DNI documentado y permanecen declarados. No acredita por sí solo identidad ni derecho a recibir documentos.
- Correo obligatorio, WhatsApp opcional. Código de 6 dígitos, 10 minutos, cinco intentos, hash en base de datos y un minuto entre reenvíos. El código no se muestra en la web. MAIL_MAILER=log no envía correos reales; el código queda en el log privado para pruebas.
- Acceso de seguimiento mediante referencia, correo y nuevo código; autorización de sesión válida por 30 minutos.
- Conector TAYPI sandbox: S/10,30 del servidor, firma HMAC, referencia e idempotencia persistentes; valida importe, estado, UUID y QR PNG de la respuesta. Cobro de producción bloqueado expresamente.
- Notificación de pago firmada, validación de moneda/importe/referencia/proveedor y protección contra doble procesamiento. Solo la notificación autenticada marca pagado, nunca una redirección del navegador.
- Bandeja `/atencion-ciudadana` para administrador/asistente, revisión humana, observaciones, aprobación o rechazo y auditoría. Aprobar no firma ni entrega documentos.

## Credenciales locales

Agregar a `.env` sin publicarlas ni enviarlas al chat:

```
APISPERU_TOKEN=
TAYPI_SANDBOX=true
TAYPI_PUBLIC_KEY=
TAYPI_SECRET_KEY=
TAYPI_WEBHOOK_SECRET=
```

El sandbox de TAYPI pide RUC según su documentación. Se necesita autorización del proveedor para acceso académico sin RUC o una cuenta cuyo titular real y representación estén autorizados. Nunca inventar RUC ni usar el institucional como propio.

Webhook: `/pagos/taypi/notificacion`. Para pruebas externas necesita URL pública protegida; no se ha abierto ni publicado el servidor municipal. La excepción CSRF es exclusiva de ese endpoint, que exige firma del proveedor.

## Límites y pendientes

### Parentesco y representación

El formulario requiere relación con el titular (titular, madre, padre, representante u otro) y motivo; permite informar el DNI del titular por separado. Se conserva el flujo pago → revisión humana. La declaración no acredita parentesco. Antes de aprobar solicitudes de terceros, actas de menores o nacimientos sin fecha conocida, el personal debe confirmar que revisó el vínculo e indicar el sustento; se registra usuario, fecha y sustento junto a la decisión auditada. Marcar «titular» no evita el control si el nacimiento corresponde a un menor. No se extraen automáticamente los nombres de los padres ni se ha añadido carga de documentos: el personal consulta el PDF original y observa cuando necesita sustento adicional. Los requisitos y devoluciones deben definirse por la municipalidad.

No se ha hecho ninguna consulta real de DNI ni orden externa por falta de credenciales. La forma exacta de respuesta TAYPI debe contrastarse en sandbox antes de declarar la integración operativa. No generar otra orden cuando vence: por ahora requiere conciliación antes de reintentar, para evitar doble pago.

Pendiente: integración y validación criptográfica de firma DNIe, entrega protegida, notificación de entrega, colas y reintentos de correo, subsanación pública, QR de autenticidad, separación nube/municipalidad y sincronización, backups y publicación. WhatsApp pospuesto por el usuario.

Los términos no son una aprobación legal. Requieren revisión municipal antes de producción, especialmente conservación, derechos sobre datos, cancelaciones y devoluciones.

## Documentación consultada

En desarrollo, `PORTAL_HTTP_CA_FILE` permite indicar un bundle local de autoridades de confianza para APIsPerú y TAYPI. Se corrigió un error cURL 60 del equipo usando el bundle de certificados de Windows ya disponible en almacenamiento privado. La documentación pública de APIsPerú respondió HTTP 200 con ese bundle, sin enviar DNI ni credenciales. TLS siempre se verifica; un archivo configurado inexistente bloquea la conexión. En otro servidor, dejar la variable vacía para usar sus certificados del sistema o configurar su propio bundle de confianza.

- https://docs.taypi.pe/integracion
- https://docs.taypi.pe/seguridad
- https://docs.taypi.pe/webhooks
- https://docs.taypi.pe/sdks/php
- https://dniruc.apisperu.com/doc/swagger

