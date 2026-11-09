<?php

namespace App\Modules\Alertas\Domain;

enum Clasificacion: string
{
    case FalsaAlarma = 'falsa_alarma';
    case SinNovedad = 'sin_novedad';
    case Incidente = 'incidente';
}
