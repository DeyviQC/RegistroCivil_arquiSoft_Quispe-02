# Sistema Web de Registro Civil Municipal

Proyecto **completo** (Laravel 12 + MySQL) para digitalizar y centralizar el
registro de actas de **Nacimiento**, **Matrimonio** y **Defunción** de una
municipalidad, reemplazando el manejo actual por Excel y PDFs dispersos.

## 1. Descripción

Sistema web MVC en Laravel/Blade que permite registrar, buscar, consultar,
visualizar e imprimir actas civiles. Cada acta queda **bloqueada e
inmutable** una vez registrada: no puede editarse ni eliminarse. Los
metadatos se guardan en MySQL y el PDF escaneado se guarda en el servidor
(nunca como BLOB en la base de datos).

## 2. Objetivo

- Registrar actas de nacimiento, matrimonio y defunción.
- Buscar y consultar actas con filtros (número, año, tipo, DNI, nombre, fechas).
- Subir, validar y visualizar PDFs escaneados (PDF.js).
- Evitar duplicados por `numero_acta+anio+tipo` y por hash SHA-256 del PDF.
- Controlar el acceso mediante roles (Administrador / Asistente).
- Registrar toda acción relevante en auditoría.
- Permitir importación masiva de nacimientos vía CSV (sin sobrescribir).

## 3. Tecnologías

| Capa       | Tecnología                                         |
|------------|-----------------------------------------------------|
| Backend    | PHP 8.4, Laravel 12, Eloquent ORM                   |
| Base datos | MySQL 8+/9+                                          |
| Frontend   | Blade, Bootstrap 5 (CDN), JavaScript vanilla        |
| PDF        | PDF.js (CDN) para visor; PDFs guardados en `storage/app/private` |
| Servidor   | Apache (producción). Desarrollo: Laravel Herd o XAMPP/Laragon |
| Tests      | PHPUnit (SQLite en memoria, no requiere MySQL)      |

No se usa Node/Vite: Bootstrap, Bootstrap Icons y PDF.js se cargan por CDN,
así que **no hace falta `npm install`** para correr el proyecto.

## 4. Requisitos

- PHP 8.4+ con extensiones `pdo_mysql`, `fileinfo`, `mbstring`.
- Composer.
- MySQL 8+ (o 9+).
- Un servidor web: Apache (XAMPP/Laragon en desarrollo Windows clásico) o
  Laravel Herd (PHP + proxy local, también en Windows).

## 5. Instalación local — Opción A: Laravel Herd + DBngin

