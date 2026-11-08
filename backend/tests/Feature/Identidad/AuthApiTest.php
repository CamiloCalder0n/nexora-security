<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_correcto_devuelve_token_200(): void
    {
        $usuario = User::factory()->conRol(Rol::Vigilante)->create(['email' => 'v@nexora.test']);

        $this->postJson(route('api.v1.identidad.login'), ['email' => 'v@nexora.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'nombre', 'rol']])
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonPath('usuario.rol', 'vigilante');
    }

    public function test_login_con_clave_equivocada_es_401(): void
    {
        User::factory()->create(['email' => 'v@nexora.test']);

        $this->postJson(route('api.v1.identidad.login'), ['email' => 'v@nexora.test', 'password' => 'otra'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales inválidas');
    }

    public function test_login_sin_datos_es_422(): void
    {
        $this->postJson(route('api.v1.identidad.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_usuario_inactivo_no_obtiene_token(): void
    {
        User::factory()->create(['email' => 'i@nexora.test', 'activo' => false]);

        $this->postJson(route('api.v1.identidad.login'), ['email' => 'i@nexora.test', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales inválidas');
    }

    public function test_token_de_usuario_desactivado_recibe_403(): void
    {
        $usuario = User::factory()->conRol(Rol::Admin)->create();
        $token = $usuario->createToken('t')->plainTextToken;
        $usuario->update(['activo' => false]);

        $this->withToken($token)->getJson(route('api.v1.monitoreo.zonas.index'))->assertForbidden();
    }

    public function test_logout_revoca_el_token(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withToken($token)->postJson(route('api.v1.identidad.logout'))->assertOk();

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_logout_sin_token_es_401(): void
    {
        $this->postJson(route('api.v1.identidad.logout'))->assertUnauthorized();
    }

    public function test_login_del_agente_cumple_el_contrato(): void
    {
        User::factory()->conRol(Rol::Agente)->create(['email' => 'agente@nexora.test', 'name' => 'Agente portería']);

        $respuesta = $this->postJson(route('api.v1.identidad.agente.login'), [
            'email' => 'agente@nexora.test',
            'password' => 'password',
            'device_name' => 'agente-portatil-1',
        ])->assertOk()
            ->assertExactJsonStructure(['token', 'usuario' => ['id', 'nombre', 'rol']])
            ->assertJsonPath('usuario.nombre', 'Agente portería')
            ->assertJsonPath('usuario.rol', 'agente');

        $token = PersonalAccessToken::findToken($respuesta->json('token'));
        $this->assertSame('agente-portatil-1', $token->name);
        $this->assertSame(['agente'], $token->abilities);
    }

    public function test_login_del_agente_rechaza_otros_roles_con_401(): void
    {
        User::factory()->conRol(Rol::Admin)->create(['email' => 'a@nexora.test']);

        $this->postJson(route('api.v1.identidad.agente.login'), ['email' => 'a@nexora.test', 'password' => 'password'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Credenciales inválidas');
    }

    public function test_login_del_agente_sin_email_es_422(): void
    {
        $this->postJson(route('api.v1.identidad.agente.login'), ['password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
