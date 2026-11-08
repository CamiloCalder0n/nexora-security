<?php

namespace App\Modules\Monitoreo\Domain;

enum EstadoCamara: string
{
    case Activa = 'activa';
    case SinSenal = 'sin_senal';
    case Inactiva = 'inactiva';
}
