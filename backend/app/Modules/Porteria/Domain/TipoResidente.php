<?php

namespace App\Modules\Porteria\Domain;

enum TipoResidente: string
{
    case Propietario = 'propietario';
    case Arrendatario = 'arrendatario';
}
