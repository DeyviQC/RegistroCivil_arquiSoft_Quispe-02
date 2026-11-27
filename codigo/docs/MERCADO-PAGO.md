# Mercado Pago — Checkout Pro de prueba

El flujo nuevo utiliza Checkout Pro (Preferences API). El ciudadano pulsa «Continuar con Mercado Pago», paga en el checkout del proveedor y vuelve al seguimiento. La notificación firmada o una consulta autenticada al API confirma el resultado. El parámetro `status=approved` del navegador no acredita un pago.

## Configuración pendiente

1. Crear una aplicación de Checkout Pro en Mercado Pago Developers Perú.
2. Guardar en el `.env` privado del desarrollo, sin compartir valores por chat:
   - `MERCADOPAGO_MODE=test`
   - `MERCADOPAGO_ACCESS_TOKEN`: Access Token obtenido con el selector en **Prueba**. Checkout Pro también entrega tokens `APP_USR-…` de su vendedor de prueba; el prefijo por sí solo no determina el ambiente.
   - `MERCADOPAGO_WEBHOOK_SECRET`: clave de firma de las notificaciones de prueba.
   - `MERCADOPAGO_COLLECTOR_ID`: identificador numérico del vendedor asociado a esas credenciales.
   - `MERCADOPAGO_PUBLIC_URL`: URL HTTPS del entorno de desarrollo/pruebas, sin barra final ni parámetros.
3. El sitio debe ser accesible por HTTPS desde Mercado Pago. Localhost no sirve para recibir sus Webhooks. Usar un entorno de pruebas público o un túnel explícitamente autorizado; no se ha publicado el proyecto ni abierto un túnel automáticamente. Acceder al portal desde esa URL al iniciar la solicitud para conservar la sesión al retornar.
4. En Webhooks, configurar el evento **Pagos (`payment`)** y la URL `https://TU-DOMINIO/pagos/mercadopago/notificacion`. Configurar la firma secreta correspondiente al mismo ambiente.
5. Limpiar la configuración con `php artisan config:clear`. Ejecutar la migración nueva en la base de desarrollo.
6. Usar exclusivamente las cuentas y tarjetas de prueba indicadas por Mercado Pago. La prueba real del checkout queda pendiente de estas credenciales y de la URL HTTPS.

No se necesita Public Key para esta modalidad de redirección: el Access Token se usa únicamente en el servidor. La implementación bloquea el modo producción. Para tokens `APP_USR-…`, antes de crear una preferencia comprueba `/users/me`: el ID debe coincidir con el vendedor configurado, el país debe ser Perú (`MPE`) y la cuenta debe llevar la etiqueta `test_user`. No se aceptan cuentas reales. La confirmación además exige `live_mode=false`.

## Comprobaciones

- API y TLS oficiales, sin seguir redirecciones ni exponer respuestas del proveedor o secretos.
- Tarifa del servidor en PEN, referencia UUID local, vendedor esperado y `live_mode=false`.
- HMAC SHA-256 de Webhooks usando el ID de la query normalizado por PHP (`data.id` / `data_id`), request-id y timestamp. El ID del cuerpo, si existe, debe coincidir.
- Consulta posterior a `/v1/payments/{id}` y transición atómica únicamente para `approved`, sin reembolsos, importe/moneda/cuenta correctos.
- Una sola actualización, evento y versión por pago; duplicados se reconocen sin volver a procesarlos.
- La vuelta del navegador exige sesión de correo verificado y un pago de esa misma solicitud. Las notificaciones pueden confirmar aunque la sesión haya vencido.
- No se envían DNI, contenido del acta, nombre ni correo del ciudadano al crear la preferencia. El checkout solicita al comprador los datos que corresponden al proveedor.

## Historial y reintentos

Las órdenes de prueba antiguas de Izipay/TAYPI quedan como historial: sus callbacks se eliminaron. Esas solicitudes no cambian de proveedor ni se les ofrece otra pasarela (el ciudadano ve que debe consultar a Registro Civil). Las nuevas solicitudes usan Mercado Pago.

Una preferencia guardada se reutiliza; no se crea otra por cada clic. Si la creación falla antes de guardar la respuesta, el siguiente clic vuelve a crearla con la **misma** referencia (`external_reference`): una preferencia no es un cobro y cualquier pago se concilia con esa referencia. Reembolsos y contracargos posteriores requieren revisión municipal y no están automatizados en esta integración de prueba.

## Comprobar un pago sin túnel ni webhook

