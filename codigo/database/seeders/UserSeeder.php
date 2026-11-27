<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Crea los usuarios de prueba del sistema.
     *
     * IMPORTANTE: estas credenciales son solo para entorno de
     * desarrollo/demo. Deben cambiarse antes de pasar a producción.
     */
    public function run(): void
    {
        $administrador = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador del Sistema',
                'email' => 'admin@municipalidad.local',
                'password' => Hash::make('Admin12345!'),
                'is_active' => true,
            ]
        );

        $asistente = User::updateOrCreate(
            ['username' => 'asistente'],
            [
                'name' => 'Asistente de Registro Civil',
                'email' => 'asistente@municipalidad.local',
                'password' => Hash::make('Asistente123!'),
                'is_active' => true,
            ]
        );

        $rolAdmin = Role::where('slug', Role::ADMINISTRADOR)->first();
        $rolAsistente = Role::where('slug', Role::ASISTENTE)->first();

        $administrador->roles()->syncWithoutDetaching([$rolAdmin->id]);
        $asistente->roles()->syncWithoutDetaching([$rolAsistente->id]);
    }
}
