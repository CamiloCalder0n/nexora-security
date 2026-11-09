<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Infrastructure\Repositories\CamaraRepository;
use App\Modules\Monitoreo\Infrastructure\Repositories\EventoRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class IngestaEventosService
{
    private const MAX_REENCOLOS = 10;

    private const REENCOLAR_POR_CORRIDA = 500;

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

    /**
     * Red de seguridad: si un job se perdió (worker caído, Redis reiniciado), el evento se queda en
     * pendiente. Cada N minutos sin cambios se vuelve a encolar, como máximo `MAX_REENCOLOS` veces:
     * con N = 2, el valor por defecto, cubre una caída de unos 20 min de PostgreSQL o de portería.
     * Si aun así no sale, queda un error en el log; para recuperarlo a mano dentro de las 24 h
     * siguientes a su llegada, poner `reencolos` en 0. La correlación es idempotente.
     */
    public function reencolarPendientes(): int
    {
        $pendientes = $this->eventos->pendientesSinCambiosDesde(
            now()->subMinutes(config('monitoreo.minutos_reencolar')), self::MAX_REENCOLOS, self::REENCOLAR_POR_CORRIDA,
        );

        foreach ($pendientes as $evento) {
            $intento = $this->eventos->anotarReencolo($evento);
            EventoRegistrado::dispatch($evento->id);

            if ($intento === self::MAX_REENCOLOS) {
                Log::error('Último reencolado de un evento que sigue sin correlacionar; revisar si no sale', ['evento' => $evento->id]);
            }
        }

        if ($pendientes->isNotEmpty()) {
            Log::warning('Eventos pendientes reencolados', ['cantidad' => $pendientes->count()]);
        }

        return $pendientes->count();
    }
}
