<?php

namespace App\Modules\Alertas\Domain;

enum EstadoAlerta: string
{
    case Abierta = 'abierta';
    case Atendida = 'atendida';
    case Cerrada = 'cerrada';
}
