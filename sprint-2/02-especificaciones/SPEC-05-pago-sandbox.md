# SPEC-05 — Pago con Mercado Pago y Yape (sandbox)

| Campo | Valor |
|---|---|
| Historias | HU01 |
| Requisitos | RF16 |
| Decisiones | ADR-022, ADR-014 |
| Atributos | AC02 Disponibilidad, AC05 Integridad |
| Estado | ✅ Implementado y probado (solo entorno de pruebas) |

## Objetivo
Cobrar S/ 10.30 por copia con Yape (destacado) o tarjeta, confirmando el pago **solo con la respuesta
verificada del proveedor**, sin cobros dobles y sin trabar la solicitud ante rechazos o fallos.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-05.1 | una solicitud `PENDIENTE_PAGO` con correo verificado | el ciudadano paga con tarjeta | se crea **una sola** orden de Mercado Pago, sin enviar datos personales, y se reutiliza si reintenta |
| CA-05.2 | un aviso (webhook) de Mercado Pago | llega con firma HMAC inválida | se rechaza; con firma válida confirma **una sola vez** |
| CA-05.3 | un aviso firmado | trae importe, moneda o cuenta incorrectos, o es un reembolso | no confirma el pago |
| CA-05.4 | el ciudadano vuelve del checkout | el navegador dice «aprobado» | el sistema **no confía** en eso: consulta a Mercado Pago antes de confirmar |
| CA-05.5 | no hay webhook ni túnel en local | el ciudadano pulsa «Ya pagué, comprobar» | se consulta el pago por referencia y se confirma o informa el rechazo |
| CA-05.6 | el ciudadano paga con Yape | la orden se aprueba y se verifica de vuelta | la solicitud pasa a `PENDIENTE`; nunca se confirma desde datos del navegador |
| CA-05.7 | Yape rechaza (por ejemplo, saldo insuficiente, HTTP 402) | el ciudadano reintenta | puede volver a pagar con Yape o con tarjeta; la solicitud no queda trabada |
| CA-05.8 | un corte de red tras enviar la orden | se reintenta | se reenvía con la **misma clave de idempotencia** (no se duplica el cobro) |
| CA-05.9 | credenciales de una cuenta real o de otro vendedor | se intenta pagar | no se crea ninguna orden (solo sandbox peruano verificado) |

## Reglas de negocio
- Tarifa fija `portal.tarifa_centimos = 1030`.
- Pago confirmado ⇒ estado `PENDIENTE` mediante `TramitePublico::transicionar()`; el pago no autoriza la entrega.
- Sin pago iniciado en 7 días ⇒ `VENCIDA` (SPEC-13).

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `MercadoPagoController@pagar/retorno/notificacion/comprobar`, `YapeOrdersController` |
| Negocio | `MercadoPagoService`, `YapeOrdersService`, `PortalHttpOptions` (TLS) |
| Datos | `pagos_tramite` (idempotencia, ids del proveedor, estado) |

## Tareas técnicas
- ✅ Checkout Pro con preferencia reutilizable.
- ✅ Webhook con HMAC y verificación de importe, moneda y cuenta.
- ✅ Yape vía Orders API con lectura de vuelta de la orden.
- ✅ Manejo de rechazo 402 e idempotencia tras timeout.
- ⬜ Cobros reales y devoluciones (requiere cuentas a nombre de la municipalidad; trabajo futuro).

## Verificación
| Criterio | Prueba |
|---|---|
| CA-05.1 | `MercadoPagoTest::test_crea_checkout_de_prueba_y_reutiliza_una_sola_orden_sin_enviar_datos_personales`, `PagoLimiteTest::test_un_fallo_al_crear_el_checkout_permite_reintentar_con_la_misma_referencia` |
| CA-05.2 | `MercadoPagoTest::test_webhook_valida_firma_y_confirma_una_sola_vez` |
| CA-05.3 | `MercadoPagoTest::test_rechaza_importe_moneda_cuenta_produccion_y_reembolso_aunque_este_firmado` |
| CA-05.4 | `MercadoPagoTest::test_retorno_no_confia_en_status_y_no_confirma_otra_solicitud` |
| CA-05.5 | `MercadoPagoTest::test_comprobar_confirma_un_pago_aprobado_sin_webhook_ni_tunel`, `MercadoPagoTest::test_comprobar_informa_rechazo_y_no_confirma_pagos_de_otra_referencia` |
| CA-05.6 | `YapeOrdersTest::test_confirma_solo_orden_verificada_y_no_envia_datos_del_ciudadano` |
| CA-05.7 | `YapeOrdersTest::test_rechazo_no_aprueba_y_permite_reintentar_con_yape_o_tarjeta`, `YapeOrdersTest::test_saldo_insuficiente_402_al_crear_no_traba_la_solicitud` |
| CA-05.8 | `YapeOrdersTest::test_tras_un_timeout_reenvia_con_la_misma_clave_de_idempotencia` |
| CA-05.9 | `MercadoPagoTest::test_app_usr_de_cuenta_real_o_de_otro_vendedor_no_crea_checkout`, `YapeOrdersTest::test_cuenta_productiva_no_envia_token_ni_orden` |
