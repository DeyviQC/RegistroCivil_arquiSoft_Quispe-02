<?php

namespace Database\Factories;

use App\Models\Acta;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActaMatrimonioFactory extends Factory
{
    public function definition(): array
    {
        $fechaMatrimonio = fake()->dateTimeBetween('-5 years', 'now');

        return [
            'acta_id' => Acta::factory()->matrimonio(),
            'c1_nombres' => fake()->firstNameMale(),
            'c1_apellido_paterno' => fake()->lastName(),
            'c1_apellido_materno' => fake()->lastName(),
            'c1_dni' => (string) fake()->unique()->numberBetween(10000000, 79999999),
            'c1_fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'c2_nombres' => fake()->firstNameFemale(),
            'c2_apellido_paterno' => fake()->lastName(),
            'c2_apellido_materno' => fake()->lastName(),
            'c2_dni' => (string) fake()->unique()->numberBetween(10000000, 79999999),
            'c2_fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'fecha_matrimonio' => $fechaMatrimonio,
            'lugar_matrimonio' => fake()->city(),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
