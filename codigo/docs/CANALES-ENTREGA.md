# Preferencias y entrega del documento

Después del pago, el ciudadano puede seleccionar correo, WhatsApp, ambos o ninguno en su seguimiento verificado. La selección y el número se guardan en el campo cifrado `datos.canales_entrega`; no se importan actas ni se modifica el proyecto entregado a la municipalidad.

El botón «Notificar por WhatsApp» prepara un mensaje al contacto configurado. El ciudadano debe pulsar enviar; abrirlo no acredita una comunicación ni la entrega del documento.

El personal sube la firma, revisa el documento y lo publica. Entonces aparecen las acciones de los medios solicitados:

- Correo: envía el mismo PDF firmado como adjunto al correo ya verificado. Se comprueba el hash antes de enviarlo y se registra el envío para bloquear clics posteriores duplicados. Ante un error SMTP, revisar la salida antes de reintentar; un fallo de conexión no garantiza que el servidor no haya aceptado el mensaje.
- WhatsApp: abre la conversación con el número indicado por el ciudadano. El personal adjunta su copia del PDF firmado y envía manualmente. Hay una descarga opcional si no dispone del archivo. No utiliza API ni marca automáticamente el documento como enviado.

Las preferencias no autorizan la entrega por sí solas: siguen siendo necesarios el pago, la aprobación, la revisión de firma y la publicación. Las pruebas usan correo simulado y documentos ficticios.

La capa visual activa es `municipal-institucional.css`, inspirada en la organización del portal de Renaper (https://www.argentina.gob.ar/interior/renaper): encabezado institucional, accesos a servicios y bloques de trámites. Conserva el escudo, la paleta y los datos de la municipalidad. La propuesta anterior de EQ Women fue retirada por petición del usuario y su CSS no se carga.
