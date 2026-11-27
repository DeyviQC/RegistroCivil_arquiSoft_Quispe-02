# Interfaz del ciudadano

La mejora usa `public/css/portal-cliente.css`, cargada únicamente por `portal.layout`. No altera la interfaz administrativa, la base de datos ni el proyecto entregado a la municipalidad.

Se conserva la portada con la plaza, el escudo y el verde municipal. Se incorporan superficies plateadas suaves, tamaños legibles, campos de 48 px y acciones consistentes. Los mensajes de seguimiento, datos y verificación se simplifican; las advertencias de pago de prueba, declaraciones, privacidad y requisitos de autorización permanecen disponibles.

El seguimiento evita prometer acceso exclusivo al titular: describe la verificación por correo que realmente se realiza. Sin movimientos, el texto distingue entre un pago confirmado y uno pendiente.

`portal-cliente.js` muestra y exige el número únicamente cuando se elige WhatsApp, conserva el funcionamiento básico sin JavaScript y evita clics repetidos en el mismo formulario mientras navega. Al volver mediante el historial restaura los botones. No añade reintentos automáticos ni cambia la confirmación de pagos.

La revisión visual utiliza modelos ficticios sin guardar registros ni enviar correos. Las vistas temporales se retiran después de comprobarlas.
