<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Infrastructure\Repositories\CamaraRepository;
use App\Modules\Monitoreo\Infrastructure\Repositories\EventoRepository;
use Illuminate\Support\Carbon;

class IngestaEventosService
{
    public function __construct(
        private readonly EventoRepository $eventos,
        private readonly CamaraRepository $camaras,
    ) {}

    /**
     * Guarda el evento y lo deja en cola para correlacionar.
     *
     * @return bool true si es nuevo, false si el uuid ya existía (reenvío del agente)
     */
    public function registrar(array $datos): bool
    {
        $camara = $this->camaras->conZona($datos['camara_id']);

        $evento = $this->eventos->crearSiNoExiste([
            'uuid' => $datos['uuid'],
            'fecha_hora' => Carbon::parse($datos['fecha_hora']),
            'tipo_objeto' => $datos['tipo_objeto'],
            'cantidad' => $datos['cantidad'],
            'confianza' => $datos['confianza'],
            'ruta_evidencia' => $datos['ruta_evidencia'] ?? null,
            'camara' => ['camara_id' => $camara->id, 'nombre' => $camara->nombre],
            'zona' => ['zona_id' => $camara->zona->id, 'nombre' => $camara->zona->nombre],
        ]);

        if ($evento === null) {
            return false;
        }

        EventoRegistrado::dispatch($evento->id);

        return true;
    }
}
