<?php

namespace App\Http\Controllers;

use App\Models\Acta;
use App\Services\AuditoriaService;
use App\Services\PdfStorageService;
use Illuminate\Http\Response;

class PdfController extends Controller
{
    public function __construct(
        private readonly PdfStorageService $pdfStorageService,
        private readonly AuditoriaService $auditoriaService,
    ) {
    }

    /**
     * GET /actas/{acta}/pdf
     * Entrega el PDF de forma segura: verifica autenticación (middleware auth),
     * autorización (Policy), existencia del archivo y registra la consulta
     * en auditoría, tal como exige la Sección 14.
     */
    public function ver(Acta $acta): Response
    {
        $this->authorize('view', $acta);

        if (! $acta->pdf_ruta || ! $this->pdfStorageService->existe($acta->pdf_ruta)) {
            abort(404, 'El documento PDF no se encuentra disponible en el servidor.');
        }

        $this->auditoriaService->registrar(
            AuditoriaService::VER_PDF,
            'Registro Civil',
            $acta->id,
            "Visualización del PDF del acta {$acta->tipo} N° {$acta->numero_acta}/{$acta->anio}."
        );

        $contenido = $this->pdfStorageService->contenido($acta->pdf_ruta);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            // makeDisposition escapes quotes and adds a UTF-8 filename* for names with accents.
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition(
                'inline',
                $acta->pdf_original ?: 'acta.pdf',
                \Illuminate\Support\Str::ascii($acta->pdf_original ?: 'acta.pdf') ?: 'acta.pdf'
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** GET /actas/{acta}/pdf-firmado: the act's latest digitally signed copy (to print or resend). */
    public function firmado(Acta $acta)
    {
        $this->authorize('view', $acta);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($acta->firmado_ruta && $disk->exists($acta->firmado_ruta)
            && hash_equals((string) $acta->firmado_sha256, hash_file('sha256', $disk->path($acta->firmado_ruta))), 404, 'No hay copia firmada disponible.');

        $this->auditoriaService->registrar(AuditoriaService::VER_PDF, 'Registro Civil', $acta->id,
            "Visualización de la copia firmada del acta {$acta->tipo} N° {$acta->numero_acta}/{$acta->anio}.");
        $nombre = 'acta-'.strtolower($acta->tipo).'-'.$acta->numero_acta.'-'.$acta->anio.'-firmada.pdf';

        return response()->file($disk->path($acta->firmado_ruta), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition(request()->boolean('descargar') ? 'attachment' : 'inline', \Illuminate\Support\Str::ascii($nombre)),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function vista(Acta $acta)
    {
        $this->authorize('view', $acta);

        return view('pdf.viewer', compact('acta'));
    }
}
