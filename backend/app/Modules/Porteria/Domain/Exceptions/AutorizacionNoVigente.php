<?php

namespace App\Modules\Porteria\Domain\Exceptions;

class AutorizacionNoVigente extends ReglaPorteriaException
{
    public function status(): int
    {
        return 409;
    }
}
