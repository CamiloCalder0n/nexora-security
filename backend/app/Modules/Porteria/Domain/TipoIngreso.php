<?php

namespace App\Modules\Porteria\Domain;

enum TipoIngreso: string
{
    case Visitante = 'visitante';
    case Domiciliario = 'domiciliario';
    case Vehiculo = 'vehiculo';
    case Contratista = 'contratista';
}
