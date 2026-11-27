# Plan de pruebas de atributos de calidad

Sistema de Registro Civil — Municipalidad Distrital Andrés Avelino Cáceres Dorregaray.
Proyecto universitario: entorno local (laptop) y servicios en la nube gratuitos.

> Los criterios de este plan son **objetivos de verificación**. Solo cuentan como resultado
> los valores registrados en los archivos `ACxx-*.md` al ejecutar cada prueba.

## 1. Entorno de prueba

| Elemento | Valor |
|---|---|
| Equipo | Laptop del desarrollador (Windows 11 Pro) — se registran CPU y RAM en cada resultado |
| Servidor web | **Laravel Herd** (nginx + PHP-FPM 8.4) en `http://registro-civil.test`. No se usa `php -S` para medir: atiende una petición a la vez. |
| Base de datos | MySQL 9.7 (DBngin). Pruebas de carga en una base **separada** `registro_civil_carga` con datos sintéticos; nunca en la base de trabajo. |
| Datos sintéticos | `php artisan pruebas:generar-datos` (se niega a correr en producción o en una base cuyo nombre no contenga `carga` o `prueba`). |
| Límites de intentos | Activos. Solo para AC01/AC03 se omiten con la cabecera `X-Prueba-Carga` y `PRUEBAS_CARGA_TOKEN` (ignorado en producción). AC04 los prueba activos. |
| Proveedores externos | ApisPeru, Mercado Pago y correo **no** se someten a carga: se mide solo el sistema propio. |

## 2. Herramientas

| Herramienta | Atributos | Motivo |
|---|---|---|
| **k6** (Grafana) | AC01, AC03, concurrencia de AC05 | Usuarios simultáneos, percentil 95 y tasa de errores en un solo informe. |
| **Postman / Newman** | AC02, AC04 (matriz de autorización) | Casos de una petición con su respuesta esperada; Newman genera reporte HTML. |
| **OWASP ZAP** | AC04 | Escaneo automático de vulnerabilidades web con informe. |
| **PHPUnit** | AC05, AC06, AC07 | Pruebas automáticas (128), ejecutadas en SQLite y MySQL. |
| **DBeaver** | AC06, AC08 | Consulta directa de la tabla `auditoria` y verificación de la restauración. |
| **PHPStan (Larastan)** | AC07 | Análisis estático del código. |
| Comandos del sistema | AC08 | `respaldo:crear`, `respaldo:clave`, verificación de hashes. |

## 3. Escenarios, metas y procedimiento

| ID | Atributo | Meta (criterio de aprobación) | Procedimiento resumido | Resultado |
|---|---|---|---|---|
| AC01 | Rendimiento | p95 < 1 s en búsqueda pública, seguimiento, búsqueda del archivo y bandeja, con **20 usuarios** durante 2 min; errores < 1 % | Script k6 con perfiles ciudadano y personal sobre 10 000 actas | ✅ Cumple — [AC01](AC01-rendimiento.md) |
| AC02 | Disponibilidad | Ante cada fallo simulado, el sistema informa el error y **no** aprueba, confirma pagos ni entrega documentos (100 % de casos) | Simular caída de ApisPeru, Mercado Pago, correo y MySQL; repetir las peticiones con Postman | AC02 |
| AC03 | Escalabilidad | Con 50 usuarios y 50 000 actas, el p95 no supera el doble del de AC01 y los errores siguen < 1 % | Mismo script de AC01, subiendo usuarios (10→20→50) y datos (10 000→50 000) | ⚠️ Parcial — [AC03](AC03-escalabilidad.md) |
| AC04 | Seguridad y privacidad | 100 % de los casos no autorizados rechazados; ZAP sin alertas de riesgo **alto**; sin PDF, códigos ni datos personales en respuestas públicas | Matriz Postman + ZAP + `composer audit` | AC04 |
| AC05 | Integridad | Rechazo de duplicados, versiones viejas, pagos inválidos y PDF alterados (100 %); con 2 decisiones simultáneas solo 1 se aplica | PHPUnit en SQLite **y** MySQL + prueba k6 de concurrencia | ✅ Cumple — [AC05](AC05-integridad.md) |
| AC06 | Trazabilidad | Cada operación probada deja responsable, acción, fecha y registro en `auditoria` (100 %) | Lista de operaciones + consultas SQL en DBeaver | AC06 |
| AC07 | Mantenibilidad | PHPStan nivel 5 sin errores nuevos; todas las pruebas pasan en CI | PHPStan + PHPUnit + GitHub Actions | ✅ Cumple — [AC07](AC07-mantenibilidad.md) |
| AC08 | Recuperabilidad | Restauración completa en < 30 min (RTO), pérdida ≤ 24 h (RPO) y 100 % de hashes de PDF correctos | Restaurar respaldo de OneDrive en otra base y carpeta; verificar relaciones y hashes | AC08 |

## 4. Limitaciones declaradas (no son defectos)

- La firma se realiza **fuera** del sistema con Firma Perú y luego se carga el PDF; no hay integración web
  para firmar. La confianza, revocación y facultades del firmante requieren revisión institucional.
- Los pagos usan el **modo de prueba** de Mercado Pago; no hay cobros reales ni devoluciones.
- WhatsApp funciona con enlaces `wa.me`; no se usa la API de WhatsApp.
- La auditoría es una tabla de base de datos: **no** es una bitácora criptográficamente inmutable.
- El entorno es una laptop: las cifras de rendimiento no representan un servidor de producción.
- Las pruebas automáticas usan SQLite en memoria; por eso AC05 las repite contra MySQL.

## 5. Registro de resultados

Cada archivo `ACxx-*.md` registra: fecha, versión (commit), entorno, pasos ejecutados, datos crudos,
comparación con la meta y veredicto (**Cumple / No cumple / Parcial**), más las acciones derivadas.
