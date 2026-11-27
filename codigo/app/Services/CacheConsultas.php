<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache-aside for the aggregate queries the staff panel repeats on every visit.
 * Each key is forgotten where its data changes (Acta model events, TramitePublico::transicionar());
 * the TTL is only a safety net for bulk loads that skip those hooks.
 * Forgetting waits for the commit, so a concurrent request cannot re-cache uncommitted data.
 */
final class CacheConsultas
{
    public const PANEL = 'consultas:panel';
    public const BANDEJA = 'consultas:bandeja-resumen';

    /** Safety-net lifetimes in seconds. */
    private const TTL = [self::PANEL => 600, self::BANDEJA => 300];

    public static function recordar(string $clave, Closure $consulta): mixed
    {
        return Cache::remember($clave, self::TTL[$clave], $consulta);
    }

    public static function olvidar(string ...$claves): void
    {
        DB::afterCommit(function () use ($claves) {
            foreach ($claves as $clave) {
                Cache::forget($clave);
            }
        });
    }
}
