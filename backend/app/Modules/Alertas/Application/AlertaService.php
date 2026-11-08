<?php

namespace App\Modules\Alertas\Application;

use App\Models\User;
use App\Modules\Alertas\Domain\Clasificacion;
use App\Modules\Alertas\Domain\EstadoAlerta;
use App\Modules\Alertas\Domain\Events\AlertaCreada;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\Reglas\ResultadoRegla;
use App\Modules\Alertas\Domain\TipoAlerta;
use App\Modules\Alertas\Domain\TransicionInvalida;
use App\Modules\Alertas\Infrastructure\Repositories\AlertaRepository;
use App\Modules\Monitoreo\Application\EventoService;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/** Ciclo de vida de la alerta: crear o agrupar, atender y cerrar. Cada cambio de estado va a la bitácora. */
class AlertaService
{
    public function __construct(
        private readonly AlertaRepository $alertas,
        private readonly EventoService $eventos,
        private readonly BitacoraService $bitacora,
    ) {}

    /**
     * Si la zona ya tiene una alerta abierta con un evento dentro de la ventana de silencio, el evento
     * se agrega ahí sin notificar (HU04). Si no, se crea una alerta nueva y se dispara AlertaCreada.
     */
    public function crearOAgrupar(Evento $evento, Zona $zona, string $regla, ResultadoRegla $resultado): Alerta
    {
        return $this->enLockDeZona($zona->id, function () use ($evento, $zona, $regla, $resultado) {
            // Reintento: el evento ya quedó en una alerta (aunque ya la hayan atendido o cerrado).
            $previa = $this->alertas->conEvento($evento->id);
            if ($previa !== null) {
                return $this->retomar($previa, $evento, $resultado->motivo);
            }

            $abierta = $this->alertas->abiertaQueCubre($zona->id, $evento->fecha_hora, $zona->ventana_silencio_min);

            if ($abierta !== null) {
                $this->agregarEvento($abierta, $evento);
                $this->eventos->marcar($evento, ResultadoEvento::Suprimido, ['alerta_id' => $abierta->id]);

                return $abierta;
            }

            $alerta = $this->alertas->crear([
                'fecha_hora' => $evento->fecha_hora,
                'fecha_ultimo_evento' => $evento->fecha_hora,
                'tipo' => TipoAlerta::SinRegistro,
                'prioridad' => $resultado->prioridad,
                'motivo' => $resultado->motivo,
                'regla' => $regla,
                'camara' => $evento->camara,
                'zona' => $evento->zona,
                'eventos_ids' => [$evento->id],
                'num_eventos' => 1,
                'evidencias_recientes' => array_values(array_filter([$evento->ruta_evidencia])),
            ]);

            return $this->completarCreacion($alerta, $evento, $resultado->motivo);
        });
    }

    /**
     * Pasos que siguen a crear la alerta. El evento se marca de último: si algo falla antes,
     * el job se reintenta, encuentra la alerta con este evento y retoma desde aquí (la
     * notificación puede salir dos veces, pero nunca cero).
     */
    private function completarCreacion(Alerta $alerta, Evento $evento, string $motivo): Alerta
    {
        if (! $this->bitacora->existe($alerta->id, 'alerta_creada')) {
            $this->bitacora->registrar('alerta_creada', $motivo, null, EstadoAlerta::Abierta->value, null, $alerta->id);
        }
        // Si ya la atendieron mientras el job esperaba el reintento, no se vuelve a avisar.
        if ($alerta->estado === EstadoAlerta::Abierta) {
            AlertaCreada::dispatch($alerta->id);
        }
        $this->eventos->marcar($evento, ResultadoEvento::Alerta, ['alerta_id' => $alerta->id]);

        return $alerta;
    }

    /** Reintento de un evento que ya quedó dentro de una alerta: termina lo que faltó sin duplicarlo. */
    private function retomar(Alerta $alerta, Evento $evento, string $motivo): Alerta
    {
        if ($alerta->eventos_ids[0] === $evento->id) {
            return $this->completarCreacion($alerta, $evento, $motivo);
        }
        $this->eventos->marcar($evento, ResultadoEvento::Suprimido, ['alerta_id' => $alerta->id]);

        return $alerta;
    }

