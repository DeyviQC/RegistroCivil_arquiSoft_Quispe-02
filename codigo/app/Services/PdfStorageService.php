<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfStorageService
{
    private const DISCO = 'local';
    private const CARPETA_BASE = 'private/actas';

    /**
     * @return array{ruta: string, nombre_original: string, tamano: int}
     */
    public function guardar(UploadedFile $archivo, string $tipo, int $anio, string $numeroActa): array
    {
        $carpeta = self::CARPETA_BASE . '/' . strtolower($tipo) . '/' . $anio;

        $nombreFisico = sprintf(
            'ACTA_%s_%d_%s_%s.pdf',
            strtoupper($tipo),
            $anio,
            $this->sanear($numeroActa),
            Str::random(8)
        );

        $ruta = Storage::disk(self::DISCO)->putFileAs($carpeta, $archivo, $nombreFisico);

        if ($ruta === false) {
            throw new \RuntimeException('No se pudo almacenar el PDF en el servidor.');
        }

        return [
            'ruta' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'tamano' => $archivo->getSize(),
        ];
    }

    public function guardarContenido(string $contenido, string $tipo, int $anio, string $numeroActa): string
    {
        $carpeta = self::CARPETA_BASE . '/' . strtolower($tipo) . '/' . $anio;
        $nombreFisico = sprintf(
            'ACTA_%s_%d_%s_%s.pdf',
            strtoupper($tipo),
            $anio,
            $this->sanear($numeroActa),
            Str::random(8)
        );

        Storage::disk(self::DISCO)->put($carpeta . '/' . $nombreFisico, $contenido);

        return $carpeta . '/' . $nombreFisico;
    }

    public function eliminar(?string $ruta): void
    {
        if ($ruta && Storage::disk(self::DISCO)->exists($ruta)) {
            Storage::disk(self::DISCO)->delete($ruta);
        }
    }

    public function existe(string $ruta): bool
    {
        return Storage::disk(self::DISCO)->exists($ruta);
    }

    public function contenido(string $ruta): string
    {
        return Storage::disk(self::DISCO)->get($ruta);
    }

    public function tamano(string $ruta): int
    {
        return Storage::disk(self::DISCO)->size($ruta);
    }

    private function sanear(string $valor): string
    {
        return preg_replace('/[^A-Za-z0-9_-]/', '', $valor);
    }
}
