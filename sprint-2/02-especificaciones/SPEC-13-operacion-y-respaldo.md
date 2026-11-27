# SPEC-13 — Operación: colas, mantenimiento, respaldo y endurecimiento

| Campo | Valor |
|---|---|
| Historias | HU11, HU12 |
| Requisitos | RF13, RF14, RF15 |
| Decisiones | ADR-019, ADR-020, ADR-026, ADR-028 |
| Atributos | AC02 Disponibilidad, AC04 Seguridad, AC05 Integridad |
| Estado | ✅ Implementado y probado |

## Objetivo
Que el sistema funcione solo en el día a día: avisos en segundo plano, vencimiento de solicitudes
abandonadas, respaldo periódico y una configuración que no se pueda desplegar insegura.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-13.1 | un aviso al ciudadano | se genera | va a la cola (`queue:work` cada minuto, 3 reintentos) y no frena la respuesta; no expone datos del acta y enlaza al seguimiento |
| CA-13.2 | solicitudes sin ninguna orden de pago tras 7 días | corre `tramites:mantenimiento` (cada hora) | pasan a `VENCIDA`; las que tienen una orden iniciada no se tocan |
| CA-13.3 | el respaldo programado | corre `respaldo:crear` (cada hora) | vuelca la base de datos y copia los documentos privados de forma **incremental**; la clave de la aplicación se guarda cifrada y se recupera con `respaldo:clave` |
| CA-13.4 | un destino de respaldo dentro de los documentos privados | se configura | se rechaza |
| CA-13.5 | cualquier respuesta HTTP | se envía | lleva cabeceras de seguridad (CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) y no revela PHP |
| CA-13.6 | una configuración insegura (por ejemplo, `APP_DEBUG` activo o sin HTTPS) | se ejecuta `sistema:verificar` | se detecta y se informa |
| CA-13.7 | pasarelas retiradas | reciben notificaciones | ya no se procesan |

## Diseño técnico
| Capa | Componentes |
|---|---|
| Programación | `routes/console.php` (`Schedule::command`), `php artisan schedule:run` cada minuto |
| Comandos | `respaldo:crear`, `respaldo:clave`, `tramites:mantenimiento`, `actas:copias-firmadas`, `sistema:verificar` |
| Infraestructura | cola en base de datos (`jobs`, `failed_jobs`), `CabecerasSeguridad` |

## Verificación
| Criterio | Prueba |
|---|---|
| CA-13.1 | `AvisosMantenimientoRespaldoTest::test_el_ciudadano_recibe_un_aviso_en_cola_cuando_el_personal_observa_su_solicitud`, `AvisosMantenimientoRespaldoTest::test_el_aviso_no_expone_datos_del_acta_y_enlaza_al_seguimiento` |
| CA-13.2 | `AvisosMantenimientoRespaldoTest::test_mantenimiento_vence_solo_solicitudes_sin_ninguna_orden_de_pago` |
| CA-13.3 | `AvisosMantenimientoRespaldoTest::test_respaldo_copia_los_documentos_privados_de_forma_incremental` |
| CA-13.4 | `AvisosMantenimientoRespaldoTest::test_respaldo_rechaza_un_destino_dentro_de_los_documentos_privados` |
| CA-13.5 | `EndurecimientoTest::test_las_respuestas_llevan_cabeceras_de_seguridad_y_no_revelan_php` |
| CA-13.6 | `EndurecimientoTest::test_verificacion_del_sistema_detecta_configuracion_insegura` |
| CA-13.7 | `EndurecimientoTest::test_las_pasarelas_retiradas_ya_no_reciben_notificaciones` |

## Fuera de alcance
Despliegue en servidor, contenedores y monitoreo en producción (Sprint 3).