    public function atender(Alerta $alerta, User $usuario): Alerta
    {
        return $this->enLockDeZona($alerta->zona['zona_id'], fn () => $this->atenderBloqueado($alerta->refresh(), $usuario));
    }

    private function atenderBloqueado(Alerta $alerta, User $usuario): Alerta
    {
        if ($alerta->estado !== EstadoAlerta::Abierta) {
            throw TransicionInvalida::desde($alerta->estado, 'atender');
        }

        $alerta->fill([
            'estado' => EstadoAlerta::Atendida,
            'atendida_por' => ['usuario_id' => $usuario->id, 'nombre' => $usuario->name],
            'fecha_atencion' => now(),
        ]);
        $this->alertas->guardar($alerta);
        $this->bitacora->registrar(
            'alerta_atendida', "Atendida por {$usuario->name}", EstadoAlerta::Abierta->value,
            EstadoAlerta::Atendida->value, $usuario, $alerta->id,
        );

        return $alerta;
    }

    /** HU03: no se cierra sin clasificar, y un incidente necesita observación. */
    public function cerrar(Alerta $alerta, User $usuario, ?string $clasificacion, ?string $observacion = null): Alerta
    {
        $tipo = Clasificacion::tryFrom((string) $clasificacion);
        $observacion = filled($observacion) ? trim($observacion) : null;

        if ($tipo === null) {
            throw ValidationException::withMessages([
                'clasificacion' => 'Para cerrar la alerta hay que clasificarla: falsa_alarma, sin_novedad o incidente.',
            ]);
        }
        if ($tipo === Clasificacion::Incidente && $observacion === null) {
            throw ValidationException::withMessages([
                'observacion' => 'Un incidente necesita una observación de lo que pasó.',
            ]);
        }

        return $this->enLockDeZona($alerta->zona['zona_id'], function () use ($alerta, $usuario, $tipo, $observacion) {
            return $this->cerrarBloqueado($alerta->refresh(), $usuario, $tipo, $observacion);
        });
    }

    private function cerrarBloqueado(Alerta $alerta, User $usuario, Clasificacion $tipo, ?string $observacion): Alerta
    {
        if ($alerta->estado === EstadoAlerta::Cerrada) {
            throw TransicionInvalida::desde($alerta->estado, 'cerrar');
        }

        $anterior = $alerta->estado;
        $alerta->fill([
            'estado' => EstadoAlerta::Cerrada,
            'clasificacion' => $tipo,
            'observacion' => $observacion,
            'fecha_cierre' => now(),
        ]);
        $this->alertas->guardar($alerta);
        $this->bitacora->registrar(
            'alerta_cerrada', "Cerrada como {$tipo->value}".($observacion ? ": {$observacion}" : ''),
            $anterior->value, EstadoAlerta::Cerrada->value, $usuario, $alerta->id,
        );

        return $alerta;
    }

    /**
     * Crear, agrupar, atender, cerrar (y escalar) una alerta de la zona pasan de a uno: así un evento
     * no se agrupa en una alerta que se está cerrando en ese mismo instante.
     */
    private function enLockDeZona(int $zonaId, Closure $accion): mixed
    {
        return Cache::lock("alertas:zona:{$zonaId}", 10)->block(5, $accion);
    }

    private function agregarEvento(Alerta $alerta, Evento $evento): void
    {
        $evidencias = $alerta->evidencias_recientes;
        if ($evento->ruta_evidencia) {
            $evidencias = array_slice([...$evidencias, $evento->ruta_evidencia], -Alerta::MAX_EVIDENCIAS);
        }

        $alerta->fill([
            'eventos_ids' => [...$alerta->eventos_ids, $evento->id],
            'num_eventos' => $alerta->num_eventos + 1,
            'evidencias_recientes' => $evidencias,
            // Un evento viejo (cola del agente tras un corte) no hace retroceder la alerta.
            'fecha_ultimo_evento' => $evento->fecha_hora->max($alerta->fecha_ultimo_evento),
        ]);
        $this->alertas->guardar($alerta);
    }
}
