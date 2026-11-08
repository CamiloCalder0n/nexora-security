<?php

namespace App\Modules\Identidad\Infrastructure\Database\Seeders;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $nombres = [
            'admin' => 'Administradora',
            'vigilante' => 'Vigilante de turno',
            'residente' => 'Residente de prueba',
            'consejo' => 'Consejo de administración',
            'agente' => 'Agente portería',
        ];

        foreach (Rol::cases() as $rol) {
            User::updateOrCreate(
                ['email' => "{$rol->value}@nexora.test"],
                [
                    'name' => $nombres[$rol->value],
                    'password' => Hash::make('password'),
                    'rol' => $rol,
                    'activo' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
