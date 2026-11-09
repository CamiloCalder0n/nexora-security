<?php

namespace App\Modules\Analitica\Application;

use App\Modules\Analitica\Infrastructure\Repositories\AlertasLecturaRepository;
use App\Modules\Monitoreo\Application\ZonaService;
use App\Modules\Porteria\Application\RegistroPorteriaService;
use App\Modules\Porteria\Application\ResidenteService;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardService
{
    public const DIAS = 30;

    public function __construct(
        private readonly RegistroPorteriaService $registros,
        private readonly ResidenteService $residentes,
        private readonly ZonaService $zonas,
        private readonly AlertasLecturaRepository $alertas,
    ) {}

    public function resumen(): array
    {
        $ingresos = $this->registros->ingresosPorDia(self::DIAS);
        $alertas = $this->alertas();

        return [
            'dias' => self::DIAS,
            'ingresos_por_dia' => $ingresos,
            'ingresos_hoy' => $ingresos[now()->toDateString()] ?? 0,
            'ingresos_periodo' => array_sum($ingresos),
            'residentes' => $this->residentes->totales(),
            'zonas' => $this->zonas->totales(),
            'mongo_disponible' => $alertas !== null,
            'alertas_abiertas' => $alertas['abiertas'] ?? null,
            'alertas_periodo' => $alertas['total'] ?? null,
            'alertas_cerradas' => $alertas['cerradas'] ?? null,
            // HU22: qué tanto de lo que se cierra resulta ser falsa alarma.
            'porcentaje_falsas' => $alertas && $alertas['cerradas'] > 0
                ? round($alertas['falsas_alarmas'] * 100 / $alertas['cerradas'], 1)
                : null,
            'alertas_por_zona' => $alertas['por_zona'] ?? null,
        ];
    }

    /** Si MongoDB no responde, el resto del dashboard se muestra igual. */
    private function alertas(): ?array
    {
        try {
            return $this->alertas->resumen(now()->subDays(self::DIAS - 1)->startOfDay());
        } catch (Throwable $e) {
            Log::warning('Dashboard: no se pudo leer MongoDB', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
