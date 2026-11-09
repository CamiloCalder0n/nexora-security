<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
     * Pendientes de las últimas 24 h que no cambian desde `$hasta` y no agotaron sus intentos, los más
     * viejos primero (usa el índice parcial `pendientes`).
     */
    public function pendientesSinCambiosDesde(CarbonInterface $hasta, int $maxReencolos, int $limite): Collection
    {
        return Evento::where('resultado', ResultadoEvento::Pendiente)
            ->where('created_at', '>=', now()->subHours(24))
            ->where('updated_at', '<=', $hasta)
            ->where(fn ($q) => $q->whereNull('reencolos')->orWhere('reencolos', '<', $maxReencolos))
            ->orderBy('created_at')
            ->limit($limite)
            ->get();
    }

    /** Cuenta el reencolado (y mueve `updated_at`, que marca cuándo toca el siguiente); devuelve el número de intento. */
    public function anotarReencolo(Evento $evento): int
    {
        $evento->fill(['reencolos' => ($evento->reencolos ?? 0) + 1])->save();

        return $evento->reencolos;
    }

    public function buscar(string $id): ?Evento
    {
        return Evento::find($id);
    }

    /**
     * Resuelve el evento solo si sigue pendiente: si dos jobs del mismo evento (el original y su
     * reencolado) corren a la vez, el segundo no pisa al primero. Si el segundo ya había agrupado el
     * evento en una alerta, la alerta lo sigue contando: queda un aviso en el log para revisarlo.
     */
    public function marcar(Evento $evento, ResultadoEvento $resultado, array $extra = []): void
    {
        $cambiados = Evento::where('_id', $evento->id)
            ->where('resultado', ResultadoEvento::Pendiente->value)
            ->update(['resultado' => $resultado->value, ...$extra]);
        $evento->refresh();

        if ($cambiados === 0) {
            Log::warning('Otro job ya había resuelto el evento; no se cambia', [
                'evento' => $evento->id, 'quedó' => $evento->resultado?->value, 'se pedía' => $resultado->value,
            ]);
        }
    }
}
