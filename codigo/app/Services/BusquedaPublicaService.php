<?php

namespace App\Services;

use App\Models\Acta;
use App\Support\NombrePersona;

class BusquedaPublicaService
{
    public function buscar(array $datos): ?Acta
    {
        [$relacion, $fecha] = match ($datos['tipo']) {
            'NACIMIENTO' => ['nacimiento', 'fecha_nacimiento'],
            'MATRIMONIO' => ['matrimonio', 'fecha_matrimonio'],
            'DEFUNCION' => ['defuncion', 'fecha_defuncion'],
            default => throw new \InvalidArgumentException('Tipo de acta no válido.'),
        };
        // The event date is a DATE column: an exact, indexed match on the detail table first,
        // then only those acts (instead of a DATE() subquery per act, which scans the table).
        $tabla = (new Acta)->$relacion()->getRelated()->getTable();
        // A whole-day range uses the index and also matches drivers that store a time part (SQLite).
        $dia = \Illuminate\Support\Carbon::parse($datos['fecha'])->toDateString();
        $ids = \Illuminate\Support\Facades\DB::table($tabla)->whereBetween($fecha, [$dia, $dia.' 23:59:59'])->pluck('acta_id');
        $candidatas = $ids->isEmpty() ? collect() : Acta::with($relacion)->whereIn('id', $ids)
            ->where('tipo', $datos['tipo'])->where('estado', Acta::ESTADO_REGISTRADA)->get();
        $coinciden = $candidatas->filter(fn ($acta) => NombrePersona::coinciden($acta->$relacion->apellido_nombre, $datos['titular']));
        if ($coinciden->count() !== 1) return null;
        $acta = $coinciden->first();
        return $acta->pdf_ruta && app(PdfStorageService::class)->existe($acta->pdf_ruta) ? $acta : null;
    }
}
