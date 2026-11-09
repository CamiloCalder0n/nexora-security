<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Domain\Events\IngresoRegistrado;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Arreglos de la revisión del panel: mensajes, filtros, accesibilidad y placas. */
class RevisionPanelTest extends TestCase
{
    use RefreshDatabase;

    private const AVISO_FILTRO = 'Algún filtro no era válido; se muestran todos los registros.';

    private User $admin;

    private User $vigilante;

    private Unidad $unidad;

    private Zona $zona;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([IngresoRegistrado::class]);

        $this->admin = User::factory()->conRol(Rol::Admin)->create();
        $this->vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $this->unidad = Unidad::factory()->create();
        $this->zona = Zona::factory()->create();
    }

    private function ingreso(array $extra = []): array
    {
        return [
            'tipo_ingreso' => 'visitante',
            'cantidad_personas' => 1,
            'unidad_id' => $this->unidad->id,
            'zona_id' => $this->zona->id,
            ...$extra,
        ];
    }

    public function test_ingreso_sin_visitante_ni_autorizacion_da_error_en_espanol(): void
    {
        $this->actingAs($this->vigilante)
            ->from(route('panel.porteria.ingresos.create'))
            ->post(route('panel.porteria.ingresos.store'), $this->ingreso())
            ->assertSessionHasErrors([
                'nombre_visitante' => 'Escriba el nombre del visitante o elija una autorización.',
                'documento' => 'Escriba el documento del visitante o elija una autorización.',
            ]);
    }

    public function test_ingreso_en_zona_inactiva_es_rechazado(): void
    {
        $inactiva = Zona::factory()->inactiva()->create();

        $this->actingAs($this->vigilante)
            ->post(route('panel.porteria.ingresos.store'), $this->ingreso([
                'nombre_visitante' => 'Ana', 'documento' => '123', 'zona_id' => $inactiva->id,
            ]))
            ->assertSessionHasErrors(['zona_id' => 'Elija una zona de ingreso activa.']);
    }

    public function test_filtro_de_residentes_en_json_da_422(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('panel.porteria.residentes.index', ['tipo_residente' => 'inventado']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo_residente');
    }

    public function test_filtro_invalido_de_vigentes_vuelve_al_listado_limpio_con_aviso(): void
    {
        $this->actingAs($this->vigilante);
        $listado = route('panel.porteria.autorizaciones.vigentes');

        $this->get(route('panel.porteria.autorizaciones.vigentes', ['search' => str_repeat('a', 101)]))
            ->assertRedirect($listado)
            ->assertSessionHasErrors('search');

        $this->followingRedirects()
            ->get(route('panel.porteria.autorizaciones.vigentes', ['page' => 'x']))
            ->assertOk()
            ->assertSee(self::AVISO_FILTRO);
    }

    public function test_autorizaciones_de_unidad_invalida_da_422_en_espanol(): void
    {
        $this->actingAs($this->vigilante)
            ->getJson(route('panel.porteria.ingresos.autorizaciones', ['unidad_id' => 99999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unidad_id' => 'El valor elegido en unidad no existe.']);
    }

    public function test_cambiar_activo_con_valor_invalido_es_rechazado(): void
    {
        $otro = User::factory()->conRol(Rol::Vigilante)->create();

        $this->actingAs($this->admin)
            ->patch(route('panel.identidad.usuarios.activo', $otro), ['activo' => 'quizas'])
            ->assertSessionHasErrors(['activo' => 'El campo estado debe ser verdadero o falso.']);
    }

    public function test_pagina_fuera_de_rango_ofrece_volver_a_la_primera(): void
    {
        Residente::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('panel.porteria.residentes.index', ['page' => 99]))
            ->assertOk()
            ->assertSee('No hay registros en esta página.')
            ->assertSee('Ir a la página 1')
            ->assertDontSee('No hay residentes que coincidan');
    }

    public function test_menu_marca_solo_registrar_ingreso_en_su_pantalla(): void
    {
        $html = $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.create'))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('panel.porteria.ingresos.create'), '#').'"\s+class="nav-link active"\s+aria-current="page"#',
            $html
        );
    }

    public function test_placa_de_autorizacion_se_normaliza_y_valida(): void
    {
        $residente = Residente::factory()->create();
        $residente->unidades()->attach($this->unidad, ['es_titular' => true]);
        $datos = [
            'residente_id' => $residente->id,
            'unidad_id' => $this->unidad->id,
            'visitante_nombre' => 'Carlos Ruiz',
            'visitante_documento' => '1020304050',
            'valida_desde' => now()->format('Y-m-d\TH:i'),
            'valida_hasta' => now()->addDay()->format('Y-m-d\TH:i'),
        ];
        $this->actingAs($this->admin);

        $this->post(route('panel.porteria.autorizaciones.store'), [...$datos, 'placa' => '12-ABC'])
            ->assertSessionHasErrors(['placa' => 'La placa debe tener el formato ABC123 (carro) o ABC12D (moto).']);

        $this->post(route('panel.porteria.autorizaciones.store'), [...$datos, 'placa' => 'abc 12-3'])
            ->assertSessionHasNoErrors();
        $autorizacion = Autorizacion::firstOrFail();
        $this->assertSame('ABC123', $autorizacion->placa);

        $this->put(route('panel.porteria.autorizaciones.update', $autorizacion), ['placa' => 'xyz 98d'])
            ->assertSessionHasNoErrors();
        $this->assertSame('XYZ98D', $autorizacion->fresh()->placa);

        $this->put(route('panel.porteria.autorizaciones.update', $autorizacion), ['placa' => 'MALA'])
            ->assertSessionHasErrors('placa');
    }

    public function test_formulario_de_residente_con_errores_respeta_unidades_desmarcadas(): void
    {
        $residente = Residente::factory()->create();
        $residente->unidades()->attach($this->unidad, ['es_titular' => true]);
        $editar = route('panel.porteria.residentes.edit', $residente);

        $this->actingAs($this->admin)->followingRedirects()->from($editar)
            ->put(route('panel.porteria.residentes.update', $residente), [
                'nombre' => $residente->nombre,
                'documento' => $residente->documento,
                'tipo_residente' => 'propietario',
            ])
            ->assertOk()
            ->assertDontSee('name="unidades[]" value="'.$this->unidad->id.'" checked', false);

        $this->get($editar)
            ->assertSee('name="unidades[]" value="'.$this->unidad->id.'" checked', false);
    }

    public function test_boton_eliminar_es_accesible_y_sin_clase_duplicada(): void
    {
        $residente = Residente::factory()->create();

        $html = $this->actingAs($this->admin)
            ->get(route('panel.porteria.residentes.show', $residente))
            ->assertOk()
            ->assertSee('<i class="bi bi-trash" aria-hidden="true"></i>', false)
            ->assertSee('aria-label="Eliminar"', false)
            ->getContent();

        $this->assertStringNotContainsString('btn btn-sm btn-outline-danger btn', $html);
        $this->assertStringContainsString('class="btn btn-outline-danger"', $html);
    }

    public function test_dashboard_carga_chartjs_con_integridad(): void
    {
        $this->actingAs($this->admin)
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertSee('integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4"', false)
            ->assertSee('crossorigin="anonymous"', false);
    }
}
