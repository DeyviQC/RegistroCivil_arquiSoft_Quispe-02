<?php

use App\Http\Controllers\ActaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('tramite')->name('portal.')->group(function () {
    Route::post('/canales-entrega', [\App\Http\Controllers\CanalEntregaController::class, 'guardar'])->middleware('portal.acceso')->name('canales');
    Route::post('/whatsapp', [\App\Http\Controllers\CanalEntregaController::class, 'whatsapp'])->middleware('portal.acceso')->middleware('throttle:10,1')->name('whatsapp');
    // Reloading or reopening the WhatsApp step must not end in a 405 page.
    Route::get('/whatsapp', fn () => redirect()->route('portal.estado'));
    Route::get('/', [\App\Http\Controllers\PortalPublicoController::class, 'inicio'])->name('inicio');
    Route::post('/buscar', [\App\Http\Controllers\PortalPublicoController::class, 'buscar'])->middleware('throttle:5,1')->name('buscar');
    Route::get('/datos', [\App\Http\Controllers\PortalPublicoController::class, 'datos'])->name('datos');
    Route::post('/datos', [\App\Http\Controllers\PortalPublicoController::class, 'registrar'])->middleware('throttle:portal-datos')->name('registrar');
    Route::get('/codigo', [\App\Http\Controllers\PortalPublicoController::class, 'codigo'])->name('codigo');
    Route::post('/codigo', [\App\Http\Controllers\PortalPublicoController::class, 'verificar'])->middleware('throttle:5,1')->name('verificar');
    Route::post('/reenviar', [\App\Http\Controllers\PortalPublicoController::class, 'reenviar'])->middleware('throttle:3,10')->name('reenviar');
    Route::get('/estado', [\App\Http\Controllers\PortalPublicoController::class, 'estado'])->middleware('portal.acceso')->name('estado');
    Route::post('/responder', [\App\Http\Controllers\PortalPublicoController::class, 'responder'])->middleware('portal.acceso')->middleware('throttle:5,1')->name('responder');
    Route::get('/documento', [\App\Http\Controllers\PortalPublicoController::class, 'documento'])->middleware('portal.acceso')->middleware('throttle:20,1')->name('documento');
    Route::get('/mis-solicitudes', [\App\Http\Controllers\PortalPublicoController::class, 'solicitudes'])->name('solicitudes');
    Route::post('/seleccionar', [\App\Http\Controllers\PortalPublicoController::class, 'seleccionar'])->name('seleccionar');
    Route::get('/seguimiento', [\App\Http\Controllers\PortalPublicoController::class, 'seguimiento'])->name('seguimiento');
    Route::post('/seguimiento', [\App\Http\Controllers\PortalPublicoController::class, 'recuperar'])->middleware('throttle:3,10')->name('recuperar');
    // Signed link from the notice e-mails (signature checked in the controller to explain expiry).
    Route::get('/acceso/{codigo}', [\App\Http\Controllers\PortalPublicoController::class, 'acceso'])->whereUuid('codigo')->middleware('throttle:10,1')->name('acceso');
});
Route::post('/tramite/pagar-mercadopago', [\App\Http\Controllers\MercadoPagoController::class, 'pagar'])->middleware('portal.acceso')->middleware('throttle:portal-pago')->name('mercadopago.pagar');
Route::get('/tramite/yape', [\App\Http\Controllers\YapeOrdersController::class, 'formulario'])->middleware('portal.acceso')->name('yape.formulario');
Route::post('/tramite/yape', [\App\Http\Controllers\YapeOrdersController::class, 'pagar'])->middleware('portal.acceso')->middleware('throttle:portal-pago')->name('yape.pagar');
Route::post('/tramite/mercadopago/comprobar', [\App\Http\Controllers\MercadoPagoController::class, 'comprobar'])->middleware('portal.acceso')->middleware('throttle:10,1')->name('mercadopago.comprobar');
Route::get('/pagos/mercadopago/retorno', [\App\Http\Controllers\MercadoPagoController::class, 'retorno'])->middleware('throttle:30,1,mp-retorno:')->name('mercadopago.retorno');
Route::post('/pagos/mercadopago/notificacion', [\App\Http\Controllers\MercadoPagoController::class, 'notificacion'])->middleware('throttle:60,1,mp-webhook:')->name('mercadopago.notificacion');
Route::prefix('firma-peru/{tramite}/{intento}')->name('firmaperu.')->middleware('throttle:30,1')->group(function () {
    Route::post('/parametros', [\App\Http\Controllers\FirmaPeruController::class, 'parametros'])->name('parametros');
    Route::get('/documento', [\App\Http\Controllers\FirmaPeruController::class, 'documento'])->name('documento');
    Route::get('/logo', [\App\Http\Controllers\FirmaPeruController::class, 'logo'])->name('logo');
    Route::post('/resultado', [\App\Http\Controllers\FirmaPeruController::class, 'resultado'])->name('resultado');
});

