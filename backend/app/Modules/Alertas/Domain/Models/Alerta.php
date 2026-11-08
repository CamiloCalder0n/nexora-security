<?php

namespace App\Modules\Alertas\Domain\Models;

use App\Modules\Alertas\Domain\Clasificacion;
use App\Modules\Alertas\Domain\EstadoAlerta;
use App\Modules\Alertas\Domain\TipoAlerta;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use MongoDB\Laravel\Eloquent\Model;

#[Connection('mongodb')]
#[Table('alertas')]
#[Fillable([
    'schema_version', 'fecha_hora', 'fecha_ultimo_evento', 'tipo', 'prioridad', 'motivo', 'regla', 'estado', 'nivel_escalamiento',
    'clasificacion', 'observacion', 'camara', 'zona', 'eventos_ids', 'num_eventos', 'evidencias_recientes',
    'atendida_por', 'fecha_atencion', 'fecha_cierre',
])]
class Alerta extends Model
{
    public const SCHEMA_VERSION = 1;

    public const MAX_EVIDENCIAS = 3;

    protected $attributes = [
        'schema_version' => self::SCHEMA_VERSION,
        'estado' => 'abierta',
        'nivel_escalamiento' => 0,
        'clasificacion' => null,
        'observacion' => null,
        'eventos_ids' => [],
        'num_eventos' => 0,
        'evidencias_recientes' => [],
        'atendida_por' => null,
        'fecha_atencion' => null,
        'fecha_cierre' => null,
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'fecha_ultimo_evento' => 'datetime',
            'fecha_atencion' => 'datetime',
            'fecha_cierre' => 'datetime',
            'prioridad' => 'integer',
            'nivel_escalamiento' => 'integer',
            'num_eventos' => 'integer',
            'tipo' => TipoAlerta::class,
            'estado' => EstadoAlerta::class,
            'clasificacion' => Clasificacion::class,
        ];
    }
}
