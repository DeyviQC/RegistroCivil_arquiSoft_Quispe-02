# AC03 — Escalabilidad

**Escenario:** aumentar progresivamente usuarios y documentos y comparar latencia, errores,
almacenamiento y recursos con AC01.

**Meta:** con **50 usuarios y 50 000 actas**, el p95 de cada operación no supera el **doble** del de AC01
y los errores siguen < 1 %.

**Veredicto: ⚠️ PARCIAL**
- ✅ **Volumen de datos:** con 5 veces más actas (50 000) el sistema mantiene p95 < 1 s y 0 % de errores
  con 20 usuarios, **después** de corregir tres consultas que no escalaban (§4).
- ❌ **Usuarios simultáneos:** con 50 usuarios el p95 sube a 2.1–2.9 s (3.4 a 4.8 veces AC01). Los errores
  se mantienen < 1 %. La causa es un **límite del servidor local**, no del código: Laravel Herd en Windows
  ejecuta 5 procesos PHP fijos (§5).

## 1. Datos de la ejecución

| Campo | Valor |
|---|---|
| Fecha | 26/11/2026 |
| Herramienta | k6 v2.4.0 · script [`tests/carga/ac01-rendimiento.js`](../../tests/carga/ac01-rendimiento.js) · ejecutor [`tests/carga/ejecutar-carga.ps1`](../../tests/carga/ejecutar-carga.ps1) (mide CPU y RAM cada 2 s) |
| Servidor | Laravel Herd (nginx + 5 procesos `php-cgi` PHP 8.4, OPcache activo) |
| Base de datos | MySQL 9.7, base separada `registro_civil_carga` |
| Equipo | Intel Core i5-12450HX (8 núcleos / 12 hilos), 23.7 GB RAM (16.6 GB en uso antes de empezar) |
| Duración | 2 min por corrida; perfiles 70 % ciudadanos / 30 % personal; 1 s de pausa entre pasos |
| Escalones | Usuarios: 10 → 20 → 50 · Datos: 10 000 → 50 000 actas (2 000 → 10 000 solicitudes) |

> Un usuario virtual de k6 repite el recorrido cada pocos segundos. Una persona real tarda mucho más
> entre acciones, así que **50 usuarios de k6 equivalen a varios cientos de personas reales**.

## 2. Resultados (p95 en milisegundos)

| Corrida | Actas | Usuarios | Búsqueda pública | Seguimiento | Archivo | Bandeja | Peticiones (por s) | Fallidas | CPU prom./máx. |
|---|---|---|---|---|---|---|---|---|---|
| **AC01 (referencia)** | 10 000 | 20 | 410 | 789 | 619 | 474 | 3 154 (25.7) | 0.00 % | — |
| 10k-10 | 10 000 | 10 | 364 | 786 | 597 | 289 | 1 669 (13.7) | 0.00 % | 35 % / 55 % |
| 10k-50 | 10 000 | 50 | 1 925 | 2 880 | 3 035 | 1 740 | 2 985 (24.0) | 0.00 % | 43 % / 61 % |
| 10k-50 + caché Laravel | 10 000 | 50 | 1 740 | 2 210 | 2 299 | 1 377 | 3 948 (31.6) | 0.05 % | 46 % / 95 % |
| 50k-20 *(antes de §4)* | 50 000 | 20 | 1 589 | 1 466 | 1 985 | 1 100 | 1 774 (14.4) | 0.39 % | 39 % / 54 % |
| **50k-20 con índices** | 50 000 | 20 | **499** | **964** | **953** | **641** | 2 782 (22.7) | **0.00 %** | 41 % / 58 % |
| **50k-50 con índices** (meta) | 50 000 | 50 | **2 071** | **2 667** | **2 915** | **2 298** | 2 909 (23.3) | **0.00 %** | 43 % / 54 % |
| 50k-50 + caché Laravel | 50 000 | 50 | 2 302 | 2 360 | 2 660 | 1 939 | 3 473 (27.5) | 0.14 % | 43 % / 61 % |

**Comparación con la meta (50 000 actas, 50 usuarios, con índices):**

| Operación | AC01 p95 | Límite (2×) | Medido | ¿Cumple? |
|---|---|---|---|---|
| Búsqueda pública | 410 ms | 820 ms | 2 071 ms | ❌ |
| Seguimiento | 789 ms | 1 578 ms | 2 667 ms | ❌ |
| Búsqueda del archivo | 619 ms | 1 238 ms | 2 915 ms | ❌ |
| Bandeja | 474 ms | 948 ms | 2 298 ms | ❌ |
| Peticiones fallidas | — | < 1 % | 0.00 % | ✅ |

Evidencias: `evidencias/ac03-*.txt` (salida de k6), `ac03-*.json` (resumen) y `ac03-*-recursos.json` (CPU y RAM).

## 3. Almacenamiento

| Elemento | Medición | Proyección |
|---|---|---|
| Base de carga (50 000 actas, 10 000 solicitudes) | 65.7 MB (28.7 datos + 37.0 índices) | ≈ 1.3 KB por acta en la base |
| Base de trabajo (79 actas) | 1.4 MB | — |
| PDF reales del archivo | 355 archivos · 137.2 MB · **396 KB de promedio** | 50 000 actas ≈ **19.4 GB** de PDF |

