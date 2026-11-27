# Cobertura de código por capa y por archivo

Generado con PHPUnit + Xdebug el 2026-11-27 a partir de `cobertura/clover.xml`. **Total de líneas: 84.15 % (2113 de 2511)** · métodos: 70.6 % (219 de 310).

Regenerar: desde `codigo/`, `powershell -ExecutionPolicy Bypass -File tests\cobertura.ps1 -Abrir`.

## Por capa

| Capa | Líneas cubiertas | Cobertura |
|---|---|---|
| Exceptions | 4 / 4 | 100.0 % |
| Support | 5 / 5 | 100.0 % |
| Http/Middleware | 49 / 50 | 98.0 % |
| Http/Requests | 125 / 129 | 96.9 % |
| Http/Controllers | 1040 / 1144 | 90.9 % |
| Models | 106 / 121 | 87.6 % |
| Services | 574 / 662 | 86.7 % |
| Policies | 12 / 14 | 85.7 % |
| Mail | 14 / 17 | 82.4 % |
| Http/Resources | 45 / 57 | 78.9 % |
| Providers | 27 / 47 | 57.4 % |
| Console | 112 / 254 | 44.1 % |
| Notifications | 0 / 7 | 0.0 % |

## Por archivo (de menor a mayor cobertura)

| Archivo | Líneas | Cobertura |
|---|---|---|
| `app/Console/Commands/GenerarDatosPrueba.php` | 0 / 78 | 0.0 % |
| `app/Console/Commands/RecuperarCopiasFirmadas.php` | 0 / 26 | 0.0 % |
| `app/Http/Resources/ActaResource.php` | 0 / 12 | 0.0 % |
| `app/Models/Configuracion.php` | 0 / 5 | 0.0 % |
| `app/Models/Role.php` | 0 / 1 | 0.0 % |
| `app/Notifications/RestablecerContrasena.php` | 0 / 7 | 0.0 % |
| `app/Mail/DocumentoFirmadoMail.php` | 1 / 3 | 33.3 % |
| `app/Http/Controllers/Api/ActaApiController.php` | 5 / 10 | 50.0 % |
| `app/Mail/CodigoTramite.php` | 1 / 2 | 50.0 % |
| `app/Models/ActaMatrimonio.php` | 1 / 2 | 50.0 % |
| `app/Services/PdfStorageService.php` | 18 / 32 | 56.2 % |
| `app/Providers/AppServiceProvider.php` | 27 / 47 | 57.4 % |
| `app/Policies/UserPolicy.php` | 3 / 5 | 60.0 % |
| `app/Http/Controllers/UsuarioController.php` | 39 / 62 | 62.9 % |
| `app/Console/Commands/RecuperarClave.php` | 7 / 11 | 63.6 % |
| `app/Console/Commands/CrearRespaldo.php` | 59 / 92 | 64.1 % |
| `app/Services/FirmaDocumentoService.php` | 31 / 48 | 64.6 % |
| `app/Models/ActaDefuncion.php` | 2 / 3 | 66.7 % |
| `app/Http/Controllers/AuditoriaController.php` | 13 / 18 | 72.2 % |
| `app/Models/ActaNacimiento.php` | 3 / 4 | 75.0 % |
| `app/Services/ActaService.php` | 113 / 149 | 75.8 % |
| `app/Models/User.php` | 10 / 13 | 76.9 % |
| `app/Http/Controllers/PasswordResetController.php` | 35 / 45 | 77.8 % |
| `app/Services/BusquedaPublicaService.php` | 12 / 15 | 80.0 % |
| `app/Services/PdfValidationService.php` | 22 / 27 | 81.5 % |
| `app/Http/Middleware/EnsureUserHasRole.php` | 6 / 7 | 85.7 % |
| `app/Http/Requests/StoreMatrimonioRequest.php` | 6 / 7 | 85.7 % |
| `app/Http/Requests/StoreNacimientoRequest.php` | 6 / 7 | 85.7 % |
| `app/Http/Controllers/PortalPublicoController.php` | 115 / 132 | 87.1 % |
| `app/Http/Controllers/CanalEntregaController.php` | 31 / 35 | 88.6 % |
| `app/Http/Requests/StoreDefuncionRequest.php` | 8 / 9 | 88.9 % |
| `app/Http/Controllers/AuthController.php` | 44 / 49 | 89.8 % |
| `app/Services/ApisPeruService.php` | 10 / 11 | 90.9 % |
| `app/Services/FirmaPeruService.php` | 20 / 22 | 90.9 % |
| `app/Http/Controllers/EntregaDocumentoController.php` | 130 / 142 | 91.5 % |
| `app/Services/IncidenciaService.php` | 65 / 71 | 91.5 % |
| `app/Http/Controllers/PdfController.php` | 33 / 36 | 91.7 % |
| `app/Http/Controllers/ReporteController.php` | 88 / 96 | 91.7 % |
| `app/Http/Controllers/ActaController.php` | 69 / 74 | 93.2 % |
| `app/Services/CodigoTramiteService.php` | 15 / 16 | 93.8 % |
| `app/Http/Controllers/PerfilController.php` | 46 / 49 | 93.9 % |
| `app/Models/Acta.php` | 52 / 55 | 94.5 % |
| `app/Console/Commands/MantenimientoTramites.php` | 18 / 19 | 94.7 % |
| `app/Http/Controllers/MercadoPagoController.php` | 59 / 62 | 95.2 % |
| `app/Services/DecisionTramiteService.php` | 22 / 23 | 95.7 % |
| `app/Services/YapeOrdersService.php` | 61 / 63 | 96.8 % |
| `app/Http/Requests/StoreActaRequest.php` | 36 / 37 | 97.3 % |
| `app/Http/Controllers/FirmaPeruController.php` | 120 / 121 | 99.2 % |
| `app/Console/Commands/VerificarSistema.php` | 28 / 28 | 100.0 % |
| `app/Exceptions/ConflictoVersionException.php` | 4 / 4 | 100.0 % |
| `app/Http/Controllers/Api/V1/ActaController.php` | 15 / 15 | 100.0 % |
| `app/Http/Controllers/Api/V1/AutenticacionController.php` | 36 / 36 | 100.0 % |
| `app/Http/Controllers/Api/V1/ConsultaPublicaController.php` | 12 / 12 | 100.0 % |
| `app/Http/Controllers/Api/V1/SaludController.php` | 11 / 11 | 100.0 % |
| `app/Http/Controllers/Api/V1/TramiteController.php` | 20 / 20 | 100.0 % |
| `app/Http/Controllers/AtencionPublicaController.php` | 28 / 28 | 100.0 % |
| `app/Http/Controllers/DashboardController.php` | 7 / 7 | 100.0 % |
| `app/Http/Controllers/FiliacionController.php` | 20 / 20 | 100.0 % |
| `app/Http/Controllers/IncidenciaController.php` | 24 / 24 | 100.0 % |
| `app/Http/Controllers/YapeOrdersController.php` | 40 / 40 | 100.0 % |
| `app/Http/Middleware/CabecerasSeguridad.php` | 17 / 17 | 100.0 % |
| `app/Http/Middleware/EnsurePortalAccess.php` | 10 / 10 | 100.0 % |
| `app/Http/Middleware/EnsureUserIsActive.php` | 5 / 5 | 100.0 % |
| `app/Http/Middleware/ForzarRespuestaJson.php` | 2 / 2 | 100.0 % |
| `app/Http/Middleware/ThrottleConPruebasCarga.php` | 9 / 9 | 100.0 % |
| `app/Http/Requests/DecidirTramiteRequest.php` | 9 / 9 | 100.0 % |
| `app/Http/Requests/GuardarFiliacionRequest.php` | 7 / 7 | 100.0 % |
| `app/Http/Requests/ReporteRequest.php` | 27 / 27 | 100.0 % |
| `app/Http/Requests/StoreUsuarioRequest.php` | 8 / 8 | 100.0 % |
| `app/Http/Requests/UpdateUsuarioRequest.php` | 18 / 18 | 100.0 % |
| `app/Http/Resources/V1/ActaResource.php` | 21 / 21 | 100.0 % |
| `app/Http/Resources/V1/TramiteResource.php` | 24 / 24 | 100.0 % |
| `app/Mail/EstadoTramiteMail.php` | 12 / 12 | 100.0 % |
| `app/Models/Auditoria.php` | 6 / 6 | 100.0 % |
| `app/Models/PagoTramite.php` | 2 / 2 | 100.0 % |
| `app/Models/TramiteEvento.php` | 10 / 10 | 100.0 % |
| `app/Models/TramitePublico.php` | 20 / 20 | 100.0 % |
| `app/Policies/ActaPolicy.php` | 8 / 8 | 100.0 % |
| `app/Policies/AuditoriaPolicy.php` | 1 / 1 | 100.0 % |
| `app/Services/AuditoriaService.php` | 10 / 10 | 100.0 % |
| `app/Services/CacheConsultas.php` | 5 / 5 | 100.0 % |
| `app/Services/CruceParentescoService.php` | 9 / 9 | 100.0 % |
| `app/Services/DashboardService.php` | 24 / 24 | 100.0 % |
| `app/Services/DeclaracionSolicitud.php` | 8 / 8 | 100.0 % |
| `app/Services/EnvioDocumentoCorreo.php` | 23 / 23 | 100.0 % |
| `app/Services/MercadoPagoService.php` | 91 / 91 | 100.0 % |
| `app/Services/PortalHttpOptions.php` | 7 / 7 | 100.0 % |
| `app/Services/WhatsAppConsulta.php` | 8 / 8 | 100.0 % |
| `app/Support/NombrePersona.php` | 5 / 5 | 100.0 % |
