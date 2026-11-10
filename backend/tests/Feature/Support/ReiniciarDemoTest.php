<?php

namespace Tests\Feature\Support;

use Tests\TestCase;

class ReiniciarDemoTest extends TestCase
{
    public function test_se_niega_a_correr_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('nexora:demo-reset', ['--force' => true])
            ->expectsOutputToContain('no corre con APP_ENV=production')
            ->assertFailed();
    }
}
