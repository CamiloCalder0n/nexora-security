<?php

namespace App\Modules\Porteria\Domain;

enum EstadoAutorizacion: string
{
    case Vigente = 'vigente';
    case Usada = 'usada';
    case Anulada = 'anulada';
    case Expirada = 'expirada';
}
