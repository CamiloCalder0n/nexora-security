<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use MongoDB\Driver\Exception\BulkWriteException;

class EventoRepository
{
    private const CLAVE_DUPLICADA = 11000;

    /** Inserta el evento; devuelve null si ese uuid ya existía (lo garantiza el índice único). */
    public function crearSiNoExiste(array $datos): ?Evento
    {
        try {
            return Evento::create($datos);
        } catch (BulkWriteException $e) {
            if ($e->getWriteResult()->getWriteErrors()[0]?->getCode() === self::CLAVE_DUPLICADA) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Pendientes recibidos entre `$desde` y `$hasta` a los que ya les toca reencolar y no agotaron
     * sus intentos, los más viejos primero (usa el índice parcial `pendientes`).
     */
    public function pendientesParaReencolar(CarbonInterface $desde, CarbonInterface $hasta, int $maxReencolos, int $limite): Collection
    {
        return Evento::where('resultado', ResultadoEvento::Pendiente)
            ->whereBetween('created_at', [$desde, $hasta])
            ->where(fn ($q) => $q->whereNull('proximo_reencolo')->orWhere('proximo_reencolo', '<=', now()))
            ->where(fn ($q) => $q->whereNull('reencolos')->orWhere('reencolos', '<', $maxReencolos))
            ->orderBy('created_at')
            ->limit($limite)
            ->get();
    }

    /** Anota un reencolado y cuándo puede tocar el siguiente (espera creciente). */
    public function anotarReencolo(Evento $evento, CarbonInterface $proximo): void
    {
        $evento->fill(['reencolos' => ($evento->reencolos ?? 0) + 1, 'proximo_reencolo' => $proximo])->save();
    }

    public function buscar(string $id): ?Evento
    {
        return Evento::find($id);
    }

    public function marcar(Evento $evento, ResultadoEvento $resultado, array $extra = []): void
    {
        $evento->fill(['resultado' => $resultado, ...$extra])->save();
    }
}
