<?php

namespace App\Modules\Porteria\Domain\Exceptions;

class ResidenteConHistorial extends ReglaPorteriaException
{
    public function __construct()
    {
        parent::__construct('El residente tiene autorizaciones registradas y no se puede eliminar.');
    }

    public function status(): int
    {
        return 409;
    }
}
