<?php

namespace Database\Factories;

use App\Models\Acta;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActaDefuncionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'acta_id' => Acta::factory()->defuncion(),
            'nombres' => fake()->firstName(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'dni' => (string) fake()->unique()->numberBetween(10000000, 79999999),
            'fecha_nacimiento' => fake()->dateTimeBetween('-90 years', '-20 years'),
            'fecha_defuncion' => fake()->dateTimeBetween('-5 years', 'now'),
            'lugar_defuncion' => fake()->city(),
            'causa_defuncion' => fake()->randomElement([
                'Paro cardiorrespiratorio', 'Enfermedad cerebrovascular', 'Neumonía',
                'Insuficiencia renal crónica', 'Accidente de tránsito',
            ]),
            'medico_certifica' => 'Dr(a). ' . fake()->lastName(),
            'observaciones' => fake()->optional()->sentence(),
        ];
    }
}
