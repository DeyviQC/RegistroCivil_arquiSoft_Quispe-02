<?php

namespace App\Services;

use App\Exceptions\ConflictoVersionException;
use App\Models\TramitePublico;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Decisión del personal sobre una solicitud pagada (aprobar, observar o rechazar).
 * Única fuente de la regla para el panel web y la API REST: bloqueo de fila y control
 * de versión, transiciones permitidas, revisión humana del vínculo, evento y auditoría.
 */
class DecisionTramiteService
{
    /** Mensaje para el ciudadano cuando el personal no escribe uno. */
    public const MENSAJES = [
        'APROBADA' => 'Tu solicitud fue aprobada. Estamos preparando tu acta firmada.',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly CruceParentescoService $cruce,
    ) {
    }

    /**
     * @throws ConflictoVersionException si la solicitud cambió desde que se abrió
     * @throws ValidationException si la transición o la revisión del vínculo no son válidas
     */
    public function decidir(TramitePublico $tramite, array $datos, User $usuario): TramitePublico
    {
        $mensaje = trim((string) ($datos['mensaje'] ?? '')) ?: (self::MENSAJES[$datos['estado']] ?? 'Tu solicitud fue actualizada.');

        return DB::transaction(function () use ($tramite, $datos, $usuario, $mensaje) {
            $actual = TramitePublico::lockForUpdate()->findOrFail($tramite->id);
            if ($actual->version !== (int) $datos['version']) {
                throw new ConflictoVersionException();
            }
            if ($actual->pago?->estado !== 'PAGADO' || ! in_array($datos['estado'], TramitePublico::TRANSICIONES[$actual->estado] ?? [], true)) {
                throw ValidationException::withMessages(['estado' => 'Cambio de estado no permitido.']);
            }
            if ($datos['estado'] === 'APROBADA' && $actual->requiereRevisionVinculo()) {
                $revision = Validator::make($datos, [
                    'vinculo_revisado' => ['accepted'],
                    'sustento_revision' => ['required', 'string', 'min:10', 'max:1500'],
                ])->validate();
                $actual->datos = array_merge($actual->datos, ['revision_vinculo' => [
                    'usuario_id' => $usuario->id,
                    'revisado_at' => now()->toIso8601String(),
                    'sustento' => $revision['sustento_revision'],
                    'cruce_parentesco' => $this->cruce->comparar($actual->acta, $actual->datos),
                ]]);
            }
            $actual->transicionar($datos['estado'], $mensaje, $usuario->id);
            $this->auditoria->registrar('REVISAR_TRAMITE', 'Portal ciudadano', $actual->id, 'Estado: '.$datos['estado']);

            return $actual;
        });
    }
}