**Conclusión:** la base de datos crece poco. **El almacenamiento lo dominan los PDF** (unas 300 veces más
que la base) y crece igual en los respaldos. A gran escala conviene un almacenamiento de objetos
(S3, Cloudflare R2) y planificar el espacio de respaldo.

## 4. Cuellos de botella por volumen de datos — corregidos

Con 50 000 actas, la capacidad cayó un 44 % y aparecieron errores (fila «50k-20 antes»). Se perfilaron
las consultas de cada operación sobre la base de carga:

| Consulta | Antes | Después | Causa | Corrección |
|---|---|---|---|---|
| Búsqueda pública | 165 ms | **30 ms** | `DATE(fecha)` en una subconsulta por cada acta, sin índice en la fecha | Índice en la fecha de cada tabla de detalle y búsqueda por rango del día |
| Archivo por nombre/DNI | 792 ms | **141 ms** | Tres `EXISTS` correlacionados por cada acta, también para contar páginas | Buscar una vez en cada tabla de detalle y filtrar por esos IDs |
| Incidencias (en `/actas`) | 64 ms | **9 ms** | Filtro por `estado` sin índice en cada carga de la página | Índice en `actas.estado` |
| Archivo por número | 220 ms | 241 ms | `LIKE '%…%'` no puede usar índice | Sin cambio: buscar «contiene» es intencional. Se revisará si crece |

Cambios: migración `2026_11_26_000025_add_indices_rendimiento.php` (índices en fechas de nacimiento,
matrimonio y defunción, `actas.estado` y `pagos_tramite.estado`), `BusquedaPublicaService` y
`ActaService::buscar`. Las 127 pruebas automáticas siguen pasando.

## 5. Cuello de botella por usuarios simultáneos — del entorno

- Con 20 o con 50 usuarios el sistema atiende **lo mismo: unas 23–25 peticiones por segundo**, y la CPU
  se queda en ~43 %. Si el límite fuera el código o la máquina, la CPU llegaría cerca del 100 %.
- Herd en Windows ejecuta **5 procesos `php-cgi` fijos**. Con ~200 ms por petición eso da ≈ 25 peticiones
  por segundo; el resto **espera en cola**. Esa espera es la que sube el p95 a 2–3 s.
- Con las cachés de producción de Laravel (`php artisan optimize`) cada petición cuesta menos y la capacidad
  sube entre **19 % y 32 %**, pero el límite de 5 procesos sigue.
- **En un servidor real** (Linux + PHP-FPM) el número de procesos se configura (`pm.max_children`,
  p. ej. 16–24 para 8 núcleos). Con eso y `php artisan optimize`, la capacidad crece con los núcleos
  disponibles. Queda como recomendación de despliegue: no se puede medir en este equipo.

## 6. Errores observados

| Corrida | Errores | Causa |
|---|---|---|
| 10k-50 y 50k-50 con caché de Laravel | 0.05 % y 0.14 % (HTTP 500) | `rename(... .tmp → vista compilada): Acceso denegado` en `laravel.log`. En **Windows**, cuando varios procesos compilan la misma vista Blade a la vez, no se puede reemplazar un archivo que otro proceso tiene abierto. En Linux el reemplazo es atómico y no ocurre. |
| 50k-20 antes de los índices | 0.39 % | No quedó registrado el código HTTP (el script aún no lo guardaba). Desaparecieron al corregir las consultas. Desde entonces el script registra estado y duración de cada fallo. |

**Mitigación:** en el despliegue, compilar las vistas antes de abrir el servicio (`php artisan view:cache`,
o una visita de calentamiento a cada página) para que no se compilen bajo carga.

## 7. Acciones derivadas

| Acción | Estado |
|---|---|
| Índices y consultas reescritas (§4) | ✅ Hecho |
| Registrar código HTTP de cada fallo en k6 | ✅ Hecho |
| Desplegar con Linux + PHP-FPM con más procesos y `php artisan optimize` | Recomendación (fuera del alcance local) |
| Compilar las vistas antes de abrir el servicio | Recomendación de despliegue |
| Almacenamiento de objetos para los PDF si el archivo supera decenas de GB | Recomendación a futuro |

## 8. Cómo repetir

```powershell
# Base de carga: 10 000 actas (AC01) y luego +40 000
$env:DB_DATABASE='registro_civil_carga'; php artisan migrate --force
php artisan pruebas:generar-datos --actas=10000 --tramites=2000
php artisan pruebas:generar-datos --actas=40000 --tramites=8000 --exportar=tests/carga/datos-50k.json
# .env temporal como en AC01 (DB_DATABASE=registro_civil_carga, MAIL_MAILER=log, PRUEBAS_CARGA_TOKEN)
powershell -File tests\carga\ejecutar-carga.ps1 -Usuarios 50 -Duracion 2m -Etiqueta ac03-50k-50vus -Datos datos-50k.json -Token <token>
```
