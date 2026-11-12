<?php

namespace App\Modules\Analitica\Application;

use App\Models\User;
use App\Modules\Analitica\Infrastructure\Repositories\EventosLecturaRepository;
use App\Modules\Identidad\Application\AuditoriaService;
use App\Modules\Monitoreo\Application\ZonaService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/** RF005 / HU14: reconstruir lo que pasó. RF007: la evidencia solo se ve con motivo y queda auditada. */
class HistorialService
{
    public const LIMITE_CSV = 5000;

    public function __construct(
        private readonly EventosLecturaRepository $eventos,
        private readonly ZonaService $zonas,
        private readonly AuditoriaService $auditoria,
    ) {}

    /** null si MongoDB no responde. */
    public function listar(array $filtros): ?LengthAwarePaginator
    {
        try {
            return $this->eventos->historial($filtros);
        } catch (Throwable $e) {
            Log::warning('Historial: no se pudo leer MongoDB', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** CU05: exportar el resultado de la búsqueda (sin la ruta de la evidencia). */
    public function filasCsv(array $filtros): array
    {
        $filas = [['Fecha', 'Zona', 'Cámara', 'Tipo', 'Cantidad', 'Confianza', 'Resultado', 'Id']];
        foreach ($this->eventos->exportar($filtros, self::LIMITE_CSV) as $e) {
            $filas[] = [
                $e['fecha_hora']->format('Y-m-d H:i:s'), $e['zona']['nombre'] ?? '', $e['camara']['nombre'] ?? '',
                $e['tipo_objeto'], $e['cantidad'], $e['confianza'], $e['resultado'], $e['uuid'],
            ];
        }

        return $filas;
    }

    public function zonas(): Collection
    {
        return $this->zonas->todas();
    }

    public function evento(string $uuid): ?array
    {
        return $this->eventos->buscarPorUuid($uuid);
    }

    /** Primero se registra el acceso; si no se puede auditar, no se muestra nada. */
    public function consultarEvidencia(User $usuario, array $evento, string $motivo, ?string $ip): array
    {
        $this->auditoria->registrar($usuario, AuditoriaService::RECURSO_EVIDENCIA, $evento['uuid'], $motivo, $ip);

        return $evento;
    }
}
