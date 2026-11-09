<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuariosPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->conRol(Rol::Admin)->create(['name' => 'Ana Admin']);
    }

    public function test_admin_ve_el_listado_con_totales_por_rol(): void
    {
        User::factory()->conRol(Rol::Vigilante)->create(['name' => 'Victor Vigilante', 'telefono' => '3001234567']);

        $this->actingAs($this->admin())
            ->get(route('panel.identidad.usuarios.index'))
            ->assertOk()
            ->assertSee('Victor Vigilante')
            ->assertSee('3001234567')
            ->assertSee('Vigilante')
            ->assertViewHas('totales', fn (array $t) => $t['admin'] === 1 && $t['vigilante'] === 1 && $t['agente'] === 0);
    }

    public function test_consejo_y_vigilante_reciben_403(): void
    {
        foreach ([Rol::Consejo, Rol::Vigilante] as $rol) {
            $this->actingAs(User::factory()->conRol($rol)->create())
                ->get(route('panel.identidad.usuarios.index'))
                ->assertForbidden();

            $otro = User::factory()->create();
            $this->patch(route('panel.identidad.usuarios.activo', $otro), ['activo' => 0])->assertForbidden();
            $this->assertTrue($otro->fresh()->activo);
        }
    }

    public function test_admin_desactiva_y_activa_una_cuenta(): void
    {
        $admin = $this->admin();
        $residente = User::factory()->create();
        $index = route('panel.identidad.usuarios.index');

        $this->actingAs($admin)->from($index)
            ->patch(route('panel.identidad.usuarios.activo', $residente), ['activo' => 0])
            ->assertRedirect($index)
            ->assertSessionHas('exito');
        $this->assertFalse($residente->fresh()->activo);

        $this->from($index)
            ->patch(route('panel.identidad.usuarios.activo', $residente), ['activo' => 1])
            ->assertRedirect($index)
            ->assertSessionHas('exito');
        $this->assertTrue($residente->fresh()->activo);
    }

    public function test_reenviar_el_formulario_no_invierte_el_cambio(): void
    {
        $admin = $this->admin();
        $residente = User::factory()->create();

        $this->actingAs($admin)->patch(route('panel.identidad.usuarios.activo', $residente), ['activo' => 0]);
        $this->patch(route('panel.identidad.usuarios.activo', $residente), ['activo' => 0]);

        $this->assertFalse($residente->fresh()->activo);
    }

    public function test_cambio_sin_estado_deseado_es_rechazado(): void
    {
        $residente = User::factory()->create();

        $this->actingAs($this->admin())
            ->patch(route('panel.identidad.usuarios.activo', $residente))
            ->assertSessionHasErrors('activo');
        $this->assertTrue($residente->fresh()->activo);
    }

    public function test_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->admin();
        $index = route('panel.identidad.usuarios.index');

        $this->actingAs($admin)->from($index)
            ->patch(route('panel.identidad.usuarios.activo', $admin), ['activo' => 0])
            ->assertRedirect($index)
            ->assertSessionHas('error', 'No puede desactivar su propia cuenta.');

        $this->assertTrue($admin->fresh()->activo);
    }

    public function test_desactivar_revoca_los_tokens_de_api(): void
    {
        $residente = User::factory()->create();
        $residente->createToken('celular');
        $residente->createToken('tablet');
        $this->assertSame(2, $residente->tokens()->count());

        $this->actingAs($this->admin())
            ->patch(route('panel.identidad.usuarios.activo', $residente), ['activo' => 0]);

        $this->assertSame(0, $residente->tokens()->count());
    }

    public function test_filtro_invalido_redirige_al_listado_limpio_con_error(): void
    {
        $this->actingAs($this->admin())
            ->get(route('panel.identidad.usuarios.index', ['rol' => 'jefe', 'activo' => '7']))
            ->assertRedirect(route('panel.identidad.usuarios.index'))
            ->assertSessionHasErrors(['rol', 'activo']);

        $this->get(route('panel.identidad.usuarios.index', ['search' => str_repeat('a', 101)]))
            ->assertRedirect(route('panel.identidad.usuarios.index'))
            ->assertSessionHasErrors('search');

        $this->get(route('panel.identidad.usuarios.index'))->assertOk();
    }

    public function test_busqueda_y_filtros(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Rosa Residente', 'email' => 'rosa@nexora.test']);
        User::factory()->create(['name' => 'Pedro Inactivo', 'activo' => false]);
        User::factory()->conRol(Rol::Vigilante)->create(['name' => 'Victor Vigilante']);

        $this->actingAs($admin)
            ->get(route('panel.identidad.usuarios.index', ['search' => 'ROSA@nexora']))
            ->assertOk()
            ->assertSee('Rosa Residente')
            ->assertDontSee('Victor Vigilante');

        $this->get(route('panel.identidad.usuarios.index', ['rol' => 'vigilante']))
            ->assertOk()
            ->assertSee('Victor Vigilante')
            ->assertDontSee('Rosa Residente');

        $this->get(route('panel.identidad.usuarios.index', ['activo' => '0']))
            ->assertOk()
            ->assertSee('Pedro Inactivo')
            ->assertDontSee('Rosa Residente');
    }
}
