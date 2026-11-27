<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;

class AuditoriaController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Auditoria::class);

        $query = Auditoria::with('usuario');

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->input('usuario_id'));
        }
        if ($request->filled('accion')) {
            $query->where('accion', $request->input('accion'));
        }
        if ($request->filled('modulo')) {
            $query->where('modulo', $request->input('modulo'));
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        $registros = $query->latest('created_at')->paginate(25)->withQueryString();

        return view('auditoria.index', [
            'modulos' => Auditoria::whereNotNull('modulo')->where('modulo', '!=', '')->distinct()->orderBy('modulo')->pluck('modulo'),
            'registros' => $registros,
            'filtros' => $request->only(['usuario_id', 'accion', 'modulo', 'fecha_desde', 'fecha_hasta']),
        ]);
    }
}
