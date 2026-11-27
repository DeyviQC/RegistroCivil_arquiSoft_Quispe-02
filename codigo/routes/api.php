<?php

use App\Http\Controllers\Api\ActaApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API interna (Sección 49)
|--------------------------------------------------------------------------
| Protegida por sesión web (auth), NO es una API pública abierta.
| Pensada para ser consumida por JavaScript del propio sistema
| (por ejemplo, autocompletado o widgets del dashboard).
*/
// The api group has no session: 'web' adds it so the logged-in staff session is recognised.
Route::middleware(['web', 'auth', 'role:administrador,asistente'])->prefix('actas')->group(function () {
    Route::get('/', [ActaApiController::class, 'index']);
    Route::get('/search', [ActaApiController::class, 'search']);
    Route::get('/{acta}', [ActaApiController::class, 'show']);
});