En la página de estado, después de abrir el checkout, aparece **«Ya pagué, comprobar pago»**. El servidor consulta `GET /v1/payments/search?external_reference=…` con el Access Token, descarta resultados de otra referencia o de otro vendedor y confirma cualquier pago aprobado mediante la misma validación completa (`/v1/payments/{id}`: importe, moneda, vendedor, `live_mode=false`, sin reembolsos). Funciona desde `127.0.0.1` sin túnel ni webhook, así que sirve para demostraciones en la laptop.

Si el navegador vuelve de Mercado Pago sin la sesión del portal (por ejemplo, por otro dominio), el retorno igualmente valida el pago con la API y envía al ciudadano al seguimiento para que vuelva a ingresar.

## Cómo hacer un pago de prueba aprobado

1. Abre el checkout en una **ventana de incógnito** e inicia sesión con un **usuario de prueba comprador** (Tus integraciones → Tu aplicación → Cuentas de prueba). Si pide un código por correo, se obtiene en esa misma sección.
2. Paga con **tarjeta de prueba**, por ejemplo Visa `4009 1753 3280 6176` o Mastercard `5031 7557 3453 0604`, CVV `123`, vencimiento `11/30`, titular **`APRO`** y documento `123456789`.
3. Otros titulares simulan resultados: `OTHE` rechazado, `CONT` pendiente, `FUND` fondos insuficientes, `SECU` código de seguridad inválido.
4. Vuelve al portal y pulsa «Ya pagué, comprobar pago».

**Yape dentro de Checkout Pro:** la documentación no publica datos de prueba de Yape para el checkout redirigido, por eso en sandbox suele terminar en «Yape rechazó tu pago». Para demostrar Yape, usa la simulación propia del portal («Probar pago con Yape», más abajo) con el celular `111111111` y OTP `123456`, que sí está documentada. Esa opción solo aparece mientras la solicitud no tenga una preferencia de Checkout Pro creada.

Fuentes: https://www.mercadopago.com.pe/developers/es/docs/checkout-pro/integration-test/test-purchases y https://www.mercadopago.com.pe/developers/es/docs/checkout-api-orders/integration-test/yape

## Fuentes oficiales

- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-preferences/create-payment-preference
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-preferences/configure-back-urls
- https://www.mercadopago.com.pe/developers/es/docs/checkout-pro-preferences/additional-content/notifications/webhooks

Las pruebas automáticas usan SQLite y respuestas HTTP ficticias. No crean preferencias ni cargos reales.

## Simulación Yape con Orders API

`/tramite/yape` ofrece una simulación por API independiente del checkout externo. Requiere la misma sesión ciudadana con identidad contrastada y `MERCADOPAGO_PUBLIC_KEY` de prueba de la misma aplicación del Access Token. Solo admite celulares ficticios documentados 111111111–111111118; el OTP 123456, correo test_payer@example.com y DNI 12345678 son constantes de prueba. Nunca se transmiten datos reales del ciudadano ni se recogen OTP reales.

Se verifica que el vendedor autenticado sea una cuenta de prueba peruana. Se genera un token por intento y se crea una order con processing_mode=automatic, monto calculado en servidor y X-Idempotency-Key persistida antes del envío. Se guarda el identificador de order y se consulta GET /v1/orders/{id}; solo se confirma si coinciden vendedor, referencia, moneda, país, importes pagados y estados de order y pago. La aprobación actualiza la solicitud a pendiente de revisión municipal, nunca autoriza la entrega.

Un timeout después de enviar la order requiere reconciliación: no se crea otra al pulsar nuevamente. Si ya existe order, se vuelve a consultar. Un rechazo conserva la solicitud sin pagar; para otro escenario se usa otra solicitud de prueba. El bloqueo compartido con Checkout Pro impide crear órdenes en ambos flujos para una misma solicitud. Las preferencias ya abiertas se conservan: para probar Orders se necesita una solicitud nueva. No se modifica el historial anterior. No hay cobros productivos habilitados ni QR.

Validación automática: YapeOrdersTest cubre aprobación, rechazo, monto alterado, sesiones, celular real, cuenta productiva, intentos ambiguos y exclusión de otro checkout. Comprobación contra Mercado Pago completada el 25/10/2026 con credenciales de prueba: token HTTP 200, creación de order HTTP 201, consulta HTTP 200, status processed/accredited, PEN 10.30 pagados y coincidencia de vendedor y referencia. Se usaron datos ficticios y una referencia técnica independiente; no se modificaron solicitudes existentes.

Referencia: https://www.mercadopago.com.pe/developers/es/docs/checkout-api-orders/integration-test/yape
