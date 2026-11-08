<?php

namespace App\Modules\Identidad\Domain;

enum Rol: string
{
    case Admin = 'admin';
    case Vigilante = 'vigilante';
    case Residente = 'residente';
    case Consejo = 'consejo';
    case Agente = 'agente';
}
