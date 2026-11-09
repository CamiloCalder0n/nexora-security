<?php

namespace App\Modules\Porteria\Domain;

/** Placa colombiana: carros (ABC123) y motos (ABC12D o ABC12). */
final class Placa
{
    public const FORMATO = '/^[A-Z]{3}\d{2}[A-Z0-9]?$/';

    public const MENSAJE_FORMATO = 'La placa debe tener el formato ABC123 (carro) o ABC12D (moto).';

    /** Mayúsculas y sin espacios ni guiones; vacía queda en null. */
    public static function normalizar(mixed $placa): mixed
    {
        if (! is_string($placa)) {
            return $placa;
        }

        $placa = mb_strtoupper(preg_replace('/[\s-]+/', '', $placa));

        return $placa === '' ? null : $placa;
    }
}
