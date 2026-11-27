<?php

use App\Http\Controllers\Api\ActaApiController;
use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST v1 (Sprint 2) — contrato: docs/api/openapi.yaml · Swagger UI: /api/documentacion
|--------------------------------------------------------------------------
| Interfaz JSON sobre los mismos servicios del monolito (no es otro sistema).
| Pública: salud y consulta de disponibilidad. Personal: token Bearer (Sanctum).
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/salud', V1\SaludController::class)->name('salud');
    Route::post('/consultas/disponibilidad', [V1\ConsultaPublicaController::class, 'disponibilidad'])
        ->middleware('throttle:5,1')->name('consultas.disponibilidad');
    Route::post('/auth/token', [V1\AutenticacionController::class, 'emitir'])
        ->middleware('throttle:10,1')->name('auth.emitir');

    Route::middleware(['auth:sanctum', 'role:administrador,asistente'])->group(function () {
        Route::get('/auth/yo', [V1\AutenticacionController::class, 'yo'])->name('auth.yo');
        Route::delete('/auth/token', [V1\AutenticacionController::class, 'revocar'])->name('auth.revocar');

        Route::get('/actas', [V1\ActaController::class, 'index'])->name('actas.index');
        Route::get('/actas/{acta}', [V1\ActaController::class, 'show'])->whereNumber('acta')->name('actas.show');

        Route::get('/tramites', [V1\TramiteController::class, 'index'])->name('tramites.index');
        Route::get('/tramites/{tramite}', [V1\TramiteController::class, 'show'])->whereNumber('tramite')->name('tramites.show');
        Route::post('/tramites/{tramite}/decision', [V1\TramiteController::class, 'decidir'])
            ->whereNumber('tramite')->middleware('throttle:30,1')->name('tramites.decidir');
    });
});

/*
|--------------------------------------------------------------------------
| API interna del panel (sesión web)
|--------------------------------------------------------------------------
| Protegida por la sesión del personal; pensada para JavaScript del propio sistema.
*/
// The api group has no session: 'web' adds it so the logged-in staff session is recognised.
Route::middleware(['web', 'auth', 'role:administrador,asistente'])->prefix('actas')->group(function () {
    Route::get('/', [ActaApiController::class, 'index']);
    Route::get('/search', [ActaApiController::class, 'search']);
    Route::get('/{acta}', [ActaApiController::class, 'show']);
});
