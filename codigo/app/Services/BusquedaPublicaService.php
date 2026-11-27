<?php

namespace App\Services;

use App\Models\Acta;
use Illuminate\Support\Str;

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
        $buscado = $this->normalizar($datos['titular']);
        $coinciden = $candidatas->filter(fn ($acta) => $this->normalizar($acta->$relacion->apellido_nombre) === $buscado);
        if ($coinciden->count() !== 1) return null;
        $acta = $coinciden->first();
        return $acta->pdf_ruta && app(PdfStorageService::class)->existe($acta->pdf_ruta) ? $acta : null;
    }

    private function normalizar(string $nombre): string
    {
        $partes = preg_split('/[^A-Z0-9]+/', strtoupper(Str::ascii($nombre)), -1, PREG_SPLIT_NO_EMPTY);
        sort($partes);
        return implode(' ', $partes);
    }
}
