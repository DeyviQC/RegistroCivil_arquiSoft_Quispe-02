<?php

namespace App\Contracts;

/**
 * Compares the DNI data a citizen declares against an external identity source.
 * The portal depends on this contract, not on a provider: APIsPerú today, a direct
 * RENIEC connector in the future (RC06) only needs a new implementation and a binding.
 * A match never proves identity or kinship by itself; staff still review the request.
 */
interface ContrastadorIdentidad
{
    public const DATOS_CONTRASTADOS = 'DATOS_CONTRASTADOS';
    public const NO_COINCIDE = 'NO_COINCIDE';
    public const SIN_RESULTADOS = 'SIN_RESULTADOS';
    public const PENDIENTE_CONFIGURACION = 'PENDIENTE_CONFIGURACION';

    /**
     * @param  array{dni: string, nombre: string, verificador: string}  $datos
     * @return self::DATOS_CONTRASTADOS|self::NO_COINCIDE|self::SIN_RESULTADOS|self::PENDIENTE_CONFIGURACION
     *
     * @throws \RuntimeException when the source is unavailable (the caller must not charge).
     */
    public function contrastar(array $datos): string;
}
