<?php

namespace Tests\Feature\Panel;

use App\Models\User;
use App\Modules\Identidad\Application\AuditoriaService;
use App\Modules\Identidad\Domain\Models\AccesoAuditado;
use App\Modules\Identidad\Domain\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DocumentosMongo;
use Tests\Concerns\LimpiaMongo;
use Tests\TestCase;

/** RF007: la evidencia solo se ve con motivo y cada consulta queda auditada. */
class AuditoriaTest extends TestCase
{
    use DocumentosMongo, LimpiaMongo, RefreshDatabase;

    private const RUTA = 'evidencias/cam1/20261110-220000.jpg';

    private function como(Rol $rol): static
    {
        return $this->actingAs(User::factory()->conRol($rol)->create());
    }

    public function test_ver_la_evidencia_exige_motivo_y_registra_el_acceso(): void
    {
        $evento = $this->crearEvento(['ruta_evidencia' => self::RUTA]);
        $admin = User::factory()->conRol(Rol::Admin)->create();

        $this->actingAs($admin)->get(route('panel.analitica.evidencia', $evento->uuid))
            ->assertOk()
            ->assertSee('Motivo de la consulta')
            ->assertDontSee(self::RUTA);

        $this->actingAs($admin)->post(route('panel.analitica.evidencia.ver', $evento->uuid), ['motivo' => 'corto'])
            ->assertSessionHasErrors('motivo');
        $this->assertDatabaseCount('auditoria_accesos', 0);

        $this->actingAs($admin)->post(route('panel.analitica.evidencia.ver', $evento->uuid), [
            'motivo' => 'Revisión del incidente reportado en la torre 2',
        ])->assertOk()->assertSee(self::RUTA)->assertSee('Consulta registrada en la auditoría.');

        $this->assertDatabaseHas('auditoria_accesos', [
            'user_id' => $admin->id,
            'recurso' => AuditoriaService::RECURSO_EVIDENCIA,
            'recurso_id' => $evento->uuid,
            'motivo' => 'Revisión del incidente reportado en la torre 2',
        ]);
    }

    public function test_solo_el_admin_puede_ver_evidencias(): void
    {
        $evento = $this->crearEvento(['ruta_evidencia' => self::RUTA]);

        foreach ([Rol::Vigilante, Rol::Consejo, Rol::Residente] as $rol) {
            $this->como($rol)->get(route('panel.analitica.evidencia', $evento->uuid))->assertForbidden();
            $this->como($rol)->post(route('panel.analitica.evidencia.ver', $evento->uuid), ['motivo' => 'Quiero ver la imagen del evento'])
                ->assertForbidden();
        }
        $this->assertDatabaseMissing('auditoria_accesos', ['recurso' => AuditoriaService::RECURSO_EVIDENCIA]);
    }

    public function test_evento_inexistente_da_404(): void
    {
        $this->como(Rol::Admin)->get(route('panel.analitica.evidencia', '00000000-0000-4000-8000-000000000000'))->assertNotFound();
    }

    public function test_el_admin_ve_la_auditoria_y_filtra_por_usuario(): void
    {
        $marta = User::factory()->conRol(Rol::Admin)->create(['name' => 'Marta Admin']);
        $otro = User::factory()->conRol(Rol::Admin)->create(['name' => 'Otro Admin']);
        AccesoAuditado::create(['user_id' => $marta->id, 'recurso' => 'evidencia', 'recurso_id' => 'abc', 'motivo' => 'Revisión de un incidente']);
        AccesoAuditado::create(['user_id' => $otro->id, 'recurso' => 'evidencia', 'recurso_id' => 'def', 'motivo' => 'Queja de un residente']);

        $this->actingAs($marta)->get(route('panel.identidad.auditoria.index'))
            ->assertOk()->assertSee('Revisión de un incidente')->assertSee('Queja de un residente');

        $accesos = $this->actingAs($marta)->get(route('panel.identidad.auditoria.index', ['search' => 'marta']))->viewData('accesos');
        $this->assertSame(1, $accesos->total());
    }

    public function test_un_intento_sin_permiso_queda_registrado(): void
    {
        $vigilante = User::factory()->conRol(Rol::Vigilante)->create();

        $this->actingAs($vigilante)->get(route('panel.identidad.usuarios.index'))->assertForbidden();

        $this->assertDatabaseHas('auditoria_accesos', [
            'user_id' => $vigilante->id,
            'recurso' => AuditoriaService::RECURSO_ACCESO_DENEGADO,
            'recurso_id' => 'panel.identidad.usuarios.index',
        ]);
    }

    public function test_exporta_la_auditoria_en_csv_con_los_filtros(): void
    {
        $marta = User::factory()->conRol(Rol::Admin)->create(['name' => 'Marta Admin']);
        AccesoAuditado::create(['user_id' => $marta->id, 'recurso' => 'evidencia', 'recurso_id' => 'abc', 'motivo' => 'Revisión de un incidente']);
        AccesoAuditado::create(['user_id' => $marta->id, 'recurso' => 'acceso_denegado', 'recurso_id' => 'xyz', 'motivo' => 'Sin permiso']);

        $csv = $this->actingAs($marta)->get(route('panel.identidad.auditoria.csv', ['recurso' => 'evidencia']))
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Revisión de un incidente', $csv);
        $this->assertStringNotContainsString('Sin permiso', $csv);
    }

    public function test_solo_el_admin_ve_la_auditoria(): void
    {
        foreach ([Rol::Vigilante, Rol::Consejo, Rol::Residente] as $rol) {
            $this->como($rol)->get(route('panel.identidad.auditoria.index'))->assertForbidden();
            $this->como($rol)->get(route('panel.identidad.auditoria.csv'))->assertForbidden();
        }
    }
}
