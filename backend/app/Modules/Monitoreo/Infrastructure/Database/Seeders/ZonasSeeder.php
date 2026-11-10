<?php

namespace App\Modules\Monitoreo\Infrastructure\Database\Seeders;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\TipoZona;
use Illuminate\Database\Seeder;

class ZonasSeeder extends Seeder
{
    /** Inicio igual a fin: la zona se vigila las 24 horas (así la demo funciona a cualquier hora). */
    private const TODO_EL_DIA = ['00:00:00', '00:00:00'];

    /** nombre, tipo, prioridad, franja, cámaras */
    private const ZONAS = [
        ['Acceso peatonal principal', TipoZona::AccesoPeatonal, 2, self::TODO_EL_DIA, ['CAM-01 Peatonal principal']],
        ['Acceso peatonal posterior', TipoZona::AccesoPeatonal, 2, self::TODO_EL_DIA, ['CAM-02 Peatonal posterior']],
        ['Acceso vehicular', TipoZona::AccesoVehicular, 2, self::TODO_EL_DIA, ['CAM-03 Vehicular entrada', 'CAM-04 Vehicular salida']],
        ['Parqueadero sótano', TipoZona::Parqueadero, 3, self::TODO_EL_DIA, ['CAM-05 Parqueadero', 'CAM-06 Parqueadero rampa']],
        ['Perímetro norte', TipoZona::Perimetro, 3, self::TODO_EL_DIA, ['CAM-07 Perímetro norte']],
        ['Perímetro sur', TipoZona::Perimetro, 3, self::TODO_EL_DIA, ['CAM-08 Perímetro sur']],
        ['Cuarto de basuras', TipoZona::Servicio, 1, self::TODO_EL_DIA, ['CAM-09 Basuras']],
        ['Salón social y BBQ', TipoZona::ZonaComun, 1, self::TODO_EL_DIA, ['CAM-10 Salón social']],
    ];

    public function run(): void
    {
        foreach (self::ZONAS as [$nombre, $tipo, $prioridad, [$inicio, $fin], $camaras]) {
            $zona = Zona::factory()->create([
                'nombre' => $nombre,
                'tipo' => $tipo,
                'prioridad_base' => $prioridad,
            ]);

            $zona->franjas()->create(['dia_semana' => null, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);

            foreach ($camaras as $camara) {
                $zona->camaras()->create(['nombre' => $camara, 'estado' => EstadoCamara::Activa]);
            }
        }
    }
}
