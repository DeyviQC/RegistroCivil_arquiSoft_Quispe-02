<?php

namespace Database\Factories;

use App\Models\Acta;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActaNacimientoFactory extends Factory
{
    public function definition(): array
    {
        $sexo = fake()->randomElement(['M', 'F']);

        return [
            'acta_id' => Acta::factory()->nacimiento(),
            'nombres' => $sexo === 'M' ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'dni' => (string) fake()->unique()->numberBetween(10000000, 79999999),
            'fecha_nacimiento' => fake()->dateTimeBetween('-5 years', 'now'),
            'hora_nacimiento' => fake()->time(),
            'lugar_nacimiento' => fake()->city(),
            'sexo' => $sexo,
            'nombre_padre' => fake()->name('male'),
            'dni_padre' => (string) fake()->numberBetween(10000000, 79999999),
            'nombre_madre' => fake()->name('female'),
            'dni_madre' => (string) fake()->numberBetween(10000000, 79999999),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
