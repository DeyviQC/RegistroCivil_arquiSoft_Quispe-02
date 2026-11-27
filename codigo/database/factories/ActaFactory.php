<?php

namespace Database\Factories;

use App\Models\Acta;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActaFactory extends Factory
{
    protected $model = Acta::class;

    public function definition(): array
    {
        return [
            'numero_acta' => str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'anio' => fake()->numberBetween(2020, 2026),
            'tipo' => Acta::TIPO_NACIMIENTO,
            'fecha_acta' => fake()->dateTimeBetween('-5 years', 'now'),
            'observaciones' => fake()->optional()->sentence(),
            'usuario_id' => User::factory(),
            'estado' => Acta::ESTADO_REGISTRADA,
            'pdf_original' => 'documento_prueba.pdf',
            'pdf_ruta' => 'private/actas/pendiente.pdf',
            'pdf_hash' => hash('sha256', fake()->unique()->uuid()),
            'pdf_tamano' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }

    public function nacimiento(): static
    {
        return $this->state(fn () => ['tipo' => Acta::TIPO_NACIMIENTO]);
    }

    public function matrimonio(): static
    {
        return $this->state(fn () => ['tipo' => Acta::TIPO_MATRIMONIO]);
    }

    public function defuncion(): static
    {
        return $this->state(fn () => ['tipo' => Acta::TIPO_DEFUNCION]);
    }
}
