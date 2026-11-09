<?php

namespace App\Modules\Alertas\Domain;

enum TipoAlerta: string
{
    case SinRegistro = 'sin_registro';
    case CamaraSinSenal = 'camara_sin_senal';
}
