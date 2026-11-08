<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Domain\Events\CamaraSinSenal;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Infrastructure\Repositories\CamaraRepository;
use Illuminate\Database\Eloquent\Collection;

class CamaraService
{
    public function __construct(
        private readonly CamaraRepository $camaras,
        private readonly VigilanciaService $vigilancia,
    ) {}

    /** @return Collection<int, Camara> cámaras no inactivas con su zona y `vigilada_ahora` */
    public function paraAgente(): Collection
    {
        return $this->camaras->vigilables()->each(
            fn (Camara $camara) => $camara->setAttribute('vigilada_ahora', $this->vigilancia->vigiladaAhora($camara->zona)),
        );
    }

    /** Pasa a sin_senal las cámaras que dejaron de latir y avisa a Alertas; devuelve cuántas cayeron. */
    public function marcarSinSenal(): int
    {
        $caidas = $this->camaras->sinHeartbeatDesde(now()->subMinutes(config('monitoreo.minutos_sin_senal')));

        foreach ($caidas as $camara) {
            $camara->estado = EstadoCamara::SinSenal;
            $this->camaras->guardar($camara);
            CamaraSinSenal::dispatch(
                $camara->id, $camara->nombre, $camara->zona->id, $camara->zona->nombre,
                $camara->ultima_conexion->toIso8601String(),
            );
        }

        return $caidas->count();
    }

    /** Heartbeat: la cámara reporta que sigue viva; si estaba sin señal, vuelve a activa. */
    public function registrarLatido(Camara $camara): void
    {
        $camara->ultima_conexion = now();
        if ($camara->estado === EstadoCamara::SinSenal) {
            $camara->estado = EstadoCamara::Activa;
        }
        $this->camaras->guardar($camara);
    }
}
