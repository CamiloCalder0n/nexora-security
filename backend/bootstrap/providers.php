<?php

use App\Modules\Alertas\AlertasServiceProvider;
use App\Modules\Analitica\AnaliticaServiceProvider;
use App\Modules\Identidad\IdentidadServiceProvider;
use App\Modules\Monitoreo\MonitoreoServiceProvider;
use App\Modules\Porteria\PorteriaServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentidadServiceProvider::class,
    PorteriaServiceProvider::class,
    MonitoreoServiceProvider::class,
    AlertasServiceProvider::class,
    AnaliticaServiceProvider::class,
];
