# Evidencia AC04 — matriz de autorización (Newman)

Ejecución: 26/11/2026 12:02 · peticiones: 53 · aserciones: 71 · fallidas: 0

| Caso | Petición | Respuesta | Resultado |
|---|---|---|---|
| 1. Panel sin sesión / 01 · Inicio del panel | GET /dashboard | 302 → /login | ✅ |
| 1. Panel sin sesión / 02 · Archivo de actas | GET /actas | 302 → /login | ✅ |
| 1. Panel sin sesión / 03 · PDF de un acta | GET /actas/{{acta}}/pdf | 302 → /login | ✅ |
| 1. Panel sin sesión / 04 · Copia firmada de un acta | GET /actas/{{acta}}/pdf-firmado | 302 → /login | ✅ |
| 1. Panel sin sesión / 05 · Gestión de usuarios | GET /usuarios | 302 → /login | ✅ |
| 1. Panel sin sesión / 06 · Auditoría | GET /auditoria | 302 → /login | ✅ |
| 1. Panel sin sesión / 07 · Reporte Excel | GET /reportes/excel | 302 → /login | ✅ |
| 1. Panel sin sesión / 08 · Bandeja de solicitudes | GET /atencion-ciudadana | 302 → /login | ✅ |
| 1. Panel sin sesión / 09 · Original para firmar de una solicitud | GET /atencion-ciudadana/{{tramite}}/firma/original | 302 → /login | ✅ |
| 1. Panel sin sesión / 10 · API interna (JSON) | GET /api/actas | 401 | ✅ |
| 2. Portal ciudadano sin verificar / 11 · Estado de una solicitud | GET /tramite/estado | 302 → /tramite/seguimiento | ✅ |
| 2. Portal ciudadano sin verificar / 12 · Descarga del acta firmada | GET /tramite/documento | 302 → /tramite/seguimiento | ✅ |
| 2. Portal ciudadano sin verificar / 13 · Formulario de pago Yape | GET /tramite/yape | 302 → /tramite/seguimiento | ✅ |
| 2. Portal ciudadano sin verificar / 14 · Lista de mis solicitudes | GET /tramite/mis-solicitudes | 403 | ✅ |
| 2. Portal ciudadano sin verificar / 15 · Pantalla de código sin solicitud pendiente | GET /tramite/codigo | 403 | ✅ |
| 2. Portal ciudadano sin verificar / 16 · Enlace directo con firma falsa | GET /tramite/acceso/{{codigo}} | 302 → /tramite/seguimiento | ✅ |
| 2. Portal ciudadano sin verificar / 17 · …y no deja acceso tras el enlace falso | GET /tramite/estado | 302 → /tramite/seguimiento | ✅ |
| 3. Protección CSRF (formularios sin token) / 18 · Responder observación sin token | POST /tramite/responder | 419 | ✅ |
| 3. Protección CSRF (formularios sin token) / 19 · Guardar canales sin token | POST /tramite/canales-entrega | 419 | ✅ |
| 3. Protección CSRF (formularios sin token) / 20 · Iniciar sesión sin token | POST /login | 419 | ✅ |
| 4. Archivos y rutas internas expuestas / 21 · Archivo .env | GET /.env | 404 | ✅ |
| 4. Archivos y rutas internas expuestas / 22 · composer.json | GET /composer.json | 404 | ✅ |
| 4. Archivos y rutas internas expuestas / 23 · Log de Laravel | GET /storage/logs/laravel.log | 403 | ✅ |
| 4. Archivos y rutas internas expuestas / 24 · PDF privado por ruta directa | GET /storage/private/carga/acta-sintetica.pdf | 403 | ✅ |
| 4. Archivos y rutas internas expuestas / 25 · Webhook de pago con firma falsa | POST /pagos/mercadopago/notificacion | 401 | ✅ |
| 4. Archivos y rutas internas expuestas / 26 · Webhook de pasarela retirada (Izipay) | POST /pagos/izipay/notificacion | 404 | ✅ |
| 5. Límites de intentos / 27 · Abrir el portal (obtiene token) | GET /tramite | 200 | ✅ |
| 5. Límites de intentos / 28 · Búsqueda 1 de 5 permitida | POST /tramite/buscar | 302 → /tramite | ✅ |
| 5. Límites de intentos / 29 · Búsqueda 2 de 5 permitida | POST /tramite/buscar | 302 → /tramite | ✅ |
| 5. Límites de intentos / 30 · Búsqueda 3 de 5 permitida | POST /tramite/buscar | 302 → /tramite | ✅ |
| 5. Límites de intentos / 31 · Búsqueda 4 de 5 permitida | POST /tramite/buscar | 302 → /tramite | ✅ |
| 5. Límites de intentos / 32 · Búsqueda 5 de 5 permitida | POST /tramite/buscar | 302 → /tramite | ✅ |
| 5. Límites de intentos / 33 · Búsqueda 6: bloqueada | POST /tramite/buscar | 429 | ✅ |
| 5. Límites de intentos / 34 · Abrir login (obtiene token) | GET /login | 200 | ✅ |
| 5. Límites de intentos / 35 · Contraseña incorrecta 1 | POST /login | 302 → /login | ✅ |
| 5. Límites de intentos / 36 · Contraseña incorrecta 2 | POST /login | 302 → /login | ✅ |
| 5. Límites de intentos / 37 · Contraseña incorrecta 3 | POST /login | 302 → /login | ✅ |
| 5. Límites de intentos / 38 · Contraseña incorrecta 4 | POST /login | 302 → /login | ✅ |
| 5. Límites de intentos / 39 · Contraseña incorrecta 5 | POST /login | 302 → /login | ✅ |
| 5. Límites de intentos / 40 · Sexto intento de login | POST /login | 302 → /login | ✅ |
| 5. Límites de intentos / 41 · …muestra el bloqueo por intentos | GET /login | 200 | ✅ |
| 6. Asistente intentando funciones de administrador / 42 · Abrir login (obtiene token) | GET /login | 200 | ✅ |
| 6. Asistente intentando funciones de administrador / 43 · Iniciar sesión como asistente | POST /login | 302 → /atencion-ciudadana/1/firma/original | ✅ |
| 6. Asistente intentando funciones de administrador / 44 · Control positivo: inicio del panel | GET /dashboard | 200 | ✅ |
| 6. Asistente intentando funciones de administrador / 45 · Lista de usuarios | GET /usuarios | 403 | ✅ |
| 6. Asistente intentando funciones de administrador / 46 · Crear usuario (formulario) | GET /usuarios/create | 403 | ✅ |
| 6. Asistente intentando funciones de administrador / 47 · Crear usuario (envío) | POST /usuarios | 403 | ✅ |
| 6. Asistente intentando funciones de administrador / 48 · Auditoría | GET /auditoria | 403 | ✅ |
| 6. Asistente intentando funciones de administrador / 49 · Cerrar sesión | POST /logout | 302 → /login | ✅ |
| 6. Asistente intentando funciones de administrador / 50 · …la sesión cerrada ya no entra | GET /dashboard | 302 → /login | ✅ |
| 7. Ciudadano intentando ver una solicitud ajena / 51 · Abrir el portal (obtiene token) | GET /tramite | 200 | ✅ |
| 7. Ciudadano intentando ver una solicitud ajena / 52 · Elegir una solicitud que no le pertenece | POST /tramite/seleccionar | 403 | ✅ |
| 7. Ciudadano intentando ver una solicitud ajena / 53 · Seguimiento con datos inexistentes: misma respuesta genérica | POST /tramite/seguimiento | 302 → /tramite/codigo | ✅ |
