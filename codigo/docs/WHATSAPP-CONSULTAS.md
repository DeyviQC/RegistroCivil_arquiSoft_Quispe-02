# Consultas manuales por WhatsApp

El seguimiento ofrece «Consultar por WhatsApp» únicamente cuando el pago está PAGADO, la sesión de correo sigue vigente y hay un contacto válido configurado. `PORTAL_WHATSAPP_CONTACTO` contiene el número público con código de país. El número elegido por el usuario se configura solo en el `.env` local, no en archivos de pruebas ni código.

Se abre un enlace `wa.me` con un texto que contiene exclusivamente el número corto de solicitud. No se incorporan DNI, correo, nombres, datos del acta, UUID ni enlaces privados. El enlace usa `noopener noreferrer` y `no-referrer`. El ciudadano debe pulsar Enviar dentro de WhatsApp; el servidor no envía mensajes ni usa la Cloud API.

La apertura del enlace no cambia el estado de la solicitud ni registra un mensaje como enviado, recibido o entregado. La respuesta del personal es manual; un mensaje por WhatsApp no autentica al solicitante, no confirma un pago y no autoriza entregar el acta.

El personal puede localizar el número en el filtro de la bandeja y responder con el enlace público de seguimiento del portal publicado. La descarga del acta permanece en el sistema, con correo verificado y después de revisión y publicación. No reenviar enlaces con tokens de sesión, adjuntar un acta basándose solo en el número del mensaje ni compartir una URL de localhost esperando que funcione en otro dispositivo.

Este cambio no envía mensajes reales ni requiere credenciales de una API. No se atribuye al número configurado carácter oficial de la municipalidad sin que la entidad lo autorice.
