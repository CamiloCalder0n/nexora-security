<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_pantalla_de_login_responde_200(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión');
    }

    public function test_invitado_es_enviado_al_login(): void
    {
        $this->get(route('panel.dashboard'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect('/panel/inicio');
    }

    public function test_admin_entra_y_llega_al_dashboard(): void
    {
        User::factory()->conRol(Rol::Admin)->create(['email' => 'a@nexora.test']);

        $this->post(route('login.entrar'), ['email' => 'a@nexora.test', 'password' => 'password'])
            ->assertRedirect(route('panel.inicio'));

        $this->assertAuthenticated();
        $this->get(route('panel.inicio'))->assertRedirect(route('panel.dashboard'));
        $this->get(route('panel.dashboard'))->assertOk()->assertSee('Dashboard');
    }

    public function test_dos_roles_ven_menus_distintos(): void
    {
        $admin = User::factory()->conRol(Rol::Admin)->create();
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();

        $this->actingAs($admin)->get(route('panel.dashboard'))->assertOk()->assertSee('Rol: <strong>Admin</strong>', false);
        $this->actingAs($vigilante)->get(route('panel.dashboard'))->assertOk()->assertSee('Rol: <strong>Vigilante</strong>', false);
    }

    public function test_clave_equivocada_vuelve_con_error(): void
    {
        User::factory()->create(['email' => 'a@nexora.test']);

        $this->from(route('login'))
            ->post(route('login.entrar'), ['email' => 'a@nexora.test', 'password' => 'mala'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_inactivo_no_entra(): void
    {
        User::factory()->conRol(Rol::Admin)->create(['email' => 'i@nexora.test', 'activo' => false]);

        $this->from(route('login'))
            ->post(route('login.entrar'), ['email' => 'i@nexora.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_usuario_desactivado_con_sesion_abierta_vuelve_al_login(): void
    {
        $admin = User::factory()->conRol(Rol::Admin)->create(['activo' => false]);

        $this->actingAs($admin)->get(route('panel.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest('web');
    }

    public function test_el_agente_no_entra_al_panel(): void
    {
        User::factory()->conRol(Rol::Agente)->create(['email' => 'ag@nexora.test']);

        $this->post(route('login.entrar'), ['email' => 'ag@nexora.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_sin_datos_valida(): void
    {
        $this->post(route('login.entrar'), [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_logout_cierra_la_sesion(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_residente_no_ve_el_dashboard(): void
    {
        $this->actingAs(User::factory()->conRol(Rol::Residente)->create())
            ->get(route('panel.dashboard'))
            ->assertForbidden();
    }

    public function test_pagina_404_propia(): void
    {
        $this->get('/no-existe-esta-ruta')->assertNotFound()->assertSee('Página no encontrada');
    }

    public function test_pagina_500_propia(): void
    {
        Route::get('/_prueba-500', fn () => throw new \RuntimeException('boom'))->middleware('web');
        config(['app.debug' => false]);

        $this->get('/_prueba-500')->assertStatus(500)->assertSee('Error interno')->assertDontSee('boom');
    }
}
