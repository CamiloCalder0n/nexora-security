<?php

namespace Tests\Feature\Alertas;

use App\Models\User;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Application\BitacoraService;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use App\Modules\Alertas\Domain\TransicionInvalida;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

class CicloAlertaTest extends TestCase
{
    use LimpiaMongo, RefreshDatabase;

    private AlertaService $servicio;

    private User $vigilante;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('nexora:mongo-indices');
        $this->servicio = app(AlertaService::class);
        $this->vigilante = User::factory()->conRol(Rol::Vigilante)->create(['name' => 'Pedro Celador']);
    }

    private function alerta(): Alerta
    {
        return Alerta::create([
            'fecha_hora' => now(), 'tipo' => 'sin_registro', 'prioridad' => 3, 'motivo' => 'Persona sin registro',
            'zona' => ['zona_id' => 1, 'nombre' => 'Parqueadero'],
        ]);
    }

    public function test_atender_guarda_quien_y_cuando_y_va_a_la_bitacora(): void
    {
        $alerta = $this->servicio->atender($this->alerta(), $this->vigilante)->fresh();

        $this->assertSame('atendida', $alerta->estado->value);
        $this->assertSame(['usuario_id' => $this->vigilante->id, 'nombre' => 'Pedro Celador'], $alerta->atendida_por);
        $this->assertNotNull($alerta->fecha_atencion);
        $entrada = EntradaBitacora::first();
        $this->assertSame(['alerta_atendida', 'abierta', 'atendida'], [$entrada->tipo, $entrada->estado_anterior, $entrada->estado_nuevo]);
        $this->assertSame('vigilante', $entrada->usuario['rol']);
    }

    public function test_la_instancia_que_se_pasa_queda_actualizada(): void
    {
        $alerta = $this->alerta();
        $this->servicio->atender($alerta, $this->vigilante);

        $this->assertSame('atendida', $alerta->estado->value);
    }

    public function test_no_se_atiende_dos_veces(): void
    {
        $alerta = $this->servicio->atender($this->alerta(), $this->vigilante);

        $this->expectException(TransicionInvalida::class);
        $this->servicio->atender($alerta, $this->vigilante);
    }

    public function test_cerrar_sin_clasificar_es_422(): void
    {
        try {
            $this->servicio->cerrar($this->alerta(), $this->vigilante, null);
            $this->fail('Debió exigir la clasificación');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status);
            $this->assertArrayHasKey('clasificacion', $e->errors());
        }
    }

    public function test_incidente_sin_observacion_es_422(): void
    {
        $this->expectException(ValidationException::class);
        $this->servicio->cerrar($this->alerta(), $this->vigilante, 'incidente', '  ');
    }

    public function test_cerrar_como_falsa_alarma(): void
    {
        $alerta = $this->servicio->cerrar($this->alerta(), $this->vigilante, 'falsa_alarma')->fresh();

        $this->assertSame('cerrada', $alerta->estado->value);
        $this->assertSame('falsa_alarma', $alerta->clasificacion->value);
        $this->assertNotNull($alerta->fecha_cierre);
        $this->assertSame('alerta_cerrada', EntradaBitacora::first()->tipo);
    }

    public function test_verificador_dice_ok_con_la_cadena_intacta(): void
    {
        $alerta = $this->servicio->atender($this->alerta(), $this->vigilante);
        $this->servicio->cerrar($alerta, $this->vigilante, 'incidente', 'Persona ajena, se llamó a la policía');

        $this->artisan('nexora:verificar-bitacora')
            ->expectsOutputToContain('OK: 2 entradas')
            ->assertSuccessful();
    }

    public function test_alterar_una_entrada_lo_detecta_el_verificador(): void
    {
        $bitacora = app(BitacoraService::class);
        $bitacora->registrar('alerta_creada', 'Primera', null, 'abierta');
        $segunda = $bitacora->registrar('alerta_atendida', 'Segunda', 'abierta', 'atendida', $this->vigilante);
        $bitacora->registrar('alerta_cerrada', 'Tercera', 'atendida', 'cerrada', $this->vigilante);

        $segunda->update(['descripcion' => 'Alguien cambió esto']);

        $this->artisan('nexora:verificar-bitacora')
            ->expectsOutputToContain('se rompe en la entrada #2')
            ->assertFailed();
    }

    public function test_borrar_una_entrada_lo_detecta_el_verificador(): void
    {
        $bitacora = app(BitacoraService::class);
        $bitacora->registrar('alerta_creada', 'Primera', null, 'abierta');
        $segunda = $bitacora->registrar('alerta_atendida', 'Segunda', 'abierta', 'atendida');
        $bitacora->registrar('alerta_cerrada', 'Tercera', 'atendida', 'cerrada');

        $segunda->delete();

        $this->artisan('nexora:verificar-bitacora')->assertFailed();
    }
}
