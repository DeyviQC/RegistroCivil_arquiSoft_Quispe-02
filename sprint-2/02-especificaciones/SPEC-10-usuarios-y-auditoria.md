# SPEC-10 — Acceso del personal, usuarios y auditoría

| Campo | Valor |
|---|---|
| Historias | HU08, HU09, HU10 |
| Requisitos | RF01, RF02, RF11, RF12 |
| Decisiones | ADR-006, ADR-009, ADR-010 |
| Atributos | AC04 Seguridad, AC06 Trazabilidad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que solo el personal activo entre al panel según su rol, que el administrador gestione las cuentas sin
poder bloquearse a sí mismo y que toda acción relevante quede registrada.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-10.1 | un usuario activo | inicia sesión con credenciales correctas | entra al panel; con credenciales incorrectas o cuenta inactiva se rechaza |
| CA-10.2 | «Recordarme» marcado | cierra el navegador y vuelve | la sesión se recupera por cookie; al salir se revoca; sin marcarlo no se crea cookie persistente |
| CA-10.3 | un visitante sin sesión | abre una ruta del panel | se redirige al login |
| CA-10.4 | un asistente | intenta gestionar usuarios o ver la auditoría | se niega (403) |
| CA-10.5 | el administrador | crea, edita o desactiva a otro usuario | se aplica; no puede desactivarse ni quitarse su propio rol |
| CA-10.6 | un usuario en su perfil | edita sus datos o su foto | solo cambia su propio perfil, sin escalar permisos; el administrador necesita su clave actual |
| CA-10.7 | un administrador que olvidó su clave | pide recuperarla | recibe un enlace de un solo uso válido 60 min; asistentes, cuentas inexistentes o desactivadas no reciben enlace |
| CA-10.8 | login, creación de acta, decisión, descarga | ocurre | se registra en `auditorias` con usuario, acción, módulo, IP y fecha; solo el administrador la consulta |

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `AuthController`, `UsuarioController`, `PerfilController`, `PasswordResetController`, `AuditoriaController` |
| Seguridad | middleware `role:`, Policies, hash bcrypt, regeneración de sesión |
| Negocio | `AuditoriaService` |
| Datos | `users`, `roles`, `auditorias`, `password_reset_tokens` |

## Verificación
| Criterio | Prueba |
|---|---|
| CA-10.1 | `AuthTest::test_login_correcto`, `AuthTest::test_login_incorrecto`, `AuthTest::test_usuario_inactivo_no_puede_iniciar_sesion` |
| CA-10.2 | `AuthTest::test_recordarme_recupera_la_sesion_mediante_cookie_y_logout_la_revoca`, `AuthTest::test_sin_recordarme_no_crea_cookie_persistente` |
| CA-10.3 | `AuthTest::test_usuario_no_autenticado_es_redirigido_al_login`, `EndurecimientoTest::test_solo_el_personal_puede_entrar_al_panel` |
| CA-10.4 | `UsuarioTest::test_asistente_no_puede_gestionar_usuarios`, `UsuarioTest::test_asistente_no_puede_acceder_a_auditoria` |
| CA-10.5 | `UsuarioTest::test_administrador_puede_crear_usuario`, `UsuarioTest::test_administrador_puede_desactivar_a_otro_usuario_desde_editar`, `UsuarioTest::test_administrador_no_puede_desactivarse_ni_quitarse_el_rol_desde_editar` |
| CA-10.6 | `PerfilInterfazTest::test_perfil_no_permite_escalar_permisos_ni_editar_otro_usuario`, `PerfilInterfazTest::test_foto_privada_solo_actualiza_el_perfil_propio_y_se_puede_quitar`, `PerfilInterfazTest::test_rechaza_archivos_invalidos_y_administrador_necesita_clave_actual` |
| CA-10.7 | `PasswordRecoveryTest::test_recuperacion_completa_y_token_de_un_solo_uso`, `PasswordRecoveryTest::test_asistente_no_recibe_correo_ni_puede_usar_un_token`, `PasswordRecoveryTest::test_cuentas_inexistentes_o_desactivadas_no_reciben_enlaces`, `PasswordRecoveryTest::test_rechaza_token_vencido_y_confirmacion_incorrecta` |
| CA-10.8 | `AuditoriaTest::test_el_login_queda_registrado_en_auditoria`, `AuditoriaTest::test_crear_acta_queda_registrado_en_auditoria`, `AuditoriaTest::test_solo_administrador_puede_ver_la_auditoria` |

## Fuera de alcance
Doble factor para el personal (posible mejora futura).
