<?php

namespace Tests\Feature\Monitoreo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class SaludTest extends TestCase
{
    use LimpiaMongo, RefreshDatabase;

    public function test_up_responde_200_con_las_tres_bases_arriba(): void
    {
        $this->getJson('/up')->assertOk()->assertJson(['status' => 'up']);
    }

    public function test_si_mongo_no_responde_up_da_500_y_el_log_dice_cual(): void
    {
        config([
            'app.debug' => false,
            'database.connections.mongodb.dsn' => 'mongodb://usuario:clave-secreta@127.0.0.1:1',
            'database.connections.mongodb.options' => ['serverSelectionTimeoutMS' => 300, 'connectTimeoutMS' => 300],
        ]);
        DB::purge('mongodb');
        Log::spy();

        $respuesta = $this->get('/up');

        $respuesta->assertStatus(500)->assertDontSee('clave-secreta');
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($mensaje) => $mensaje === 'MongoDB no responde.');
    }

    public function test_si_redis_no_responde_up_da_500(): void
    {
        config([
            'app.debug' => false,
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,  // puerto cerrado: falla al instante
        ]);
        app('redis')->purge('default');
        Log::spy();

        $this->get('/up')->assertStatus(500);
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($mensaje) => $mensaje === 'Redis no responde.');
    }
}
