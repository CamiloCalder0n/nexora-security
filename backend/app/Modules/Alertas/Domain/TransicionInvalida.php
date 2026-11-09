<?php

namespace App\Modules\Alertas\Domain;

use DomainException;

/** Cambio de estado que el ciclo de la alerta no permite (por ejemplo, atender una alerta cerrada). */
class TransicionInvalida extends DomainException
{
    public static function desde(EstadoAlerta $actual, string $accion): self
    {
        return new self("No se puede {$accion} una alerta {$actual->value}.");
    }
}
