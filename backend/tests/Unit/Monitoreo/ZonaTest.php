<?php

namespace Tests\Unit\Monitoreo;

use App\Modules\Monitoreo\Domain\Models\Franja;
use App\Modules\Monitoreo\Domain\Models\Zona;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ZonaTest extends TestCase
{
    private function zonaCon(array ...$franjas): Zona
    {
        $zona = new Zona(['nombre' => 'Zona de prueba', 'tipo' => 'perimetro', 'activa' => true]);
        $zona->setRelation('franjas', collect($franjas)->map(fn (array $datos) => new Franja($datos)));

        return $zona;
    }

    private function momento(string $fecha): CarbonImmutable
    {
        return CarbonImmutable::parse($fecha, 'America/Bogota');
    }

    public function test_franja_normal_cubre_solo_dentro_del_horario(): void
    {
        $zona = $this->zonaCon(['dia_semana' => null, 'hora_inicio' => '08:00:00', 'hora_fin' => '17:00:00']);

        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 08:00')));
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 16:59')));
        $this->assertFalse($zona->estaVigilada($this->momento('2026-11-07 17:00')));
        $this->assertFalse($zona->estaVigilada($this->momento('2026-11-07 07:59')));
    }

    public function test_franja_que_cruza_medianoche(): void
    {
        $zona = $this->zonaCon(['dia_semana' => null, 'hora_inicio' => '18:00:00', 'hora_fin' => '06:00:00']);

        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 18:00')));
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 23:59')));
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-08 00:30')));
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-08 05:59')));
        $this->assertFalse($zona->estaVigilada($this->momento('2026-11-08 06:00')));
        $this->assertFalse($zona->estaVigilada($this->momento('2026-11-08 12:00')));
    }

    public function test_franja_nocturna_de_un_dia_cubre_la_madrugada_siguiente(): void
    {
        // Viernes (5) 22:00 → sábado 02:00
        $zona = $this->zonaCon(['dia_semana' => 5, 'hora_inicio' => '22:00:00', 'hora_fin' => '02:00:00']);

        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-06 23:00')));  // viernes
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 01:00')));  // sábado madrugada
        $this->assertFalse($zona->estaVigilada($this->momento('2026-11-07 23:00'))); // sábado noche
        $this->assertFalse($zona->estaVigilada($this->momento('2026-11-06 01:00'))); // viernes madrugada
    }

    public function test_franja_de_todo_el_dia(): void
    {
        $zona = $this->zonaCon(['dia_semana' => null, 'hora_inicio' => '00:00:00', 'hora_fin' => '00:00:00']);

        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 00:00')));
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 13:45')));
        $this->assertTrue($zona->estaVigilada($this->momento('2026-11-07 23:59')));
    }

    public function test_respeta_la_zona_horaria_de_colombia(): void
    {
        $zona = $this->zonaCon(['dia_semana' => null, 'hora_inicio' => '18:00:00', 'hora_fin' => '06:00:00']);

        // 2026-11-07 12:00 UTC = 07:00 en Bogotá → fuera de franja
        $this->assertFalse($zona->estaVigilada(CarbonImmutable::parse('2026-11-07 12:00', 'UTC')));
        // 2026-11-08 02:00 UTC = 21:00 en Bogotá → dentro
        $this->assertTrue($zona->estaVigilada(CarbonImmutable::parse('2026-11-08 02:00', 'UTC')));
    }

    public function test_zona_inactiva_o_sin_franjas_no_esta_vigilada(): void
    {
        $inactiva = $this->zonaCon(['dia_semana' => null, 'hora_inicio' => '00:00:00', 'hora_fin' => '00:00:00']);
        $inactiva->activa = false;

        $this->assertFalse($inactiva->estaVigilada($this->momento('2026-11-07 12:00')));
        $this->assertFalse($this->zonaCon()->estaVigilada($this->momento('2026-11-07 12:00')));
    }
}
