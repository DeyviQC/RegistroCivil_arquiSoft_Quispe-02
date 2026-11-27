<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Single rule to compare person names typed in different orders, cases, accents or punctuation. */
final class NombrePersona
{
    /** "Pérez Díaz, Ana" and "ANA PEREZ DIAZ" both become "ANA DIAZ PEREZ". */
    public static function normalizar(?string $nombre): string
    {
        $partes = preg_split('/[^A-Z0-9]+/', strtoupper(Str::ascii((string) $nombre)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        sort($partes);
        return implode(' ', $partes);
    }

    public static function coinciden(?string $a, ?string $b): bool
    {
        $a = self::normalizar($a);
        return $a !== '' && $a === self::normalizar($b);
    }
}
