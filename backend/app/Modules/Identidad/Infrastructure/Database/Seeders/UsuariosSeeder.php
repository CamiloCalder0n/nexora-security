<?php

namespace App\Modules\Identidad\Infrastructure\Database\Seeders;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    /** Solo para local: todos con contraseña "password". */
    private const USUARIOS = [
        ['admin@nexora.test', 'Marta Rueda', Rol::Admin, '3104567890'],
        ['vigilante@nexora.test', 'Óscar Peña', Rol::Vigilante, '3157894561'],
        ['consejo@nexora.test', 'Gustavo Barrera', Rol::Consejo, '3009876543'],
        ['agente@nexora.test', 'Agente portería', Rol::Agente, null],
        ['residente@nexora.test', 'Laura Méndez', Rol::Residente, '3201234567'],
        ['residente2@nexora.test', 'Andrés Castillo', Rol::Residente, '3112223344'],
        ['residente3@nexora.test', 'Paula Herrera', Rol::Residente, '3185556677'],
    ];

    public function run(): void
    {
        $password = Hash::make('password');

        foreach (self::USUARIOS as [$email, $nombre, $rol, $telefono]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $nombre,
                    'password' => $password,
                    'rol' => $rol,
                    'activo' => true,
                    'telefono' => $telefono,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
