<?php

namespace App\Modules\Analitica\Application;

use App\Modules\Analitica\Infrastructure\Repositories\AlertasLecturaRepository;
use App\Modules\Analitica\Infrastructure\Repositories\EventosLecturaRepository;
use App\Modules\Porteria\Application\RegistroPorteriaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/** RF008: informe del mes para el consejo y la administración. */
class InformeMensualService
{
    public const FRANJAS = [
        'Madrugada (0–6 h)' => [0, 5],
        'Mañana (6–12 h)' => [6, 11],
        'Tarde (12–18 h)' => [12, 17],
        'Noche (18–24 h)' => [18, 23],
    ];

    public function __construct(
        private readonly AlertasLecturaRepository $alertas,
        private readonly EventosLecturaRepository $eventos,
        private readonly RegistroPorteriaService $registros,
    ) {}

    public function generar(string $mes): array
    {
        $desde = Carbon::createFromFormat('Y-m-d', $mes.'-01', config('app.timezone'))->startOfMonth();
        $hasta = $desde->copy()->endOfMonth();

        $alertas = $this->leer(fn () => $this->alertas->informe($desde, $hasta));
        $eventos = $this->leer(fn () => $this->eventos->totalesPorResultado($desde, $hasta));

        $clasificadas = $alertas ? array_sum($alertas['por_clasificacion']) : 0;

        return [
            'mes' => $mes,
            'desde' => $desde,
            'hasta' => $hasta,
            'mongo_disponible' => $alertas !== null && $eventos !== null,
            'ingresos' => $this->registros->totalIngresos($desde, $hasta),
            'eventos' => $eventos,
            'eventos_total' => $eventos ? array_sum($eventos) : null,
            'alertas' => $alertas,
            'clasificadas' => $clasificadas,
            'porcentaje_falsas' => $clasificadas > 0
                ? round(($alertas['por_clasificacion']['falsa_alarma'] ?? 0) * 100 / $clasificadas, 1)
                : null,
            'por_franja' => $alertas ? $this->franjas($alertas['por_hora']) : null,
        ];
    }

    /** Filas planas para el CSV. */
    public function filasCsv(array $informe): array
    {
        $filas = [['Indicador', 'Detalle', 'Valor'], ['Mes', '', $informe['mes']], ['Ingresos en portería', '', $informe['ingresos']]];

        foreach ($informe['eventos'] ?? [] as $resultado => $total) {
            $filas[] = ['Eventos', $resultado, $total];
        }
        if ($a = $informe['alertas']) {
            $filas[] = ['Alertas', 'total', $a['total']];
            $filas[] = ['Alertas', 'escaladas', $a['escaladas']];
            foreach ($a['por_clasificacion'] as $clase => $total) {
                $filas[] = ['Alertas cerradas', $clase, $total];
            }
            $filas[] = ['Falsas alarmas', '% de las clasificadas', $informe['porcentaje_falsas'] ?? ''];
            foreach ($a['por_zona'] as $z) {
                $filas[] = ['Alertas por zona', $z['zona'], $z['total']];
                $filas[] = ['Incidentes por zona', $z['zona'], $z['incidentes']];
            }
            foreach ($informe['por_franja'] as $franja => $total) {
                $filas[] = ['Alertas por franja', $franja, $total];
            }
            $filas[] = ['Tiempo de atención', 'promedio (min)', $a['atencion']['promedio_min'] ?? ''];
            $filas[] = ['Tiempo de atención', 'máximo (min)', $a['atencion']['maximo_min'] ?? ''];
        }

        return $filas;
    }

    private function franjas(array $porHora): array
    {
        $resultado = [];
        foreach (self::FRANJAS as $nombre => [$inicio, $fin]) {
            $resultado[$nombre] = array_sum(array_filter($porHora, fn ($h) => $h >= $inicio && $h <= $fin, ARRAY_FILTER_USE_KEY));
        }

        return $resultado;
    }

    private function leer(callable $consulta): ?array
    {
        try {
            return $consulta();
        } catch (Throwable $e) {
            Log::warning('Informe mensual: no se pudo leer MongoDB', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
