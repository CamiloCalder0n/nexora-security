<?php

namespace Database\Seeders;

use App\Modules\Identidad\Infrastructure\Database\Seeders\UsuariosSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UsuariosSeeder::class,
        ]);
    }
}
