<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BusquedaPublicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public, unauthenticated availability check. It only answers whether a single
 * matching act with its PDF exists: it never returns data from the archive.
 */
class ConsultaPublicaController extends Controller
{
    public function disponibilidad(Request $request, BusquedaPublicaService $busqueda): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['NACIMIENTO', 'MATRIMONIO', 'DEFUNCION'])],
            'titular' => ['required', 'string', 'min:5', 'max:200'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $disponible = $busqueda->buscar($datos) !== null;

        return response()->json([
            'disponible' => $disponible,
            'mensaje' => $disponible
                ? 'El acta está disponible para solicitar una copia certificada en /tramite.'
                : 'No se encontró una coincidencia única en el archivo digital. Consulta en el Registro Civil; no se ha generado ningún cobro.',
        ]);
    }
}
