# SPEC-09 — Canales de entrega: correo y WhatsApp

| Campo | Valor |
|---|---|
| Historias | HU03 |
| Requisitos | RF10 |
| Decisiones | ADR-018, ADR-025 |
| Atributos | AC02 Disponibilidad, AC04 Privacidad |
| Estado | ✅ Implementado y probado (WhatsApp manual) |

## Objetivo
Que el ciudadano elija cómo recibir su acta y que el documento llegue sin pasos extra para el personal,
sin que la municipalidad escriba primero por WhatsApp.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-09.1 | un ciudadano con pago confirmado y sesión verificada | guarda sus preferencias | se registran sin enviar ningún documento todavía; sin sesión verificada se niega |
| CA-09.2 | el ciudadano eligió correo | el personal publica el documento | el PDF firmado se envía **automáticamente** al correo verificado (no a otro) |
| CA-09.3 | un envío por correo ya realizado | se vuelve a pedir | no se envía dos veces |
| CA-09.4 | el pago aún no está confirmado | se abre el seguimiento | no se ofrece WhatsApp |
| CA-09.5 | el pago está confirmado | el ciudadano pulsa «Escribir a Registro Civil» | se abre WhatsApp con un mensaje listo que solo menciona el número de solicitud; el sistema no envía nada por sí mismo |
| CA-09.6 | falta el número municipal o tiene formato inválido | se muestra el seguimiento | se omite el botón |
| CA-09.7 | el documento publicado y WhatsApp elegido | el personal entrega | tiene el PDF y un mensaje institucional listo para copiar y adjuntar |

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `CanalEntregaController@guardar/whatsapp/correo`, `EntregaDocumentoController@publicar` |
| Negocio | `EnvioDocumentoCorreo` (bloqueo para no duplicar), `WhatsAppConsulta`, `DocumentoFirmadoMail` |

## Tareas técnicas
- ✅ Preferencias de entrega por solicitud.
- ✅ Envío automático al publicar con verificación de hash.
- ✅ WhatsApp iniciado por el ciudadano y mensaje listo para el personal.
- ⬜ API de WhatsApp (trabajo futuro).

## Verificación
| Criterio | Prueba |
|---|---|
| CA-09.1 | `CanalEntregaTest::test_solo_la_sesion_verificada_guarda_los_canales_sin_enviar_documentos` |
| CA-09.2 | `CanalEntregaTest::test_publicar_envia_el_correo_automaticamente_si_el_ciudadano_lo_pidio` |
| CA-09.3 | `CanalEntregaTest::test_envia_solo_el_firmado_publicado_al_correo_verificado_y_evita_doble_envio` |
| CA-09.4 | `WhatsAppConsultaTest::test_no_ofrece_whatsapp_antes_de_confirmar_el_pago_ni_abre_el_seguimiento_sin_verificar` |
| CA-09.5 | `WhatsAppConsultaTest::test_despues_del_pago_ofrece_chat_solo_con_numero_de_solicitud_y_sin_enviar_nada` |
| CA-09.6 | `WhatsAppConsultaTest::test_omite_el_boton_si_falta_el_numero_o_el_formato_es_invalido` |
| CA-09.7 | `CanalEntregaTest::test_preferencias_visibles_al_cargar_firma_y_envios_solo_al_publicar` |
