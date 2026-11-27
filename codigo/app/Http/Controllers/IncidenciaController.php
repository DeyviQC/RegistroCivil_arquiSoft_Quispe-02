<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Services\IncidenciaService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IncidenciaController extends Controller
{
    public function index(Request $request, IncidenciaService $service)
    {
        $this->authorize('viewAny', Acta::class);
        $filters = $request->validate(['anio' => 'nullable|integer|between:1800,2200', 'tipo' => ['nullable', Rule::in(['NACIMIENTO', 'MATRIMONIO', 'DEFUNCION'])], 'situacion' => ['nullable', Rule::in(array_keys(IncidenciaService::ESTADOS))], 'detalle' => 'nullable|regex:/^[a-f0-9]{64}$/']);
        if (! empty($filters['situacion']) && ! in_array($filters['situacion'], IncidenciaService::ESTADOS_VISIBLES, true)) {
            return redirect()->route('incidencias.index', $request->only('anio', 'tipo'));
        }
        $all = $service->todas()->whereIn('estado', IncidenciaService::ESTADOS_VISIBLES);
        $counts = $all->countBy('estado'); $years = $all->pluck('anio')->filter()->unique()->sortDesc();
        $selected = isset($filters['detalle']) ? $all->firstWhere('id', $filters['detalle']) : null;
        abort_if(isset($filters['detalle']) && ! $selected, 404);
        $filtered = $all->filter(fn ($i) => (! ($filters['anio'] ?? '') || $i['anio'] == $filters['anio']) && (! ($filters['tipo'] ?? '') || $i['tipo'] === $filters['tipo']) && (! ($filters['situacion'] ?? '') || $i['estado'] === $filters['situacion']))->values();
        $page = max(1, $request->integer('page', 1));
        $incidencias = new LengthAwarePaginator($filtered->forPage($page, 15), $filtered->count(), 15, $page, ['path' => route('incidencias.index'), 'query' => $request->query()]);
        return view('incidencias.index', compact('incidencias', 'counts', 'years', 'selected', 'filters'));
    }

    public function documento(string $id, IncidenciaService $service)
    {
        $this->authorize('viewAny', Acta::class);
        $incident = $service->todas()->firstWhere('id', $id);
        abort_unless($incident && $incident['ruta'], 404);
        $disk = Storage::disk('local');
        $path = realpath($disk->path($incident['ruta']));
        $private = realpath($disk->path('private'));
        abort_unless($path && $private && str_starts_with(str_replace('\\', '/', $path), rtrim(str_replace('\\', '/', $private), '/').'/') && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf', 404);
        app(\App\Services\AuditoriaService::class)->registrar(
            \App\Services\AuditoriaService::VER_PDF, 'Incidencias', $incident['acta_id'],
            'Consulta del documento recibido: '.$incident['tipo'].' '.$incident['anio'].', orden '.$incident['orden'].'.'
        );
        return response()->file($path, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
