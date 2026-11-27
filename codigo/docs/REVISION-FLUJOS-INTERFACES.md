# Revisión de flujos e interfaces · 25/10/2026

Alcance: proyecto de desarrollo. Revisión de rutas, formularios, validaciones y pruebas de módulos. No se alteraron actas ni datos del proyecto municipal entregado. No equivale a una certificación de producción ni a pruebas visuales de cada combinación de datos.

| Módulo | Secuencia revisada | Resultado |
| --- | --- | --- |
| Inicio interno | Resumen → solicitud o archivo | Se conservan accesos y colores por tipo de acta. Superficies y campos comparten tratamiento plateado. |
| Registro de actas | Tipo → datos → PDF → guardar | Se conserva la selección de tipo y sus campos condicionales, sin pedir datos de otros tipos. Numeración automática de solo lectura. DNI obligatorio salvo excepción explícita en nacimiento/defunción; observaciones opcionales. |
| Búsqueda y consulta | Filtros → resultados → documento | Filtros opcionales conservados; tablas, etiquetas, foco y campos con mayor contraste. |
| Filiación | Estado del dato → valor cuando está registrado → guardar | Se conservan estados no consignado/desconocido/ilegible; no se obliga a inventar información. |
| Portal ciudadano | Buscar → datos → verificar correo → pagar → revisión → documento | Se mantiene la verificación; datos de contacto y vínculo diferenciados. WhatsApp solo obligatorio si se solicita ese medio. |
| Pago | Un flujo por solicitud → confirmación de proveedor | No se ofrece Yape Orders cuando existe preferencia de Checkout Pro. Se conserva historial y la simulación se identifica como prueba. |
| Atención municipal | Iniciar revisión → decidir → firmar → publicar | Inicio/retoma con un botón, sin selector de una sola opción ni respuesta escrita redundante. Revisión de vínculo obligatoria en pantalla solo al aprobar, igual que en servidor. |
| Firma y entrega | Cargar firmado → revisar → publicar → enviar | Preferencias visibles antes de publicación. Confirmación al publicar o retirar borrador. Correo adjunta PDF; WhatsApp requiere adjuntarlo y enviar manualmente. |
| Solicitudes con cuenta | Presentar → observar/revisar → subsanar → decidir | Se conservan controles de propietario, versión y estados. Es un flujo legado distinto del portal con código; no se mezclan historiales ni se eliminan registros. |
| Usuarios | Crear credenciales → asignar rol → activar/desactivar | Se conserva confirmación de activación/desactivación. Al fallar validación, edición conserva rol y estado elegidos. Reglas de contraseña visibles; confirmación obligatoria al introducir nueva contraseña. |
| Perfil | Datos personales → foto opcional → guardar | Asistentes sin gestión de credenciales. Administrador conserva recuperación y cambio opcional; contraseña actual/confirmación obligatorias solo al cambiarla. |
| Reportes | Seleccionar reporte → filtros compatibles → consultar → exportar | Se conservan filtros condicionales y periodo requerido según reporte. No se hacen obligatorios filtros que no necesita el reporte. |
| Incidencias | Filtrar situación → consultar referencia/PDF | Se conservan categorías, contadores y contexto del archivo. |
| Historial | Filtrar → consultar actividades | Acceso administrativo conservado; filtros opcionales y tabla homogénea. |
| Acceso y recuperación | Iniciar sesión / recuperar acceso | Campos claramente delimitados; no se eliminan controles de acceso ni se cambian credenciales. |

## Cambios compartidos

- Bordes definidos, fondos plateados para información de solo lectura, sombras visibles y tipografía uniforme.
- Indicador «Obligatorio» derivado del atributo real del campo y actualizado cuando cambia su condición. No convierte campos opcionales en obligatorios.
- Asociación de etiquetas con controles; foco e identificación visual de campos inválidos.
- Botón «Guardando…» durante formularios internos y protección frente a doble clic; el portal conserva su indicador de procesamiento.
- Mensajes de atención breves y acordes a la siguiente acción. Se conservan avisos necesarios sobre pago, firma, revisión y entrega.

## Límites prácticos

Pagos reales y habilitación productiva siguen fuera de esta revisión. El correo real depende del transporte SMTP; no se enviaron documentos a destinatarios durante la revisión. WhatsApp abre una conversación y no confirma automáticamente la entrega. En el portal con código, las observaciones se consultan en seguimiento y se coordinan con atención; no se añadió un editor de subsanación a ese flujo.
