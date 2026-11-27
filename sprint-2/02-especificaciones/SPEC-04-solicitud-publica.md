# SPEC-04 — Solicitud pública y verificación de correo

| Campo | Valor |
|---|---|
| Historias | HU01 |
| Requisitos | RF06 |
| Decisiones | ADR-013, ADR-021, ADR-027, ADR-031 |
| Atributos | AC04 Seguridad y privacidad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que cualquier ciudadano, **sin crear una cuenta**, compruebe si su acta está disponible, declare quién es y
su vínculo con el titular, y verifique su correo con un código antes de pagar.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-04.1 | un ciudadano en `/tramite` | busca por tipo, nombre del titular y fecha aceptando los términos | si hay una única coincidencia con PDF continúa; si no, se le informa **sin cobrar ni mostrar datos del archivo** |
| CA-04.2 | un acta encontrada | completa DNI, verificador, nombre, correo, motivo y relación | se contrasta el DNI con APIsPerú y se crea la solicitud `PENDIENTE_PAGO` con los datos **cifrados** |
| CA-04.3 | la solicitud creada | se envía el código al correo | el código tiene 6 dígitos, vence en 10 min, admite 5 intentos, es de un solo uso y solo se guarda su hash |
| CA-04.4 | un código ya usado, vencido o tras 5 intentos | se vuelve a ingresar | se rechaza |
| CA-04.5 | la madre o el padre de un menor | solicita el acta del menor | puede pagar, pero la aprobación exigirá revisión humana del vínculo |
| CA-04.6 | alguien declara ser el titular | el acta es de un menor de edad | igualmente se exige revisión del vínculo |
| CA-04.7 | un abuso de la búsqueda | se superan 5 búsquedas por minuto desde la misma IP | se bloquea con 429 (contador propio de la ruta) |

## Reglas de negocio
- Términos versionados; ninguna casilla viene marcada por defecto.
- No encontrar una coincidencia digital **no** prueba que el acta física no exista.
- El contraste de DNI no acredita identidad ni parentesco por sí solo.
- El DNI se indexa con HMAC (`dni_hash`) para buscar sin descifrar (ADR-027).

## Diseño técnico
| Capa | Componentes |
|---|---|
| Presentación | `portal/buscar.blade.php`, `datos.blade.php`, `codigo.blade.php` |
| Control | `PortalPublicoController@buscar/registrar/verificar/reenviar` |
| Negocio | `BusquedaPublicaService`, `ContrastadorIdentidad` (implementado por `ApisPeruService`), `NombrePersona`, `CodigoTramiteService`, `CruceParentescoService`, `DeclaracionSolicitud` |
| Datos | `tramites_publicos` (campo `datos` con `encrypted:array`, `dni_hash`, `otp_hash`) |

## Tareas técnicas
- ✅ Búsqueda de coincidencia única con PDF disponible.
- ✅ Formulario progresivo con autocompletado para el titular.
- ✅ Contraste DNI con TLS verificado.
- ✅ Código de verificación con límites y hash.
- ✅ Límites de intentos por ruta.

## Verificación
| Criterio | Prueba |
|---|---|
| CA-04.1 | `PortalPublicoTest::test_busqueda_no_cobra_ni_expone_el_archivo_y_conserva_aceptacion` |
| CA-04.2 | `EndurecimientoTest::test_el_dni_se_guarda_como_indice_ciego_y_no_en_claro` |
| CA-04.3, CA-04.4 | `PortalPublicoTest::test_codigo_un_solo_uso_caducidad_e_intentos` |
| CA-04.5 | `PortalPublicoTest::test_padre_de_menor_puede_pagar_pero_no_aprobarse_sin_revision_del_vinculo` |
| CA-04.6 | `PortalPublicoTest::test_indicar_titular_no_evade_revision_si_el_acta_es_de_un_menor` |
| CA-04.7 | `PruebasCargaTest::test_los_limites_siguen_activos_sin_la_clave_de_carga`, `PruebasCargaTest::test_cada_ruta_tiene_su_propio_contador_de_intentos` |

## Fuera de alcance
Acceso directo a RENIEC (RC06). La validación real de APIsPerú depende de credenciales del proveedor.
