<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditoriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_id' => User::factory(),
            'accion' => fake()->randomElement(['LOGIN', 'CREAR_ACTA', 'VER_PDF', 'CONSULTAR_ACTA']),
            'modulo' => 'Registro Civil',
            'registro_id' => fake()->numberBetween(1, 100),
            'descripcion' => fake()->sentence(),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
