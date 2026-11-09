<?php

return [
    // Rate limit de POST /agente/eventos por token (contrato: 60 por minuto).
    'eventos_por_minuto' => (int) env('AGENTE_EVENTOS_POR_MINUTO', 60),

    // Minutos sin heartbeat para dar por caída una cámara (el agente late cada 60 s, HU13).
    'minutos_sin_senal' => (int) env('CAMARA_MINUTOS_SIN_SENAL', 5),

    // Un evento que sigue en pendiente N minutos sin cambios perdió su job: se vuelve a encolar.
    'minutos_reencolar' => (int) env('EVENTOS_MINUTOS_REENCOLAR', 2),
];
