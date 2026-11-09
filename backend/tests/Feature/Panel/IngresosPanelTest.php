<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Events\IngresoRegistrado;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IngresosPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $vigilante;

    private Unidad $unidad;

    private Zona $zona;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([IngresoRegistrado::class]);

        $this->vigilante = User::factory()->conRol(Rol::Vigilante)->create();
        $this->unidad = Unidad::factory()->create();
        $this->zona = Zona::factory()->create(['nombre' => 'Portería principal']);
    }

    /** El factory puede dejarla vencida (resta hasta 12 h y suma desde 4 h): se fija la ventana. */
    private function vigente(array $atributos = []): Autorizacion
    {
        return Autorizacion::factory()->create([
            'unidad_id' => $this->unidad->id,
            'valida_desde' => now()->subHour(),
            'valida_hasta' => now()->addHours(6),
            ...$atributos,
        ]);
    }

    private function datos(array $extra = []): array
    {
        return [
            'tipo_ingreso' => 'visitante',
            'nombre_visitante' => 'Laura Gómez',
            'documento' => '1020304050',
            'cantidad_personas' => 2,
            'unidad_id' => $this->unidad->id,
            'zona_id' => $this->zona->id,
            ...$extra,
        ];
    }

    public function test_el_vigilante_ve_el_formulario(): void
    {
        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.create'))
            ->assertOk()
            ->assertSee('Registrar ingreso')
            ->assertSee('Portería principal')
            ->assertSee($this->unidad->etiqueta);
    }

    public function test_el_residente_no_puede_registrar_ingresos(): void
    {
        $residente = User::factory()->conRol(Rol::Residente)->create();

        $this->actingAs($residente)->get(route('panel.porteria.ingresos.create'))->assertForbidden();
        $this->actingAs($residente)->post(route('panel.porteria.ingresos.store'), $this->datos())->assertForbidden();
        $this->actingAs($residente)->get(route('panel.porteria.ingresos.index'))->assertForbidden();
        $this->assertDatabaseCount('registros_porteria', 0);
    }

    public function test_registra_un_ingreso_simple(): void
    {
        $this->actingAs($this->vigilante)
            ->post(route('panel.porteria.ingresos.store'), $this->datos())
            ->assertRedirect(route('panel.porteria.ingresos.create'))
            ->assertSessionHas('exito');

        $this->assertDatabaseHas('registros_porteria', [
            'nombre_visitante' => 'Laura Gómez',
            'tipo_ingreso' => 'visitante',
            'cantidad_personas' => 2,
            'unidad_id' => $this->unidad->id,
            'zona_id' => $this->zona->id,
            'registrado_por' => $this->vigilante->id,
            'placa' => null,
        ]);
        Event::assertDispatched(IngresoRegistrado::class);
    }

    public function test_ingreso_vehicular_exige_placa(): void
    {
        $this->actingAs($this->vigilante)
            ->from(route('panel.porteria.ingresos.create'))
            ->post(route('panel.porteria.ingresos.store'), $this->datos(['tipo_ingreso' => 'vehiculo']))
            ->assertRedirect(route('panel.porteria.ingresos.create'))
            ->assertSessionHasErrors('placa');

        $this->assertDatabaseCount('registros_porteria', 0);
    }

    public function test_ingreso_vehicular_normaliza_la_placa_y_valida_formato(): void
    {
        $this->actingAs($this->vigilante)
            ->post(route('panel.porteria.ingresos.store'), $this->datos(['tipo_ingreso' => 'vehiculo', 'placa' => 'abc-123']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('registros_porteria', ['tipo_ingreso' => 'vehiculo', 'placa' => 'ABC123']);

        $this->actingAs($this->vigilante)
            ->post(route('panel.porteria.ingresos.store'), $this->datos(['tipo_ingreso' => 'vehiculo', 'placa' => '12ABCD']))
            ->assertSessionHasErrors('placa');
    }

    public function test_valida_cantidad_unidad_y_zona(): void
    {
        $this->actingAs($this->vigilante)
            ->post(route('panel.porteria.ingresos.store'), $this->datos(['cantidad_personas' => 21, 'unidad_id' => 999, 'zona_id' => 999]))
            ->assertSessionHasErrors(['cantidad_personas', 'unidad_id', 'zona_id']);
    }

    public function test_con_autorizacion_la_deja_usada(): void
    {
        $autorizacion = $this->vigente([
            'visitante_nombre' => 'Pedro Pérez',
            'visitante_documento' => '99887766',
            'placa' => null,
        ]);

        $this->actingAs($this->vigilante)
            ->post(route('panel.porteria.ingresos.store'), $this->datos([
                'autorizacion_id' => $autorizacion->id,
                'nombre_visitante' => null,
                'documento' => null,
            ]))
            ->assertRedirect(route('panel.porteria.ingresos.create'))
            ->assertSessionHas('exito');

        $this->assertSame(EstadoAutorizacion::Usada, $autorizacion->fresh()->estado);
        $this->assertDatabaseHas('registros_porteria', [
            'autorizacion_id' => $autorizacion->id,
            'nombre_visitante' => 'Pedro Pérez',
            'documento' => '99887766',
        ]);
    }

    public function test_autorizacion_ya_usada_vuelve_con_error_y_datos(): void
    {
        $autorizacion = Autorizacion::factory()->usada()->create(['unidad_id' => $this->unidad->id]);

        $this->actingAs($this->vigilante)
            ->from(route('panel.porteria.ingresos.create'))
            ->post(route('panel.porteria.ingresos.store'), $this->datos(['autorizacion_id' => $autorizacion->id]))
            ->assertRedirect(route('panel.porteria.ingresos.create'))
            ->assertSessionHasErrors('regla')
            ->assertSessionHasInput('nombre_visitante', 'Laura Gómez');

        $this->assertDatabaseCount('registros_porteria', 0);

        $this->actingAs($this->vigilante)
            ->followingRedirects()
            ->from(route('panel.porteria.ingresos.create'))
            ->post(route('panel.porteria.ingresos.store'), $this->datos(['autorizacion_id' => $autorizacion->id]))
            ->assertOk()
            ->assertSee('La autorización ya está usada.')
            ->assertSee('value="Laura Gómez"', false);
    }

    public function test_registra_la_salida(): void
    {
        $registro = RegistroPorteria::factory()->sinSalida()->create();

        $this->actingAs($this->vigilante)
            ->from(route('panel.porteria.ingresos.index'))
            ->patch(route('panel.porteria.ingresos.salida', $registro))
            ->assertRedirect(route('panel.porteria.ingresos.index'))
            ->assertSessionHas('exito');

        $this->assertNotNull($registro->fresh()->fecha_salida);
    }

    public function test_salida_repetida_no_cambia_la_hora(): void
    {
        $registro = RegistroPorteria::factory()->create(['fecha_salida' => now()->subHour()]);
        $salida = $registro->fecha_salida->toDateTimeString();

        $this->actingAs($this->vigilante)
            ->patch(route('panel.porteria.ingresos.salida', $registro))
            ->assertSessionHas('error');

        $this->assertSame($salida, $registro->fresh()->fecha_salida->toDateTimeString());
    }

    public function test_el_vigilante_ve_el_boton_de_salida(): void
    {
        $registro = RegistroPorteria::factory()->sinSalida()->create(['nombre_visitante' => 'Visita adentro']);

        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.index'))
            ->assertOk()
            ->assertSee('Visita adentro')
            ->assertSee(route('panel.porteria.ingresos.salida', $registro));
    }

    public function test_el_consejo_ve_el_listado_sin_boton_de_salida(): void
    {
        $consejo = User::factory()->conRol(Rol::Consejo)->create();
        $registro = RegistroPorteria::factory()->sinSalida()->create(['nombre_visitante' => 'Visita adentro']);

        $this->actingAs($consejo)
            ->get(route('panel.porteria.ingresos.index'))
            ->assertOk()
            ->assertSee('Visita adentro')
            ->assertDontSee(route('panel.porteria.ingresos.salida', $registro))
            ->assertDontSee('Registrar salida');

        $this->actingAs($consejo)->patch(route('panel.porteria.ingresos.salida', $registro))->assertForbidden();
    }

    public function test_filtra_por_tipo_y_fechas(): void
    {
        RegistroPorteria::factory()->create(['tipo_ingreso' => 'vehiculo', 'placa' => 'XYZ987', 'nombre_visitante' => 'Conductor uno', 'fecha_ingreso' => now()]);
        RegistroPorteria::factory()->create(['tipo_ingreso' => 'domiciliario', 'placa' => null, 'nombre_visitante' => 'Mensajero dos', 'fecha_ingreso' => now()]);

        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.index', ['tipo_ingreso' => 'vehiculo', 'desde' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Conductor uno')
            ->assertDontSee('Mensajero dos');
    }

    public function test_tipo_invalido_en_json_da_422(): void
    {
        $this->actingAs($this->vigilante)
            ->getJson(route('panel.porteria.ingresos.index', ['tipo_ingreso' => 'nave']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo_ingreso');
    }

    public function test_filtro_invalido_en_web_vuelve_al_listado_limpio(): void
    {
        $this->actingAs($this->vigilante)
            ->from(route('panel.porteria.ingresos.index', ['tipo_ingreso' => 'nave']))
            ->get(route('panel.porteria.ingresos.index', ['tipo_ingreso' => 'nave']))
            ->assertRedirect(route('panel.porteria.ingresos.index'))
            ->assertSessionHasErrors('tipo_ingreso');

        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.index', ['desde' => '2026-11-08', 'hasta' => '2026-11-01']))
            ->assertRedirect(route('panel.porteria.ingresos.index'))
            ->assertSessionHasErrors('hasta');

        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.index'))
            ->assertOk();
    }

    public function test_endpoint_de_autorizaciones_de_la_unidad(): void
    {
        $vigente = $this->vigente(['visitante_nombre' => 'Ana Vigente', 'placa' => 'QWE456']);
        Autorizacion::factory()->usada()->create(['unidad_id' => $this->unidad->id]);
        $this->vigente(['unidad_id' => Unidad::factory()->create()->id, 'visitante_nombre' => 'De otra unidad']);
        Autorizacion::factory()->create([
            'unidad_id' => $this->unidad->id,
            'valida_desde' => now()->addDay(),
            'valida_hasta' => now()->addDays(2),
        ]);

        $this->actingAs($this->vigilante)
            ->getJson(route('panel.porteria.ingresos.autorizaciones', ['unidad_id' => $this->unidad->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vigente->id)
            ->assertJsonPath('data.0.visitante_nombre', 'Ana Vigente')
            ->assertJsonPath('data.0.placa', 'QWE456');

        $this->actingAs($this->vigilante)
            ->getJson(route('panel.porteria.ingresos.autorizaciones'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unidad_id');

        $this->actingAs(User::factory()->conRol(Rol::Residente)->create())
            ->getJson(route('panel.porteria.ingresos.autorizaciones', ['unidad_id' => $this->unidad->id]))
            ->assertForbidden();
    }

    public function test_autorizaciones_vigentes_con_enlace_al_registro(): void
    {
        $vigente = $this->vigente(['visitante_nombre' => 'Ana Vigente']);
        Autorizacion::factory()->anulada()->create(['visitante_nombre' => 'Anulada fulana']);

        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.autorizaciones.vigentes'))
            ->assertOk()
            ->assertSee('Ana Vigente')
            ->assertDontSee('Anulada fulana')
            ->assertSee(route('panel.porteria.ingresos.create', ['unidad_id' => $this->unidad->id, 'autorizacion_id' => $vigente->id]));

        $this->actingAs($this->vigilante)
            ->get(route('panel.porteria.ingresos.create', ['unidad_id' => $this->unidad->id, 'autorizacion_id' => $vigente->id]))
            ->assertOk()
            ->assertSee('data-inicial="'.$vigente->id.'"', false);

        $this->actingAs(User::factory()->conRol(Rol::Consejo)->create())
            ->get(route('panel.porteria.autorizaciones.vigentes'))
            ->assertForbidden();
    }
}
