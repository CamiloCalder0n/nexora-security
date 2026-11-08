<?php

namespace App\Modules\Alertas\Application\Reglas;

use App\Modules\Alertas\Domain\Reglas\ReglaDeteccion;
use App\Modules\Alertas\Domain\Reglas\ResultadoRegla;
use App\Modules\Monitoreo\Domain\Models\Evento;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Application\Contracts\ConsultaRegistrosPorteria;

/** Presencia en una zona vigilada sin un ingreso de portería en la ventana de correlación (HU01). */
class SinRegistroEnPorteria implements ReglaDeteccion
{
    public function __construct(private readonly ConsultaRegistrosPorteria $porteria) {}

    public function nombre(): string
    {
        return 'sin_registro_en_porteria';
    }

    public function aplica(Evento $evento): bool
    {
        return in_array($evento->tipo_objeto, ['persona', 'vehiculo'], true);
    }

    public function evaluar(Evento $evento, Zona $zona): ResultadoRegla
    {
        $registro = $this->porteria->buscarParaCorrelacion(
            $zona->id,
            $evento->fecha_hora,
            $zona->ventana_correlacion_min,
            $evento->cantidad,
        );

        if ($registro !== null) {
            return ResultadoRegla::explicado($registro->id);
        }

        return ResultadoRegla::alerta($this->motivo($evento, $zona), $zona->prioridad_base);
    }

    /** Ej.: "Persona en Parqueadero sótano a las 22:30 sin registro en portería (ventana ±10 min)" (HU38). */
    private function motivo(Evento $evento, Zona $zona): string
    {
        $quien = match (true) {
            $evento->cantidad > 1 && $evento->tipo_objeto === 'persona' => "{$evento->cantidad} personas",
            $evento->cantidad > 1 => "{$evento->cantidad} vehículos",
            $evento->tipo_objeto === 'persona' => 'Persona',
            default => 'Vehículo',
        };
        $hora = $evento->fecha_hora->copy()->setTimezone(config('app.timezone'))->format('H:i');

        return "{$quien} en {$zona->nombre} a las {$hora} sin registro en portería "
            ."(ventana ±{$zona->ventana_correlacion_min} min)";
    }
}
