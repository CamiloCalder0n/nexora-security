<?php

use App\Modules\Alertas\Application\Reglas\SinRegistroEnPorteria;

return [
    // Reglas del motor de correlación, en orden: decide la primera que tenga algo que decir.
    // Agregar una regla = crear la clase (ReglaDeteccion) y ponerla aquí, sin tocar el motor (HU31).
    'reglas' => [
        SinRegistroEnPorteria::class,
    ],
];
