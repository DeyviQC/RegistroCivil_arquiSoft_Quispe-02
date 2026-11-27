<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Health check for monitoring (availability): database reachable and storage writable. */
class SaludController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $baseDatos = rescue(fn () => DB::select('select 1') !== [], false, false);
        $almacenamiento = is_writable(storage_path('app'));
        $ok = $baseDatos && $almacenamiento;

        return response()->json([
            'estado' => $ok ? 'ok' : 'degradado',
            'componentes' => [
                'base_datos' => $baseDatos ? 'ok' : 'error',
                'almacenamiento' => $almacenamiento ? 'ok' : 'error',
            ],
            'fecha' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
