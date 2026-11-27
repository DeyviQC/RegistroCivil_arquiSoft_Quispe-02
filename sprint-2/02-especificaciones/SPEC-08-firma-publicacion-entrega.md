# SPEC-08 — Firma, publicación, reutilización y descarga

| Campo | Valor |
|---|---|
| Historias | HU07, HU14 |
| Requisitos | RF07, RF08 |
| Decisiones | ADR-005, ADR-015, ADR-017, ADR-024 |
| Atributos | AC05 Integridad, AC06 Trazabilidad |
| Estado | ✅ Implementado y probado (firma manual externa) |

## Objetivo
Entregar al ciudadano una copia **firmada digitalmente** del acta, verificada contra el original, revisada
por el personal y descargable solo por quien la pidió; y no volver a firmar el mismo documento cada vez.

## Criterios de aceptación

| ID | Dado | Cuando | Entonces |
|---|---|---|---|
| CA-08.1 | una solicitud que no está `APROBADA` o no está pagada, o una versión obsoleta | se intenta preparar o cargar la firma | se bloquea |
| CA-08.2 | una solicitud aprobada | el personal descarga el original y lo firma con Firma Perú | puede descargarlo varias veces sin duplicar archivos ni cambiar la solicitud |
| CA-08.3 | un PDF firmado | se carga | el servidor verifica la firma CMS y que el PDF firmado corresponde al original; un PDF sin firma, ajeno o modificado se rechaza sin guardarse |
| CA-08.4 | un firmado verificado | el personal publica | debe confirmar que revisó contenido y certificado y dejar constancia; la solicitud pasa a `LISTA` |
| CA-08.5 | una solicitud `LISTA` | el ciudadano descarga con su acceso vigente | recibe el PDF solo si el hash del archivo sigue intacto; se audita la descarga |
| CA-08.6 | un acta ya firmada en una entrega anterior | llega otra solicitud de la misma acta | se entrega con un clic **sin volver a firmar**, verificando de nuevo firma e integridad |
| CA-08.7 | el original cambió o la copia tiene más de 90 días | se intenta reutilizar | hay que firmar de nuevo |
| CA-08.8 | una copia con el sello de una solicitud concreta | se publica | no se guarda como copia reutilizable del acta |

## Reglas de negocio
- El original nunca se reemplaza por el firmado; ambos se guardan por separado.
- La verificación CMS no acredita confianza, revocación ni facultades del firmante: hay revisión humana.
- Vigencia de reutilización configurable (`FIRMA_VIGENCIA_COPIA_DIAS`, 0 = sin límite).

## Diseño técnico
| Capa | Componentes |
|---|---|
| Control | `EntregaDocumentoController@preparar/archivo/cargar/publicar/reutilizar/descartar`, `PortalPublicoController@documento`, `PdfController@firmado` |
| Negocio | `FirmaDocumentoService` (verificación CMS), `Acta::copiaFirmadaVigente()`, `EnvioDocumentoCorreo` |
| Datos | `tramites_publicos.datos.entrega`, columnas `firmado_*` de `actas`, archivos en `private/entregas` y `private/actas-firmadas` |

## Tareas técnicas
- ✅ Flujo de 3 pasos: descargar, firmar fuera, cargar con verificación automática.
- ✅ Publicación con constancia y envío automático por correo (SPEC-09).
- ✅ Copia firmada reutilizable por acta y comando `actas:copias-firmadas`.
- ⬜ Firma integrada con el invocador de Firma Perú (requiere credenciales institucionales; trabajo futuro).

## Verificación
| Criterio | Prueba |
|---|---|
| CA-08.1 | `EntregaDocumentoTest::test_bloquea_preparacion_sin_aprobacion_o_sin_pago_y_version_obsoleta` |
| CA-08.2 | `BandejaManualTest::test_descarga_manual_prepara_copia_identica_sin_logo_y_actualiza_version`, `BandejaManualTest::test_descargar_original_varias_veces_no_duplica_archivos_ni_cambia_la_solicitud` |
| CA-08.3 | `EntregaDocumentoTest::test_rechaza_archivo_sin_firma_y_no_habilita_descarga_de_otra_solicitud`, `EntregaDocumentoTest::test_integridad_rechaza_documento_distinto_o_modificado`, `BandejaManualTest::test_subida_directa_sin_descarga_usa_original_y_solo_guarda_el_firmado`, `BandejaManualTest::test_subida_directa_rechaza_un_documento_ajeno_sin_guardarlo` |
| CA-08.4, CA-08.5 | `EntregaDocumentoTest::test_flujo_carga_revision_y_descarga_con_correo_verificado` |
| CA-08.6 | `CopiaFirmadaTest::test_la_primera_firma_queda_en_el_acta_y_la_siguiente_solicitud_se_entrega_sin_firmar` |
| CA-08.7 | `CopiaFirmadaTest::test_si_el_original_cambia_o_la_copia_vence_hay_que_firmar_de_nuevo` |
| CA-08.8 | `CopiaFirmadaTest::test_una_copia_con_sello_de_la_solicitud_no_se_guarda_en_el_acta` |

## Fuera de alcance
QR público de autenticidad (HU04, ADR-016).