1. Instalar [Laravel Herd para Windows](https://herd.laravel.com/windows)
   (incluye PHP 8.4 y Composer).
2. Instalar [DBngin](https://dbngin.com/), crear una instancia **MySQL**
   en el puerto `3306`, usuario `root` sin contraseña, e iniciarla.
3. Copiar este proyecto a una carpeta gestionada por Herd, por ejemplo:
   ```
   C:\Users\<usuario>\Herd\registro-civil
   ```
4. `composer install`
5. `copy .env.example .env`
6. `php artisan key:generate`
7. Crear la base de datos:
   ```sql
   CREATE DATABASE registro_civil CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
8. Confirmar en `.env`: `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=registro_civil`, `DB_USERNAME=root`, `DB_PASSWORD=`
9. `php artisan migrate --seed`
10. `php artisan storage:link` (opcional; los PDFs son privados, no se sirven directo)
11. Parquear la carpeta con Herd → disponible en `http://registro-civil.test`
    (o `php artisan serve` → `http://127.0.0.1:8000`)

## 6. Instalación local — Opción B: XAMPP / Laragon (Windows clásico)

1. Instalar XAMPP y activar **Apache** y **MySQL**.
2. Instalar Composer para Windows.
3. Copiar el proyecto a `C:\xampp\htdocs\registro-civil`.
4. Repetir los pasos 4-9 de la Opción A (mismo `.env`, misma base de datos).
5. `php artisan serve` → abrir `http://127.0.0.1:8000`, o configurar un
   Virtual Host de Apache apuntando a `public/`.

En ambos casos: **no se requiere Linux, WSL, Docker ni Nginx en producción**
(ver sección 17).

## 7. Configuración de `.env` (resumen)

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=registro_civil
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=local
ACTAS_PDF_MAX_KB=10240
```

## 8. Migraciones

`php artisan migrate` crea, en orden, las 9 tablas: `users`, `roles`,
`role_user`, `actas`, `acta_nacimientos`, `acta_matrimonios`,
`acta_defunciones`, `auditoria`, `configuraciones`. Incluyen:

- `UNIQUE(numero_acta, anio, tipo)` y `UNIQUE(pdf_hash)` en `actas`.
- Índices en `numero_acta`, `anio`, `tipo`, `fecha_acta`, `usuario_id`.
- Claves foráneas con `constrained()` hacia `users` y `actas`.

## 9. Seeders

`php artisan migrate --seed` (o `php artisan db:seed`) ejecuta, en orden:

1. **RoleSeeder** — crea los roles `administrador` y `asistente`.
2. **UserSeeder** — crea los usuarios de prueba (tabla abajo).
3. **ActaSeeder** — crea 30 actas ficticias (10 de cada tipo) con un PDF de
   demostración real (válido, con cabecera `%PDF-`) almacenado en
   `storage/app/private/actas/`, para poder probar el visor sin usar datos
   reales.

## 10. Usuarios de prueba

| Usuario     | Contraseña      | Rol            |
|-------------|-----------------|----------------|
| `admin`     | `Admin12345!`   | Administrador  |
| `asistente` | `Asistente123!` | Asistente      |

> Solo para desarrollo/demo. Cambiar antes de producción.

## 11. Ejecución

```
php artisan serve
```
Abrir `http://127.0.0.1:8000` (o el dominio `.test` de Herd). El login
redirige a `/dashboard`.

## 12. Almacenamiento de PDFs

Los PDFs se guardan en `storage/app/private/actas/{tipo}/{anio}/` con un
nombre físico generado (no depende del nombre original), por ejemplo:
`ACTA_NACIMIENTO_2026_00125_a8f72c31.pdf`. **No son accesibles directamente
por URL**: solo se sirven a través de `GET /actas/{id}/pdf`
(`PdfController@ver`), que valida sesión, autorización (Policy), existencia
del archivo, y registra la consulta en auditoría antes de entregar el
binario con cabeceras `Content-Type: application/pdf` y
`Cache-Control: private, no-store`.

## 13. Roles y permisos

| Acción                          | Administrador | Asistente |
|----------------------------------|:---:|:---:|
| Iniciar/cerrar sesión            | ✔ | ✔ |
| Registrar actas                  | ✔ | ✔ |
| Buscar / consultar / ver PDF / imprimir | ✔ | ✔ |
| Gestionar usuarios                | ✔ | ✘ |
| Ver auditoría                     | ✔ | ✘ |
| Importación masiva                | ✔ | ✘ |
| Editar o eliminar un acta          | ✘ | ✘ |

La última fila es intencional: **nadie** puede editar/eliminar un acta
registrada (`ActaPolicy::update/delete` devuelven `false` siempre).

## 14. Seguridad implementada

- CSRF en todos los formularios (`@csrf`) y `@method` para PUT/PATCH.
- Hash de contraseñas con `Hash::make()` (nunca texto plano).
- Rate limiting de login (5 intentos / 60s por usuario+IP).
- Middleware `auth`, `role:administrador` y Policies en cada ruta sensible.
- Validación de PDF: extensión, MIME, tamaño, cabecera binaria `%PDF-`,
  hash SHA-256 contra duplicados.
- Nombres físicos de archivo generados por el servidor (no confían en el
  nombre que sube el usuario).
- PDFs fuera del `public/`, servidos únicamente vía controlador autenticado.
- Mass assignment protegido con `$fillable` explícito en cada modelo.
- Todo el acceso a datos vía Eloquent/Query Builder (sin SQL crudo).

## 15. Estructura del proyecto

```
registro-civil/
├── app/
│   ├── Http/
│   │   ├── Controllers/       (Acta, Auth, Dashboard, Pdf, Usuario, Auditoria, Importacion, Api/ActaApi)
│   │   ├── Middleware/        (EnsureUserHasRole, EnsureUserIsActive)
│   │   ├── Requests/          (StoreActaRequest, StoreNacimientoRequest, ...)
│   │   └── Resources/         (ActaResource)
│   ├── Models/                (User, Role, Acta, ActaNacimiento/Matrimonio/Defuncion, Auditoria, Configuracion)
│   ├── Policies/               (ActaPolicy, UserPolicy, AuditoriaPolicy)
│   ├── Providers/              (AppServiceProvider)
│   └── Services/               (ActaService, PdfValidationService, PdfStorageService, AuditoriaService, DashboardService, ImportacionService)
├── bootstrap/app.php            (middleware, rutas, alias de roles)
├── config/actas.php
├── database/
│   ├── factories/
│   ├── migrations/              (9 migraciones, en orden de dependencias)
│   └── seeders/                 (RoleSeeder, UserSeeder, ActaSeeder, DatabaseSeeder)
├── resources/
│   ├── views/
│   │   ├── layouts/app.blade.php
│   │   ├── auth/login.blade.php
│   │   ├── dashboard/index.blade.php
│   │   ├── actas/ (create, index, show, print)
│   │   ├── usuarios/ (index, create, edit)
│   │   ├── auditoria/index.blade.php
│   │   ├── importaciones/index.blade.php
│   │   └── pdf/viewer.blade.php
│   ├── css/app.css
│   └── js/app.js
├── routes/ (web.php, api.php, console.php)
├── storage/app/private/actas/{nacimiento,matrimonio,defuncion}/
├── tests/Feature/ (AuthTest, ActaTest, UsuarioTest, AuditoriaTest)
├── public/.htaccess              (Apache, mod_rewrite)
├── .env.example
├── composer.json
└── README.md
```

## 16. Tests

```
php artisan test
```

Usa SQLite en memoria (`phpunit.xml`), así que **no toca tu base de datos
MySQL**. Cubre: login correcto/incorrecto, usuario inactivo, usuario no
autenticado, permisos por rol (asistente no gestiona usuarios/auditoría),
creación de acta, rechazo de acta duplicada (número/año/tipo), rechazo de
archivo no-PDF, detección de PDF duplicado por hash, consulta de acta,
inmutabilidad (`update`/`delete` de `ActaPolicy` siempre `false`),
visualización del PDF, búsqueda de actas y registro en auditoría de LOGIN
y CREAR_ACTA.

## 17. Despliegue en producción (Windows Server + Apache)

1. Copiar el proyecto al servidor, por ejemplo `C:\inetpub\registro-civil`
   o `C:\xampp\htdocs\registro-civil` según la distribución de Apache usada.
2. Configurar un **VirtualHost de Apache** apuntando su `DocumentRoot` a
   la carpeta `public/` del proyecto (nunca a la raíz del proyecto).
3. `composer install --no-dev --optimize-autoloader`
4. Configurar `.env` de producción: `APP_ENV=production`, `APP_DEBUG=false`,
   `APP_URL=https://registro.municipalidad.gob.pe` (dominio real).
5. `php artisan migrate --force`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. Dar permisos de escritura al usuario de Apache sobre `storage/` y
   `bootstrap/cache/`.
8. Verificar que `mod_rewrite` esté habilitado (usa el `.htaccess` incluido
   en `public/`).

No se usa Nginx, Docker, WSL ni Linux en ningún punto de este flujo de
producción; el servidor final sigue siendo **Apache sobre Windows Server**.

## 18. HTTPS

En producción, servir exclusivamente por HTTPS (certificado del dominio
institucional). En desarrollo local con Herd, los sitios "parqueados" ya
obtienen HTTPS local automático (`https://registro-civil.test`), útil para
probar cookies/sesiones seguras antes de desplegar.

## Flujo del sistema y su correspondencia con los módulos

```
LOGIN (AuthController + rate limiting)
   → DASHBOARD (DashboardController + DashboardService)
   → REGISTRAR ACTA (ActaController@create/store + ActaService)
        → selecciona tipo → formulario dinámico (Blade + JS)
        → sube PDF → PdfValidationService (extensión, MIME, tamaño, cabecera, hash)
        → verifica duplicado (numero+año+tipo y hash) → ActaService
        → guarda PDF → PdfStorageService (nombre seguro, storage/app/private)
        → transacción DB → Acta + detalle específico → AuditoriaService::CREAR_ACTA
   → ACTA BLOQUEADA (ActaPolicy: update/delete siempre false)
   → BUSCAR / CONSULTAR (ActaController@index/show + ActaService::buscar)
   → VER PDF / IMPRIMIR (PdfController + actas/print.blade.php)
   → AUDITORÍA (AuditoriaController, solo administrador)
   → IMPORTACIÓN MASIVA (ImportacionController + ImportacionService, solo administrador)
```

Todas las acciones relevantes (login, logout, crear acta, consultar acta,
ver PDF, imprimir, subir PDF, intento de duplicado, crear/editar/desactivar
usuario, importación masiva) pasan por `AuditoriaService::registrar()`.
