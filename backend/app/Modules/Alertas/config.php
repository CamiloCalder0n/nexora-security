<?php

use App\Modules\Alertas\Application\Reglas\SinRegistroEnPorteria;

return [
    // Reglas del motor de correlación, en orden: decide la primera que tenga algo que decir.
    // Agregar una regla = crear la clase (ReglaDeteccion) y ponerla aquí, sin tocar el motor (HU31).
    'reglas' => [
        SinRegistroEnPorteria::class,
    ],

    // Escalamiento (HU12): las de esta prioridad suben un nivel cada N minutos sin atender.
    'prioridad_alta' => 3,
    'minutos_escalar' => (int) env('ALERTAS_MINUTOS_ESCALAR', 5),
];
