<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'rol:admin,consejo'])
            ->get('/api/_prueba/rol', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', 'rol:admin'])
            ->get('/_prueba/rol', fn () => 'ok');
    }

    public function test_sin_token_responde_401(): void
    {
        $this->getJson('/api/_prueba/rol')->assertUnauthorized();
    }

    public function test_sin_sesion_responde_401(): void
    {
        $this->get('/_prueba/rol')->assertUnauthorized();
    }

    public function test_rol_equivocado_responde_403(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();

        $this->actingAs($vigilante, 'sanctum')
            ->getJson('/api/_prueba/rol')
            ->assertForbidden();
    }

    public function test_rol_correcto_responde_200(): void
    {
        $consejo = User::factory()->conRol(Rol::Consejo)->create();

        $this->actingAs($consejo, 'sanctum')
            ->getJson('/api/_prueba/rol')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_rol_correcto_con_sesion_web_responde_200(): void
    {
        $admin = User::factory()->conRol(Rol::Admin)->create();

        $this->actingAs($admin)->get('/_prueba/rol')->assertOk();
    }

    public function test_seeder_crea_un_usuario_por_rol(): void
    {
        $this->seed();

        foreach (Rol::cases() as $rol) {
            $this->assertDatabaseHas('users', ['email' => "{$rol->value}@nexora.test", 'rol' => $rol->value]);
        }
    }
}
