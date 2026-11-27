# AC01 — Rendimiento

**Escenario:** medir búsquedas, seguimiento y consultas con datos representativos; registrar
percentil 95, errores y concurrencia.

**Veredicto: ✅ CUMPLE** — todas las operaciones con p95 < 1 s y 0 % de errores con 20 usuarios simultáneos.

> Nota: después de esta medición, AC03 agregó índices y reescribió dos consultas (búsqueda pública 5.5× y
> archivo por nombre 5.6× más rápidas con 50 000 actas). Los valores de AC01 corresponden a la versión
> anterior y son, por tanto, conservadores.

## 1. Datos de la ejecución

| Campo | Valor |
|---|---|
| Fecha | 26/11/2026 |
| Versión del sistema | commit `547d27a` + cambios de preparación de pruebas (este documento) |
| Herramienta | k6 v2.4.0 (Grafana) |
| Script | [`tests/carga/ac01-rendimiento.js`](../../tests/carga/ac01-rendimiento.js) |
| Servidor | Laravel Herd: nginx 1.25 + PHP-FPM 8.4, `http://registro-civil.test` |
| Base de datos | MySQL 9.7 (DBngin), base separada `registro_civil_carga` |
| Equipo | Intel Core i5-12450HX (8 núcleos / 12 hilos), 23.7 GB RAM, Windows 11 Pro |
| Datos | 10 000 actas sintéticas (3 tipos, 1950–2025) y 2 000 solicitudes pagadas — `php artisan pruebas:generar-datos --actas=10000 --tramites=2000` |
| Carga | 20 usuarios virtuales durante 2 min: 14 ciudadanos y 6 del personal, 1 s de pausa entre pasos |
| Límites de intentos | Omitidos solo para el generador de carga con la cabecera `X-Prueba-Carga` (ver §4) |
| Correo | `MAIL_MAILER=log` durante la prueba (no se enviaron correos reales) |

## 2. Operaciones medidas

| Operación (etiqueta k6) | Qué hace | Verificación funcional |
|---|---|---|
| `busqueda_publica` | `POST /tramite/buscar` con tipo, nombre y fecha de un acta existente | Redirige a `/tramite/datos` (acta encontrada) |
| `seguimiento` | `GET /tramite/seguimiento` y `POST` con DNI y correo de una solicitud existente | Redirige a `/tramite/codigo` |
| `busqueda_archivo` | `GET /actas?nombre=…` y `GET /actas?numero_acta=…` (personal con sesión) | Responde 200 y el número aparece en el resultado |
| `bandeja` | `GET /atencion-ciudadana` y filtro por DNI | Responde 200 con la bandeja |

## 3. Resultados

| Operación | Promedio | Mediana | p90 | **p95** | Máximo | Meta p95 | ¿Cumple? |
|---|---|---|---|---|---|---|---|
| Búsqueda pública | 207.85 ms | 175.91 ms | 355.71 ms | **410.34 ms** | 1.07 s | < 1 s | ✅ |
| Seguimiento | 316.15 ms | 223.13 ms | 664.32 ms | **789.31 ms** | 1.45 s | < 1 s | ✅ |
| Búsqueda del archivo | 349.13 ms | 320.41 ms | 537.26 ms | **619.31 ms** | 1.04 s | < 1 s | ✅ |
| Bandeja del personal | 212.39 ms | 172.84 ms | 332.07 ms | **474.03 ms** | 996.51 ms | < 1 s | ✅ |

| Indicador | Resultado | Meta | ¿Cumple? |
|---|---|---|---|
| Peticiones totales | 3 154 (25.7 por segundo) | — | — |
| Peticiones fallidas | **0 (0.00 %)** | < 1 % | ✅ |
| Verificaciones funcionales | 3 142 de 3 142 (100 %) | 100 % | ✅ |
| Iteraciones completas | 784 | — | — |
| Usuarios simultáneos | 20 | 20 | ✅ |

Evidencia cruda: [`evidencias/ac01-k6-20vus.txt`](evidencias/ac01-k6-20vus.txt) (salida de k6) y
[`evidencias/ac01-k6-20vus.json`](evidencias/ac01-k6-20vus.json) (resumen exportado).

## 4. Observaciones

- **El seguimiento es la operación más lenta (p95 789 ms).** Es esperado: genera un código de 6 dígitos y lo
  guarda con `bcrypt`, un algoritmo lento a propósito para resistir ataques de fuerza bruta. Es el costo
  de la seguridad, no un defecto. Queda a 211 ms de la meta: es la primera a vigilar en AC03.
- Los máximos aislados (1.0–1.45 s) son picos puntuales; el p95 —criterio acordado— queda bajo 1 s.
- **Límites de intentos:** en uso normal la búsqueda admite 5 intentos por minuto y el seguimiento 3 cada
  10 minutos por IP. Como k6 envía todo desde una sola IP, se omitieron con la cabecera secreta
  `X-Prueba-Carga` (configurada con `PRUEBAS_CARGA_TOKEN`, ignorada en producción y probada en
  `tests/Feature/PruebasCargaTest.php`). Que los límites **sí bloquean** se verifica en AC04.
- No se midieron ApisPeru, Mercado Pago ni el correo: son servicios externos y se mediría su velocidad,
  no la del sistema.
- La base de trabajo (`registro_civil_desarrollo`, 79 actas) no se modificó: el generador se niega a
  ejecutarse en una base cuyo nombre no contenga «carga» o «prueba».

## 5. Cómo repetir la prueba

```powershell
# 1. Base de carga (una sola vez)
$env:DB_DATABASE='registro_civil_carga'; php artisan migrate --force
php artisan pruebas:generar-datos --actas=10000 --tramites=2000

# 2. En .env, temporalmente: DB_DATABASE=registro_civil_carga, MAIL_MAILER=log y PRUEBAS_CARGA_TOKEN=<32 caracteres>
php artisan config:clear

# 3. Ejecutar
k6 run -e BASE=http://registro-civil.test -e TOKEN=<el mismo token> -e VUS=20 -e DURACION=2m --summary-export docs/calidad/evidencias/ac01-k6-20vus.json tests/carga/ac01-rendimiento.js

# 4. Restaurar .env (base de trabajo, MAIL_MAILER=smtp, PRUEBAS_CARGA_TOKEN vacío) y php artisan config:clear
```
