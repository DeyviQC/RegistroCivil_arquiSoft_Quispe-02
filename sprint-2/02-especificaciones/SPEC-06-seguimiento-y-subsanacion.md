# SPEC-06 — Seguimiento ciudadano y respuesta a observaciones

| Campo | Valor |
|---|---|
| Historias | HU02 |
| Requisitos | RF06, RF18 |
| Decisiones | ADR-013, ADR-014, ADR-027 |
| Atributos | AC04 Seguridad y privacidad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que el ciudadano vuelva a su solicitud cuando quiera, vea su avance y responda lo que el Registro Civil le
pida, sin cuenta y sin exponer solicitudes ajenas.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-06.1 | un ciudadano en «Seguir mi solicitud» | ingresa su DNI y correo y verifica el código | ve **solo** sus solicitudes (búsqueda exacta por correo y `dni_hash`) y puede elegir una |
| CA-06.2 | datos que no corresponden a ninguna solicitud | se consulta | la respuesta es idéntica a la de datos válidos (no revela si existen) |
| CA-06.3 | un acceso verificado | pasan 30 minutos | el acceso vence; las páginas redirigen al seguimiento con aviso y los envíos devuelven 403 |
| CA-06.4 | un aviso por correo | el ciudadano pulsa «Ver mi solicitud» | entra con un enlace firmado de 7 días, sin pedir otro código; un enlace alterado o vencido no da acceso |
| CA-06.5 | una solicitud `OBSERVADA` | el ciudadano responde con texto (10–1500) y, opcionalmente, un PDF/JPG/PNG de hasta 5 MB | la solicitud vuelve a `PENDIENTE` y el personal ve la respuesta y el adjunto |
| CA-06.6 | una solicitud sin pagar | se abre el seguimiento | el pago aparece primero; tras pagar se pide elegir la entrega |
| CA-06.7 | una solicitud `RECHAZADA` | se abre el seguimiento | se muestra el motivo |

## Reglas de negocio
- Acceso a una sola solicitud por sesión, mediante el middleware `portal.acceso`.
- La respuesta del ciudadano no genera el correo de «pago recibido» (evento sin notificación).

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `PortalPublicoController@recuperar/solicitudes/seleccionar/estado/responder/acceso`, middleware `EnsurePortalAccess` |
| Negocio | `CodigoTramiteService`, `TramitePublico::transicionar()`, `EstadoTramiteMail::enlaceDirecto()` |
| Datos | `tramites_publicos.datos.subsanaciones`, archivos en `private/subsanaciones/{id}` |

## Tareas técnicas
- ✅ Recuperación por DNI y correo con respuesta genérica.
- ✅ Middleware único de acceso.
- ✅ Enlace firmado en los avisos.
- ✅ Formulario de respuesta con adjunto validado.

## Verificación
| Criterio | Prueba |
|---|---|
| CA-06.1 | `SeguimientoCiudadanoTest::test_consulta_sin_referencia_verifica_correo_y_limita_la_lista_y_la_seleccion`, `EndurecimientoTest::test_seguimiento_encuentra_por_correo_y_dni_del_solicitante` |
| CA-06.2 | `SeguimientoCiudadanoTest::test_datos_desconocidos_no_revelan_si_existen_solicitudes` |
| CA-06.3 | `MejorasFlujoTest::test_acceso_vencido_redirige_al_seguimiento_y_post_sigue_prohibido` |
| CA-06.4 | `MejorasFlujoTest::test_enlace_directo_del_correo_abre_la_solicitud_y_caduca`, `MejorasFlujoTest::test_el_correo_de_aviso_incluye_el_enlace_directo` |
| CA-06.5 | `RespuestaObservacionTest::test_el_ciudadano_ve_la_observacion_y_responde_con_un_documento`, `RespuestaObservacionTest::test_el_personal_ve_la_respuesta_y_abre_el_adjunto` |
| CA-06.6 | `MejorasFlujoTest::test_sin_pagar_el_pago_aparece_primero`, `MejorasFlujoTest::test_tras_pagar_se_pide_elegir_la_entrega_arriba_una_sola_vez` |
| CA-06.7 | `RespuestaObservacionTest::test_un_rechazo_muestra_el_motivo_al_ciudadano` |
