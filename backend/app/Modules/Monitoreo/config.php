<?php

return [
    // Rate limit de POST /agente/eventos por token (contrato: 60 por minuto).
    'eventos_por_minuto' => (int) env('AGENTE_EVENTOS_POR_MINUTO', 60),
];
