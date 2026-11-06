# Escenarios de atributos de calidad del Registro Civil

Los escenarios concretan los [atributos de calidad](04-atributos-de-calidad.md). Las medidas propuestas requieren validación; no representan pruebas ejecutadas ni capacidades acreditadas.

## EQ-01. Rendimiento: consulta de actas

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC01 — Rendimiento. |
| Fuente | Administrador o asistente. |
| Estímulo | Busca actas mediante los filtros habilitados. |
| Condición | Operación normal con datos representativos del archivo. |
| Respuesta esperada | El sistema presenta resultados paginados y respeta los filtros y permisos. |
| Medida | Propuesta por validar: percentil 95 de hasta 2 segundos para consultas internas. Volumen y concurrencia aún por acordar; se excluye la descarga del PDF y llamadas externas de esta medida. |
| Verificación | Ejecutar búsquedas con carga documentada; registrar tiempos, errores, configuración y cantidad de registros. |
| Estado | Pendiente de acordar carga, entorno y ejecutar la medición. |

## EQ-02. Disponibilidad: fallo de un proveedor

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC02 — Disponibilidad. |
| Fuente | Proveedor de correo, contraste de datos o Mercado Pago en sandbox. |
| Estímulo | El proveedor no responde o devuelve un error. |
| Condición | El solicitante está realizando una operación que depende del proveedor. |
| Respuesta esperada | Se informa el fallo de forma controlada, sin confirmar pagos, aprobar trámites ni entregar documentos por ese error. |
| Medida | Ninguno de los fallos simulados de la prueba debe confirmar un pago o habilitar una entrega. Horario de servicio y disponibilidad mensual objetivo por acordar. |
| Verificación | Simular errores y tiempos de espera; comprobar respuesta y estados persistidos. |
| Estado | Pendiente de ejecutar este escenario con fallos simulados en el entorno sandbox de Mercado Pago. |

## EQ-03. Escalabilidad: crecimiento del archivo

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC03 — Escalabilidad. |
| Fuente | Aumento de registros y usuarios del archivo municipal. |
| Estímulo | Se incrementan documentos almacenados y consultas concurrentes. |
| Condición | Pruebas con conjuntos de datos y configuración documentados. |
| Respuesta esperada | Las búsquedas siguen respetando filtros y autorización sin perder datos ni degradarse por encima de los límites acordados. |
| Medida | Comparar percentil 95, errores, uso de recursos y almacenamiento con EQ-01; carga máxima y crecimiento esperado por definir. |
| Verificación | Aumentar progresivamente datos y concurrencia y comparar resultados. |
| Estado | Pendiente de definir carga y capacidad objetivo. |

## EQ-04. Seguridad: acceso al archivo privado

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC04 — Seguridad y privacidad. |
| Fuente | Solicitante público, usuario sin sesión o personal sin permisos suficientes. |
| Estímulo | Intenta consultar un PDF privado o firmado, gestionar usuarios o acceder a un seguimiento ajeno. La descarga propia debe requerir LISTA, pago confirmado y sesión de correo vigente. |
| Condición | Pruebas de rutas públicas y protegidas con usuarios de cada rol. |
| Respuesta esperada | Se rechaza el acceso y no se muestran documentos, códigos ni datos privados. |
| Medida | Rechazar el 100 % de los casos no autorizados definidos en la matriz de pruebas, sin exposición de información en las respuestas revisadas. |
| Verificación | Ejecutar pruebas de autorización y revisar respuesta, efectos persistidos y exposición de datos. |
| Estado | Controles existentes; pendiente de registrar resultados de la matriz completa. |

## EQ-05. Mantenibilidad: cambio de regla documental

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC07 — Mantenibilidad. |
| Fuente | Equipo de desarrollo. |
| Estímulo | Se modifica una regla de validación de PDF o de transcripción de filiación. |
| Condición | Cambio dentro de Laravel MVC con servicios, validaciones y políticas existentes. |
| Respuesta esperada | La modificación se concentra en los responsables de esa regla y conserva la funcionalidad del archivo. |
| Medida | Aprobar las pruebas afectadas y regresiones acordadas de registro, autorización y conservación del original. |
| Verificación | Revisar dependencias y cambios y ejecutar las pruebas correspondientes. |
| Estado | Pendiente de verificar en cada cambio; no se presume aislamiento completo del framework. |

## EQ-06. Integridad: duplicados y decisiones simultáneas

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC05 — Integridad documental y transaccional. |
| Fuente | Dos operadores o un intento de registrar un duplicado. |
| Estímulo | Se registra un número/año/tipo o PDF repetido, o se decide sobre una versión desactualizada. |
| Condición | Operaciones concurrentes sobre el mismo registro o trámite. |
| Respuesta esperada | Se rechaza el duplicado o la versión antigua sin sobrescribir la decisión vigente ni alterar el PDF original. |
| Medida | Ningún duplicado ni sobrescritura desactualizada en los casos definidos; hashes y numeración se conservan. |
| Verificación | Probar duplicados y concurrencia; comparar registros, archivos y versiones. Validar bloqueo en MySQL, además de pruebas con SQLite. |
| Estado | Controles existentes; pruebas de concurrencia en entorno objetivo pendientes de documentar. |

## EQ-07. Auditoría: revisión de filiación

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC06 — Trazabilidad. |
| Fuente | Administrador o asistente autorizado. |
| Estímulo | Corrige la transcripción de un dato de padre o madre. |
| Condición | Existe un nacimiento con PDF original y versión vigente. |
| Respuesta esperada | Se guardan datos anteriores y nuevos, operador, fecha y hash de referencia; no cambia el acta central. |
| Medida | El 100 % de las modificaciones efectivas incluidas en la prueba tiene historial y auditoría; PDF y numeración permanecen iguales. |
| Verificación | Comparar datos previos/posteriores, historial, auditoría y hash. |
| Estado | Implementado localmente; la bitácora no se presenta como criptográficamente inmutable. |

## EQ-08. Recuperabilidad: restauración del archivo

| Elemento | Descripción |
|---|---|
| Atributo de calidad | AC08 — Recuperabilidad. |
| Fuente | Fallo del servidor o pérdida de almacenamiento. |
| Estímulo | Se necesita recuperar el sistema desde un respaldo. |
| Condición | Entorno separado y copias de MySQL, PDF y claves necesarias disponibles. |
| Respuesta esperada | Se recuperan relaciones, documentos y lectura de los datos cifrados sin depender de archivos perdidos. |
| Medida | Verificar integridad de registros y hashes de la copia restaurada; tiempo máximo de recuperación y pérdida admisible por acordar. |
| Verificación | Ejecutar restauración aislada y registrar tiempos, cobertura y comprobaciones. |
| Estado | Procedimiento operativo y prueba de restauración pendientes. |

## Resumen de escenarios

| Código | Atributo de calidad | Qué se busca comprobar |
|---|---|---|
| EQ-01 | Rendimiento | Consultas dentro del tiempo acordado. |
| EQ-02 | Disponibilidad | Fallos externos controlados. |
| EQ-03 | Escalabilidad | Crecimiento medido del archivo y uso concurrente. |
| EQ-04 | Seguridad | Acceso autorizado y privacidad. |
| EQ-05 | Mantenibilidad | Cambios sin regresiones del archivo. |
| EQ-06 | Integridad | Duplicados y conflictos rechazados. |
| EQ-07 | Auditoría | Historial verificable de filiación. |
| EQ-08 | Recuperabilidad | Restauración conjunta de datos, documentos y claves. |
