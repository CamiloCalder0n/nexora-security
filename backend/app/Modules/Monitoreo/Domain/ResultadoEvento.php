<?php

namespace App\Modules\Monitoreo\Domain;

enum ResultadoEvento: string
{
    case Pendiente = 'pendiente';
    case Explicado = 'explicado';
    case Alerta = 'alerta';
    case Suprimido = 'suprimido';
    case FueraFranja = 'fuera_franja';
}
