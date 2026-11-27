<?php

namespace App\Services;

use App\Models\Acta;
use Illuminate\Http\UploadedFile;

class PdfValidationService
{
    private const EXTENSIONES_PERMITIDAS = ['pdf'];
    private const MIME_PERMITIDOS = ['application/pdf'];

    public function validar(UploadedFile $archivo): array
    {
        $errores = [];

        $extension = strtolower($archivo->getClientOriginalExtension());
        if (! in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            $errores[] = 'La extensión del archivo no es válida. Solo se permite .pdf';
        }

        if (! in_array($archivo->getMimeType(), self::MIME_PERMITIDOS, true)) {
            $errores[] = 'El tipo de archivo no corresponde a un PDF válido.';
        }

        $maxKb = (int) config('actas.pdf_max_kb', 10240);
        if ($archivo->getSize() > $maxKb * 1024) {
            $errores[] = "El archivo supera el tamaño máximo permitido ({$maxKb} KB).";
        }

        if (! $this->esPdfValido($archivo)) {
            $errores[] = 'El archivo está dañado o no es un PDF real.';
        }

        return [
            'valido' => count($errores) === 0,
            'errores' => $errores,
        ];
    }

    public function esPdfValido(UploadedFile $archivo): bool
    {
        $handle = fopen($archivo->getRealPath(), 'rb');
        if ($handle === false) {
            return false;
        }

        $cabecera = fread($handle, 5);
        fclose($handle);

        return $cabecera === '%PDF-';
    }

    public function calcularHash(UploadedFile $archivo): string
    {
        return hash_file('sha256', $archivo->getRealPath());
    }

    public function existeDuplicadoPorHash(string $hash): bool
    {
        return Acta::where('pdf_hash', $hash)->exists();
    }

    public function existeDuplicadoPorNumero(string $numeroActa, int $anio, string $tipo): bool
    {
        return Acta::where('numero_acta', $numeroActa)
            ->where('anio', $anio)
            ->where('tipo', $tipo)
            ->exists();
    }
}
