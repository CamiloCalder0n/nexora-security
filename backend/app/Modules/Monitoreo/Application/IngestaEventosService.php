<?php

namespace App\Modules\Monitoreo\Application;

use App\Modules\Monitoreo\Domain\Events\EventoPendienteReencolado;
use App\Modules\Monitoreo\Domain\Events\EventoRegistrado;
use App\Modules\Monitoreo\Infrastructure\Repositories\CamaraRepository;
use App\Modules\Monitoreo\Infrastructure\Repositories\EventoRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

    /**
     * Red de seguridad: si un job se perdió (Redis reiniciado sin persistencia, worker caído),
     * el evento se queda en pendiente para siempre. Se reencola con espera creciente (2, 4, 8…
     * minutos) y como máximo `max_reencolos` veces; si aun así no sale, se registra un error
     * para revisarlo a mano. La correlación es idempotente.
     */
    public function reencolarPendientes(): int
    {
        $minutos = config('monitoreo.minutos_reencolar');
        $maximo = config('monitoreo.max_reencolos');
        $pendientes = $this->eventos->pendientesParaReencolar(
            now()->subHours(24), now()->subMinutes($minutos), $maximo, config('monitoreo.reencolar_por_minuto'),
        );

        foreach ($pendientes as $evento) {
            $intento = ($evento->reencolos ?? 0) + 1;
            $this->eventos->anotarReencolo($evento, now()->addMinutes($minutos * 2 ** $intento));
            EventoPendienteReencolado::dispatch($evento->id);

            if ($intento === $maximo) {
                Log::error('Último reencolado de un evento que sigue sin correlacionar; revisar si no sale', ['evento' => $evento->id]);
            }
        }

        if ($pendientes->isNotEmpty()) {
            Log::warning('Eventos pendientes reencolados', ['cantidad' => $pendientes->count()]);
        }

        return $pendientes->count();
    }
}
