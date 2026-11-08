<?php

namespace App\Modules\Monitoreo\Domain;

enum TipoZona: string
{
    case AccesoPeatonal = 'acceso_peatonal';
    case AccesoVehicular = 'acceso_vehicular';
    case Parqueadero = 'parqueadero';
    case Perimetro = 'perimetro';
    case ZonaComun = 'zona_comun';
    case Servicio = 'servicio';
}
