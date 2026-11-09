<?php

namespace App\Modules\Porteria\Domain\Exceptions;

use App\Modules\Porteria\Domain\Models\Autorizacion;

class AutorizacionFueraDeVentana extends ReglaPorteriaException
{
    public function __construct(public readonly Autorizacion $autorizacion, public readonly bool $expiro)
    {
        parent::__construct($expiro
            ? 'La autorización venció y quedó expirada.'
            : 'La autorización todavía no está vigente.');
    }

    public function status(): int
    {
        return 422;
    }
}