/*
|--------------------------------------------------------------------------
| Autenticación
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::redirect('/ciudadano/registro', '/tramite')->name('ciudadano.registro');
    Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/recuperar-contrasena', [\App\Http\Controllers\PasswordResetController::class, 'solicitar'])->name('password.request');
    Route::post('/recuperar-contrasena', [\App\Http\Controllers\PasswordResetController::class, 'enviar'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [\App\Http\Controllers\PasswordResetController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer-contrasena', [\App\Http\Controllers\PasswordResetController::class, 'actualizar'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', function () {
    if (!auth()->check()) return redirect()->route('login');
    return redirect()->route('dashboard');
});
// Old citizen-account module: citizens now use the portal without an account.
Route::redirect('/ciudadano/solicitudes', '/tramite/seguimiento');

/*
|--------------------------------------------------------------------------
| Rutas autenticadas (Administrador + Asistente)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:administrador,asistente'])->group(function () {
    Route::post('/atencion-ciudadana/{tramite}/entrega/correo', [\App\Http\Controllers\CanalEntregaController::class, 'correo'])->name('entrega.correo');
    Route::get('/perfil', [\App\Http\Controllers\PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [\App\Http\Controllers\PerfilController::class, 'update'])->name('perfil.guardar');
    Route::put('/perfil/foto', [\App\Http\Controllers\PerfilController::class, 'update'])->name('perfil.update');
    Route::get('/perfil/foto', [\App\Http\Controllers\PerfilController::class, 'foto'])->name('perfil.foto');
    Route::delete('/perfil/foto', [\App\Http\Controllers\PerfilController::class, 'destroy'])->name('perfil.destroy');
    Route::get('/incidencias', [\App\Http\Controllers\IncidenciaController::class, 'index'])->name('incidencias.index');
    Route::get('/incidencias/{id}/documento', [\App\Http\Controllers\IncidenciaController::class, 'documento'])->where('id', '[a-f0-9]{64}')->name('incidencias.documento');
    Route::get('/atencion-ciudadana', [\App\Http\Controllers\AtencionPublicaController::class, 'index'])->name('atencion.index');
    Route::get('/atencion-ciudadana/{tramite}', [\App\Http\Controllers\AtencionPublicaController::class, 'show'])->name('atencion.show');
    Route::patch('/atencion-ciudadana/{tramite}', [\App\Http\Controllers\AtencionPublicaController::class, 'atender'])->name('atencion.atender');
    Route::get('/atencion-ciudadana/{tramite}/subsanacion/{indice}', [\App\Http\Controllers\AtencionPublicaController::class, 'subsanacion'])->whereNumber('indice')->name('atencion.subsanacion');
    Route::post('/atencion-ciudadana/{tramite}/firma/preparar', [\App\Http\Controllers\EntregaDocumentoController::class, 'preparar'])->name('entrega.preparar');
    Route::get('/atencion-ciudadana/{tramite}/firma/{tipo}', [\App\Http\Controllers\EntregaDocumentoController::class, 'archivo'])->name('entrega.archivo');
    Route::post('/atencion-ciudadana/{tramite}/firma/cargar', [\App\Http\Controllers\EntregaDocumentoController::class, 'cargar'])->middleware('throttle:10,1')->name('entrega.cargar');
    Route::post('/atencion-ciudadana/{tramite}/firma/publicar', [\App\Http\Controllers\EntregaDocumentoController::class, 'publicar'])->name('entrega.publicar');
    Route::post('/atencion-ciudadana/{tramite}/firma/reutilizar', [\App\Http\Controllers\EntregaDocumentoController::class, 'reutilizar'])->middleware('throttle:10,1')->name('entrega.reutilizar');
    Route::get('/actas/{acta}/pdf-firmado', [PdfController::class, 'firmado'])->name('actas.pdf-firmado');
    Route::post('/atencion-ciudadana/{tramite}/firma/descartar', [\App\Http\Controllers\EntregaDocumentoController::class, 'descartar'])->name('entrega.descartar');
    Route::post('/atencion-ciudadana/{tramite}/firma-peru/iniciar', [\App\Http\Controllers\FirmaPeruController::class, 'iniciar'])->middleware('throttle:5,1')->name('firmaperu.iniciar');
    Route::get('/atencion-ciudadana/{tramite}/firma-peru/estado', [\App\Http\Controllers\FirmaPeruController::class, 'estado'])->name('firmaperu.estado');
    Route::post('/atencion-ciudadana/{tramite}/firma-peru/cancelar', [\App\Http\Controllers\FirmaPeruController::class, 'cancelar'])->name('firmaperu.cancelar');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reportes', [\App\Http\Controllers\ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/excel', [\App\Http\Controllers\ReporteController::class, 'excel'])->name('reportes.excel');
    Route::get('/reportes/pdf', [\App\Http\Controllers\ReporteController::class, 'pdf'])->name('reportes.pdf');

    // Actas: registrar, buscar, consultar, PDF, imprimir.
    // NO existen rutas PUT/PATCH/DELETE para actas: son inmutables (Sección 5).
    Route::prefix('actas')->name('actas.')->group(function () {
        Route::get('/{acta}/filiacion', [\App\Http\Controllers\FiliacionController::class, 'edit'])->name('filiacion.edit');
        Route::patch('/{acta}/filiacion', [\App\Http\Controllers\FiliacionController::class, 'update'])->name('filiacion.update');
        Route::get('/', [ActaController::class, 'index'])->name('index');
        Route::get('/create', [ActaController::class, 'create'])->name('create');
        Route::post('/', [ActaController::class, 'store'])->name('store');
        Route::get('/{acta}', [ActaController::class, 'show'])->name('show');
        Route::get('/{acta}/print', [ActaController::class, 'imprimir'])->name('print');
        Route::post('/{acta}/completar-pdf', [ActaController::class, 'completarPdf'])->name('completar-pdf');
        Route::get('/{acta}/pdf', [PdfController::class, 'ver'])->name('pdf');
        Route::get('/{acta}/pdf/visor', [PdfController::class, 'vista'])->name('pdf.visor');
    });

    /*
    |----------------------------------------------------------------------
    | Rutas exclusivas de Administrador
    |----------------------------------------------------------------------
    */
    Route::middleware('role:administrador')->group(function () {
        Route::get('/usuarios/{usuario}/foto', [UsuarioController::class, 'foto'])->name('usuarios.foto');
        Route::resource('usuarios', UsuarioController::class)
            ->except(['show', 'destroy']);
        Route::patch('/usuarios/{usuario}/alternar-estado', [UsuarioController::class, 'alternarEstado'])
            ->name('usuarios.alternar-estado');

        Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

    });
});
