<?php

namespace App\Services;

use App\Models\Acta;
use App\Models\ActaDefuncion;
use App\Models\ActaMatrimonio;
use App\Models\ActaNacimiento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActaService
{
    public function __construct(
        private readonly PdfValidationService $pdfValidationService,
        private readonly PdfStorageService $pdfStorageService,
        private readonly AuditoriaService $auditoriaService,
    ) {
    }

    /**
     * Registra el acta con su PDF obligatorio y la deja REGISTRADA (bloqueada).
     * Los BORRADOR sin PDF solo provienen de la importación histórica
     * (storage/app/import-historical.php) y se completan con completarPdf().
     */
    public function registrar(array $datosActa, array $datosDetalle, ?UploadedFile $pdf, User $usuario): Acta
    {
        $tipo = $datosActa['tipo'];
        $numeroActa = $datosActa['numero_acta'];
        $anio = (int) $datosActa['anio'];

        $this->verificarDuplicadoNumero($numeroActa, $anio, $tipo, $usuario);

        if (! $pdf) {
            throw ValidationException::withMessages(['pdf' => 'Debe adjuntar el PDF del acta.']);
        }

        return $this->registrarConPdf($datosActa, $datosDetalle, $pdf, $usuario, $tipo);
    }

    /** Usado por storage/app/import-historical.php: la numeración histórica nunca llega desde el formulario. */
    public function registrarHistorico(array $datosActa, array $datosDetalle, UploadedFile $pdf, User $usuario, int $orden, int $sobre, int $correlativo): Acta
    {
        $this->verificarDuplicadoNumero($datosActa['numero_acta'], (int) $datosActa['anio'], $datosActa['tipo'], $usuario);
        $this->verificarDuplicadoSobreCorrelativo($datosActa['tipo'], (string) $sobre, $correlativo, $usuario);
        return $this->registrarConPdf($datosActa, $datosDetalle, $pdf, $usuario, $datosActa['tipo'], [$orden, $sobre, $correlativo]);
    }

    /**
     * Adjunta el PDF firmado/escaneado a un acta que estaba en BORRADOR
     * y la bloquea definitivamente (pasa a REGISTRADA).
     */
    public function completarPdf(Acta $acta, UploadedFile $pdf, User $usuario): Acta
    {
        if (! $acta->esBorrador()) {
            throw ValidationException::withMessages(['pdf' => 'Esta acta ya no está en estado borrador.']);
        }

        $resultado = $this->pdfValidationService->validar($pdf);
        if (! $resultado['valido']) {
            throw ValidationException::withMessages(['pdf' => $resultado['errores']]);
        }

        $hash = $this->pdfValidationService->calcularHash($pdf);
        if ($this->pdfValidationService->existeDuplicadoPorHash($hash)) {
            $this->auditoriaService->registrar(
                AuditoriaService::INTENTO_DUPLICADO,
                'Registro Civil',
                $acta->id,
                'Intento de adjuntar un PDF que ya se encuentra registrado.',
                $usuario->id
            );

            throw ValidationException::withMessages(['pdf' => 'El documento PDF ya fue registrado en el sistema.']);
        }

        $archivoGuardado = $this->pdfStorageService->guardar($pdf, $acta->tipo, $acta->anio, $acta->numero_acta);

        try {
            DB::transaction(function () use ($acta, $hash, $archivoGuardado, $usuario) {
                $acta->update([
                    'estado' => Acta::ESTADO_REGISTRADA,
                    'pdf_original' => $archivoGuardado['nombre_original'],
                    'pdf_ruta' => $archivoGuardado['ruta'],
                    'pdf_hash' => $hash,
                    'pdf_tamano' => $archivoGuardado['tamano'],
                ]);

                $this->auditoriaService->registrar(
                    AuditoriaService::SUBIR_PDF,
                    'Registro Civil',
                    $acta->id,
                    "PDF firmado adjuntado. Acta {$acta->tipo} N° {$acta->numero_acta}/{$acta->anio} queda REGISTRADA (bloqueada).",
                    $usuario->id
                );
            });
        } catch (\Throwable $e) {
            $this->pdfStorageService->eliminar($archivoGuardado['ruta']);
            throw $e;
        }

        return $acta->fresh(['nacimiento', 'matrimonio', 'defuncion', 'usuario']);
    }

    private function registrarConPdf(array $datosActa, array $datosDetalle, UploadedFile $pdf, User $usuario, string $tipo, ?array $numeracionHistorica = null): Acta
    {
        $resultado = $this->pdfValidationService->validar($pdf);
        if (! $resultado['valido']) {
            throw ValidationException::withMessages(['pdf' => $resultado['errores']]);
        }

        $hash = $this->pdfValidationService->calcularHash($pdf);
        if ($this->pdfValidationService->existeDuplicadoPorHash($hash)) {
            $this->auditoriaService->registrar(
                AuditoriaService::INTENTO_DUPLICADO,
                'Registro Civil',
                null,
                'Intento de subir un PDF que ya se encuentra registrado.',
                $usuario->id
            );

            throw ValidationException::withMessages(['pdf' => 'El documento PDF ya fue registrado en el sistema.']);
        }

        $archivoGuardado = $this->pdfStorageService->guardar($pdf, $tipo, (int) $datosActa['anio'], $datosActa['numero_acta']);

        try {
            $acta = DB::transaction(function () use ($datosActa, $datosDetalle, $usuario, $hash, $archivoGuardado, $tipo, $numeracionHistorica) {
                $acta = new Acta([
                    'numero_acta' => $datosActa['numero_acta'],
                    'anio' => $datosActa['anio'],
                    'tipo' => $tipo,
                    'fecha_acta' => $datosActa['fecha_acta'],
                    'observaciones' => $datosActa['observaciones'] ?? null,
                    'usuario_id' => $usuario->id,
                    'estado' => Acta::ESTADO_REGISTRADA,
                    'pdf_original' => $archivoGuardado['nombre_original'],
                    'pdf_ruta' => $archivoGuardado['ruta'],
                    'pdf_hash' => $hash,
                    'pdf_tamano' => $archivoGuardado['tamano'],
                ]);

                if ($numeracionHistorica !== null) { $acta->asignarNumeracionHistorica(...$numeracionHistorica); }
                $acta->save();

                $this->crearDetalle($acta, $tipo, $datosDetalle);

                $this->auditoriaService->registrar(
                    AuditoriaService::CREAR_ACTA,
                    'Registro Civil',
                    $acta->id,
                    "Acta {$tipo} N° {$acta->numero_acta}/{$acta->anio} registrada.",
                    $usuario->id
                );

                return $acta;
            });
        } catch (\Throwable $e) {
            $this->pdfStorageService->eliminar($archivoGuardado['ruta']);
            throw $e;
        }

        return $acta->fresh(['nacimiento', 'matrimonio', 'defuncion', 'usuario']);
    }

    private function verificarDuplicadoNumero(string $numeroActa, int $anio, string $tipo, User $usuario): void
    {
        if ($this->pdfValidationService->existeDuplicadoPorNumero($numeroActa, $anio, $tipo)) {
            $this->auditoriaService->registrar(
                AuditoriaService::INTENTO_DUPLICADO,
                'Registro Civil',
                null,
                "Intento de registrar acta duplicada: {$tipo} {$numeroActa}/{$anio}",
                $usuario->id
            );

            throw ValidationException::withMessages([
                'numero_acta' => 'Ya existe un acta registrada con ese número, año y tipo.',
            ]);
        }
    }

    private function verificarDuplicadoSobreCorrelativo(string $tipo, string $sobre, int $correlativo, User $usuario): void
    {
        $existe = Acta::where('tipo', $tipo)->where('sobre', $sobre)->where('correlativo', $correlativo)->exists();

        if ($existe) {
            $this->auditoriaService->registrar(
                AuditoriaService::INTENTO_DUPLICADO,
                'Registro Civil',
                null,
                "Intento de usar sobre {$sobre} posición {$correlativo} ya ocupada.",
                $usuario->id
            );

            throw ValidationException::withMessages([
                'correlativo' => 'Ya existe un acta registrada en ese sobre y esa posición (correlativo).',
            ]);
        }
    }

    private function crearDetalle(Acta $acta, string $tipo, array $datos): void
    {
        if ($tipo === Acta::TIPO_NACIMIENTO) {
            foreach (['madre_nombre', 'madre_dni', 'padre_nombre', 'padre_dni'] as $campo) {
                if (($datos[$campo.'_estado'] ?? 'PENDIENTE') !== 'REGISTRADO') $datos[$campo] = null;
            }
        }
        // Defunción keeps observations on the central acta, not its detail table.
        if ($tipo === Acta::TIPO_DEFUNCION) { unset($datos['observaciones']); }
        match ($tipo) {
            Acta::TIPO_NACIMIENTO => ActaNacimiento::create(array_merge($datos, ['acta_id' => $acta->id])),
            Acta::TIPO_MATRIMONIO => ActaMatrimonio::create(array_merge($datos, ['acta_id' => $acta->id])),
            Acta::TIPO_DEFUNCION => ActaDefuncion::create(array_merge($datos, ['acta_id' => $acta->id])),
            default => throw new \InvalidArgumentException("Tipo de acta inválido: {$tipo}"),
        };
    }

    public function buscar(array $filtros)
    {
        $query = Acta::with(['nacimiento', 'matrimonio', 'defuncion', 'usuario']);
        // Treat %, _ and \ typed by the user as literal characters, not LIKE wildcards.
        $like = fn ($valor) => '%' . addcslashes((string) $valor, '\\%_') . '%';

        if (! empty($filtros['numero_acta'])) {
            $query->where('numero_acta', 'like', $like($filtros['numero_acta']));
        }
        if (! empty($filtros['anio'])) {
            $query->where('anio', $filtros['anio']);
        }
        if (! empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }
        if (! empty($filtros['sobre'])) {
            $query->where('sobre', $filtros['sobre']);
        }
        if (! empty($filtros['correlativo'])) {
            $query->where('correlativo', $filtros['correlativo']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_acta', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_acta', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['dni']) || ! empty($filtros['nombre'])) {
            $dni = $filtros['dni'] ?? null;
            $nombre = $filtros['nombre'] ?? null;

            // Each detail table is searched once and only the matching act ids are kept.
            // The previous "OR EXISTS (…)" ran three correlated subqueries for every act,
            // which made the page count take ~0.8 s with 50 000 acts (AC03).
            $detalle = fn (string $tabla) => DB::table($tabla)->select('acta_id')
                ->when($dni, fn ($s) => $s->where('dni', 'like', $like($dni)))
                ->when($nombre, fn ($s) => $s->where('apellido_nombre', 'like', $like($nombre)));
            $ids = $detalle('acta_nacimientos')->union($detalle('acta_matrimonios'))->union($detalle('acta_defunciones'))->pluck('acta_id');
            $query->whereIn('id', $ids->all());
        }

        if (! empty($filtros['sobre']) && ! empty($filtros['tipo'])) {
            return $query->orderBy('correlativo')->orderBy('id')->paginate(15)->withQueryString();
        }
        return $query->orderByDesc('fecha_acta')->paginate(15)->withQueryString();
    }
}
