<?php

namespace Database\Seeders;

use App\Modules\Identidad\Infrastructure\Database\Seeders\UsuariosSeeder;
use App\Modules\Monitoreo\Infrastructure\Database\Seeders\ZonasSeeder;
use App\Modules\Porteria\Infrastructure\Database\Seeders\PorteriaSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UsuariosSeeder::class,
            ZonasSeeder::class,
            PorteriaSeeder::class,
        ]);
    }
}
