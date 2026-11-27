<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuraciones';

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    public static function obtener(string $clave, mixed $default = null): mixed
    {
        return Cache::remember("config.$clave", 3600, function () use ($clave, $default) {
            return static::where('clave', $clave)->value('valor') ?? $default;
        });
    }

    public static function establecer(string $clave, mixed $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        Cache::forget("config.$clave");
    }
}
