<?php

namespace App\Modules\Porteria\Application\Contracts;

use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Carbon\CarbonInterface;

/**
 * Puerto que usa la correlación de Alertas para saber si un evento lo explica un ingreso registrado.
 * Firma acordada en docs/guias/GUIA_02_CAMI_DATOS_PORTERIA.md (Paso 4); la implementa el repositorio de Portería.
 */
interface ConsultaRegistrosPorteria
{
    public function buscarParaCorrelacion(int $zonaId, CarbonInterface $momento, int $ventanaMin, int $cantidad): ?RegistroPorteria;
}
