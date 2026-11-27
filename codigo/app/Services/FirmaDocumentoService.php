<?php

namespace App\Services;

use App\Models\TramitePublico;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class FirmaDocumentoService
{
    public function prepararOriginal(TramitePublico $tramite): array
    {
        $disk = Storage::disk('local');
        $original = $tramite->acta?->pdf_ruta;
        if (! $original || ! $disk->exists($original)) {
            throw ValidationException::withMessages(['firma' => 'El PDF original no está disponible.']);
        }
        // Reuse the immutable archive file. Downloads and unsigned requests need no extra PDF.
        return ['preparado_ruta' => $original, 'preparado_sha256' => hash_file('sha256', $disk->path($original)), 'modo' => 'ORIGINAL'];
    }

    public function preparar(TramitePublico $tramite, int $pagina, string $posicion): array
    {
        $disk = Storage::disk('local');
        if (! $tramite->acta?->pdf_ruta || ! $disk->exists($tramite->acta->pdf_ruta)) {
            throw ValidationException::withMessages(['firma' => 'El PDF original no está disponible.']);
        }
        $ruta = 'private/entregas/'.$tramite->id.'/'.Str::uuid().'-preparado.pdf';
        $disk->makeDirectory(dirname($ruta));
        $process = new Process([config('firma.python'), base_path('scripts/preparar_firma.py'),
            $disk->path($tramite->acta->pdf_ruta), $disk->path($ruta), public_path('img/logo.jpg'),
            json_encode(['solicitud' => $tramite->id, 'tipo' => $tramite->acta->tipo,
                'numero' => $tramite->acta->numero_acta, 'anio' => $tramite->acta->anio,
                'fecha' => now()->format('d/m/Y H:i'), 'pagina' => $pagina, 'posicion' => $posicion], JSON_UNESCAPED_UNICODE)]);
        $process->setTimeout(60);
        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            $disk->delete($ruta);
            throw ValidationException::withMessages(['firma' => 'No se pudo preparar el PDF. Revisa la configuración de Python, pypdf y reportlab.']);
        }
        return ['preparado_ruta' => $ruta, 'preparado_sha256' => hash_file('sha256', $disk->path($ruta)), 'pagina' => $pagina, 'posicion' => $posicion];
    }

    /** Verify CMS integrity only. Trust, revocation and institutional authority require review. */
    public function verificar(string $pdf, string $preparado): array
    {
        if (! str_starts_with($pdf, $preparado)) {
            throw ValidationException::withMessages(['archivo' => 'Firma el PDF preparado por esta solicitud, sin editarlo ni imprimirlo a PDF.']);
        }
        preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $matches, PREG_SET_ORDER);
        $match = end($matches);
        if (! $match) $this->invalida();
        [$start, $length, $offset, $endLength] = array_map('intval', array_slice($match, 1));
        if ($start !== 0 || $length < strlen($preparado) || $offset <= $length || $offset + $endLength !== strlen($pdf)) $this->invalida();
        $gap = substr($pdf, $length, $offset - $length);
        if (! preg_match('/^<([a-fA-F0-9\s]+)>$/D', trim($gap), $hex)) $this->invalida();
        $der = hex2bin(preg_replace('/\s/', '', $hex[1]));
        if ($der === false) $this->invalida();
        $paths = [];
        try {
            foreach (['data', 'sig', 'cert'] as $key) $paths[$key] = tempnam(sys_get_temp_dir(), 'firma-');
            file_put_contents($paths['data'], substr($pdf, 0, $length).substr($pdf, $offset, $endLength));
            file_put_contents($paths['sig'], $der);
            $ok = @openssl_cms_verify($paths['data'], OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | OPENSSL_CMS_NOVERIFY,
                $paths['cert'], [], null, null, null, $paths['sig'], OPENSSL_ENCODING_DER);
            if (! $ok) $this->invalida();
            $certificate = openssl_x509_read(file_get_contents($paths['cert']));
            if (! $certificate) $this->invalida();
            $details = openssl_x509_parse($certificate);
            if ($details['validFrom_time_t'] > now()->timestamp || $details['validTo_time_t'] < now()->timestamp) $this->invalida();
            return ['firmante' => $details['subject']['CN'] ?? 'Consultar certificado',
                'certificado_sha256' => openssl_x509_fingerprint($certificate, 'sha256'),
                'integridad' => 'VERIFICADA', 'confianza' => 'PENDIENTE_REVISION'];
        } finally {
            foreach ($paths as $path) if (is_file($path)) unlink($path);
        }
    }

    private function invalida(): never
    {
        throw ValidationException::withMessages(['archivo' => 'No se pudo verificar la firma digital o el PDF tiene modificaciones posteriores.']);
    }
}
