<?php

namespace App\Services;

use App\Models\Acta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class IncidenciaService
{
    public const ESTADOS_VISIBLES = ['PENDIENTE', 'INUTILIZADA'];

    public const ESTADOS = [
        'PENDIENTE' => ['Pendiente', 'danger', 'exclamation-circle-fill'],
        'REVISION' => ['Requiere revisión', 'danger', 'exclamation-triangle-fill'],
        'INUTILIZADA' => ['Inutilizada / en blanco', 'danger', 'slash-circle-fill'],
        'ACLARADA' => ['Aclarada', 'info', 'check-circle-fill'],
        'RESUELTA' => ['Resuelta', 'success', 'check-circle-fill'],
    ];

    /** Read the preserved import history; attaching a missing PDF resolves its notice live. */
    public function todas(): Collection
    {
        [$sources, $ids] = $this->fuentes();
        $actas = Acta::with(['nacimiento', 'matrimonio', 'defuncion'])
            ->where(fn ($q) => $q->whereIn('id', $ids)->orWhere(fn ($q) => $q->where('estado', Acta::ESTADO_BORRADOR)->whereNull('pdf_ruta')))->get()->keyBy('id');
        $linked = [];
        $items = collect($sources)->map(function ($item) use ($actas, &$linked) {
            $acta = $actas->get($item['acta_id']);
            if ($acta) {
                $linked[$acta->id] = true;
                $item = array_replace($item, ['tipo' => $acta->tipo, 'anio' => $acta->anio, 'orden' => $acta->orden_tipo, 'sobre' => $acta->sobre]);
                $estado = $acta->pdf_ruta ? 'RESUELTA' : 'PENDIENTE';
            } else {
                $text = mb_strtolower($item['detalle']);
                $estado = match (true) {
                    str_contains($text, 'inutiliz'), str_contains($text, 'anulad'), str_contains($text, 'en blanco') => 'INUTILIZADA',
                    str_contains($text, 'contradict'), str_contains($text, 'conflicto'), str_contains($text, 'otro nombre') => 'REVISION',
                    str_contains($text, 'traslad'), str_contains($text, 'repetid'), str_contains($text, 'ya incluida') => 'ACLARADA',
                    default => 'REVISION',
                };
            }
            return $this->presentar($item, $estado, $acta);
        });
        foreach ($actas as $acta) {
            if (isset($linked[$acta->id]) || $acta->pdf_ruta || ! $acta->esBorrador()) { continue; }
            $items->push($this->presentar([
                'id' => hash('sha256', 'acta/'.$acta->id), 'acta_id' => $acta->id,
                'tipo' => $acta->tipo, 'anio' => $acta->anio, 'orden' => $acta->orden_tipo, 'sobre' => $acta->sobre,
                'nombre' => $acta->nacimiento?->apellido_nombre ?? $acta->matrimonio?->apellido_nombre ?? $acta->defuncion?->nombre_completo ?? '',
                'detalle' => 'Este registro está pendiente del documento PDF original.', 'fuente' => 'Ficha del acta', 'ruta' => null, 'pagina' => 1,
            ], 'PENDIENTE', $acta));
        }
        return $items->sortBy(fn ($r) => [array_search($r['estado'], array_keys(self::ESTADOS)), $r['anio'], $r['orden']])->values();
    }

    /**
     * The import lots are static files; parse them once and reuse the result until
     * a lote.json is added, removed or modified (the key includes a fast content hash;
     * mtimes only have one-second resolution and could miss quick rewrites).
     */
    private function fuentes(): array
    {
        $disk = Storage::disk('local');
        $lotes = collect($disk->directories('private/cargas'))
            ->filter(fn ($folder) => $disk->exists($folder.'/lote.json'))
            ->mapWithKeys(fn ($folder) => [$folder => hash_file('xxh128', $disk->path($folder.'/lote.json'))])
            ->all();
        $clave = 'incidencias:fuentes:'.hash('sha256', json_encode($lotes));
        return \Illuminate\Support\Facades\Cache::remember($clave, now()->addDay(), fn () => $this->leerLotes(array_keys($lotes)));
    }

    private function leerLotes(array $carpetas): array
    {
        $disk = Storage::disk('local');
        $sources = []; $ids = [];
        foreach ($carpetas as $folder) {
            $lot = json_decode($disk->get($folder.'/lote.json'), true, 512, JSON_THROW_ON_ERROR);
            if (($lot['estado'] ?? '') !== 'importado') { continue; }
            foreach ($lot['incidencias'] ?? [] as $index => $incident) {
                $row = collect($lot['filas'] ?? [])->first(fn ($r) => (string) ($r['orden_tipo'] ?? '') === (string) ($incident['orden'] ?? '')) ?? [];
                $file = $lot['archivos'][$row['archivo'] ?? ''] ?? (count($lot['archivos'] ?? []) === 1 ? reset($lot['archivos']) : null);
                $sources[] = [
                    'id' => hash('sha256', basename($folder).'/'.$index),
                    'acta_id' => $incident['acta_id'] ?? null,
                    'tipo' => $row['tipo'] ?? '', 'anio' => $row['anio'] ?? null,
                    'orden' => $incident['orden'] ?? null, 'sobre' => $row['sobre'] ?? '—',
                    'nombre' => $incident['nombre'] ?? '', 'detalle' => $incident['detalle'] ?? '',
                    'fuente' => $row['archivo'] ?? $lot['nombre'] ?? '',
                    'ruta' => $file['ruta'] ?? null, 'pagina' => max(1, (int) ($incident['pagina'] ?? 1)),
                ];
                if (! empty($incident['acta_id'])) { $ids[] = $incident['acta_id']; }
            }
        }
        return [$sources, $ids];
    }

    private function presentar(array $item, string $estado, ?Acta $acta): array
    {
        [$label, $color, $icon] = self::ESTADOS[$estado];
        $item['estado'] = $estado; $item['etiqueta'] = $label; $item['color'] = $color; $item['icono'] = $icon;
        $item['acta'] = $acta;
        $item['resumen'] = match ($estado) {
            'PENDIENTE' => 'Falta el acta original', 'RESUELTA' => 'Documento original adjuntado',
            'INUTILIZADA' => 'Asiento inutilizado, anulado o en blanco',
            'ACLARADA' => str_contains(mb_strtolower($item['detalle']), 'traslad') ? 'Referencia trasladada a otro sobre' : 'Referencia repetida ya identificada',
            default => 'Diferencia en los datos del documento',
        };
        $item['siguiente'] = match ($estado) {
            'PENDIENTE' => 'Localizar y adjuntar el acta original. La nota recibida no reemplaza el documento.',
            'REVISION' => 'Comparar el índice con el acta física y confirmar los datos con el responsable del archivo.',
            'INUTILIZADA' => 'Conservar este antecedente. No debe utilizarse como un acta vigente.',
            'ACLARADA' => 'Consultar la referencia indicada en el detalle. No se necesita crear otra acta.',
            default => 'El PDF ya está disponible en la ficha del acta. Se conserva el aviso como historial.',
        };
        return $item;
    }
}
