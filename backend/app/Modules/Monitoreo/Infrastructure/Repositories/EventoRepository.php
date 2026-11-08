<?php

namespace App\Modules\Monitoreo\Infrastructure\Repositories;

use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
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

    public function buscar(string $id): ?Evento
    {
        return Evento::find($id);
    }

    public function marcar(Evento $evento, ResultadoEvento $resultado, array $extra = []): void
    {
        $evento->fill(['resultado' => $resultado, ...$extra])->save();
    }
}
