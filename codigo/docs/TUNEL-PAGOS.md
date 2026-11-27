# HTTPS temporal para Mercado Pago

El usuario autorizó un túnel público temporal para probar Mercado Pago. Se utiliza el ejecutable oficial `cloudflared` 2026.10.3 descargado de GitHub/Cloudflare, verificado contra el digest SHA-256 publicado en el release. No se instala como servicio y no requiere cuenta de Cloudflare.

## Iniciar

Desde el Explorador de Windows ejecutar `iniciar-tunel-pagos.cmd`. Requiere MySQL del desarrollo encendido. Inicia un servidor separado en loopback (prefiere 8002 y elige otro puerto libre si está ocupado), comprueba el bloqueo de rutas internas y crea un Quick Tunnel. El servidor habitual `127.0.0.1:8001` no se modifica. Los procesos se registran desde su creación para poder detener también intentos interrumpidos antes de obtener una URL.

El inicio solo anuncia éxito después de una conexión registrada con Cloudflare. Entonces guarda la dirección obtenida en `MERCADOPAGO_PUBLIC_URL` del `.env` de este desarrollo y limpia la caché de configuración. Los secretos no se imprimen. El estado/PID/tiempos de creación y los registros se guardan en `tmp/tunel`, excluido de Git.

El portal se abre con la URL HTTPS y `/tramite`. Configurar en Mercado Pago **Webhooks → Pruebas → Pagos** la URL HTTPS seguida de `/pagos/mercadopago/notificacion`. Guardar la firma secreta en `MERCADOPAGO_WEBHOOK_SECRET`, sin compartirla por chat. Iniciar las solicitudes desde HTTPS para que la sesión esté disponible al volver del proveedor. No exponer el enlace como servicio municipal de producción.

## Alcance publicado

El router del puerto 8002 permite solamente `/tramite`, sus rutas públicas y los dos endpoints de retorno/notificación de Mercado Pago. Permite CSS, JavaScript e imágenes municipales conocidas. Bloquea login, panel administrativo, usuarios, archivo interno, `.env`, documentos y ejecutables que existan en `public/img`. Rechaza segmentos de recorrido y archivos fuera de public. Las comprobaciones locales de estas rutas son obligatorias antes de lanzar Cloudflare.

El origen desactiva la depuración y usa cookies seguras y URLs HTTPS. El tráfico sigue usando los controles de sesión, permisos, tarifas, límites y verificación de pagos de Laravel. El modo de prueba de Mercado Pago continúa activo. El túnel no aprueba, cobra, firma ni envía mensajes automáticamente.

## Detener

Ejecutar `detener-tunel-pagos.cmd`. Detiene únicamente los dos procesos registrados tras comprobar PID, ejecutable y fecha de inicio; restaura la URL previa si aún corresponde a este túnel. No detiene el servidor 8001, MySQL ni procesos de otros proyectos. Conserva los PDF y los registros.

Al reiniciar un Quick Tunnel cambia la dirección: actualizar el Webhook en Mercado Pago. Al desplegar se sustituye por la URL fija del hosting HTTPS.

## Resultado de la comprobación actual

El túnel registró una conexión con Cloudflare usando QUIC. La comprobación pública devolvió 200 para /tramite y JavaScript, y 404 para /login, /.env y /usuarios. El intento anterior por HTTP2 recibía un certificado sustituido por Avast Web/Mail Shield Untrusted Root. Se cambió el transporte a QUIC, manteniendo la validación TLS nativa de cloudflared; no se modificó Avast ni se agregaron certificados de confianza.

La URL temporal se guarda en el estado local y en MERCADOPAGO_PUBLIC_URL. Falta configurar la firma secreta de Webhooks y completar la prueba de Checkout Pro para verificar el flujo de pago completo.

Fuente: https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/
