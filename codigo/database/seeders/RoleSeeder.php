<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(
            ['slug' => Role::ADMINISTRADOR],
            [
                'nombre' => 'Administrador',
                'descripcion' => 'Acceso total: usuarios, auditoría, configuración, actas e importaciones.',
            ]
        );

        Role::updateOrCreate(
            ['slug' => Role::ASISTENTE],
            [
                'nombre' => 'Asistente',
                'descripcion' => 'Registra y consulta actas. Sin acceso a usuarios ni configuración.',
            ]
        );
    }
}
