<?php

namespace App\Modules\Porteria\Application\Contracts;

use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Carbon\CarbonInterface;

/**
 * Puerto que usa Alertas para saber si una detección la explica un ingreso.
 * Busca un registro de la zona dentro de ±$ventanaMin minutos de $momento
 * con al menos $cantidad personas; si hay varios, devuelve el más cercano.
 * La implementa RegistroPorteriaRepository (ver GUIA_02_CAMI_DATOS_PORTERIA.md, Paso 4).
 */
interface ConsultaRegistrosPorteria
{
    public function buscarParaCorrelacion(int $zonaId, CarbonInterface $momento, int $ventanaMin, int $cantidad): ?RegistroPorteria;
}
