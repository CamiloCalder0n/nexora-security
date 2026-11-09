<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Identidad\Http\Middleware\CerrarSesionInactiva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesionInactivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_si_lo_desactivan_con_la_sesion_abierta_lo_saca_en_la_siguiente_peticion(): void
    {
        $admin = User::factory()->conRol(Rol::Admin)->create();
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();

        $this->actingAs($vigilante)->get(route('panel.inicio'))->assertRedirect(route('panel.dashboard'));

        $this->actingAs($admin)
            ->patch(route('panel.identidad.usuarios.activo', $vigilante), ['activo' => '0'])
            ->assertRedirect();

        $this->actingAs($vigilante->refresh())
            ->get(route('panel.inicio'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', CerrarSesionInactiva::MENSAJE);

        $this->assertGuest('web');
    }

    public function test_tambien_en_el_api_con_la_sesion_del_panel(): void
    {
        $usuario = User::factory()->conRol(Rol::Vigilante)->create(['activo' => false]);

        $this->actingAs($usuario)
            ->getJson(route('api.v1.identidad.yo'), ['Referer' => config('app.url').'/panel'])
            ->assertUnauthorized()
            ->assertJsonPath('message', CerrarSesionInactiva::MENSAJE);

        $this->assertGuest('web');
    }

    public function test_un_usuario_activo_sigue_normal(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Vigilante)->create())
            ->get(route('panel.dashboard'))
            ->assertOk();
    }
}
