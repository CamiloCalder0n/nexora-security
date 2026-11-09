<?php

namespace Tests\Concerns;

use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Monitoreo\Domain\Models\Evento;
use Illuminate\Support\Str;

/** Documentos que cumplen el validador de Mongo, para tests que no pasan por el flujo completo. */
trait DocumentosMongo
{
    protected function crearEvento(array $datos = []): Evento
    {
        return Evento::create([
            'uuid' => (string) Str::uuid(), 'fecha_hora' => now(), 'tipo_objeto' => 'persona',
            'cantidad' => 1, 'confianza' => 0.9,
            'camara' => ['camara_id' => 1, 'nombre' => 'CAM-01'], 'zona' => ['zona_id' => 1, 'nombre' => 'Parqueadero'],
            ...$datos,
        ]);
    }

    protected function crearAlerta(array $datos = []): Alerta
    {
        return Alerta::create([
            'fecha_hora' => now(), 'tipo' => 'sin_registro', 'prioridad' => 3, 'motivo' => 'Persona sin registro',
            'regla' => 'sin_registro_en_porteria', 'zona' => ['zona_id' => 1, 'nombre' => 'Parqueadero'],
            ...$datos,
        ]);
    }
}
