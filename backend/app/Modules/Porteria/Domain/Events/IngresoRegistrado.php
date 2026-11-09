<?php

namespace App\Modules\Porteria\Domain\Events;

use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IngresoRegistrado
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly RegistroPorteria $registro) {}
}
