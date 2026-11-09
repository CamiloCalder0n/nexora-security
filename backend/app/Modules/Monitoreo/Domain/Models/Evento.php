<?php

namespace App\Modules\Monitoreo\Domain\Models;

use App\Modules\Monitoreo\Domain\ResultadoEvento;
use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Detección que llega del agente. Los nombres de campos siguen el modelo de BD2.
 * camara y zona van embebidas (copia al momento del evento) para consultar sin ir a PostgreSQL.
 */
#[Connection('mongodb')]
#[Table('eventos')]
#[Fillable([
    'schema_version', 'uuid', 'fecha_hora', 'tipo_objeto', 'cantidad', 'confianza', 'ruta_evidencia',
    'resultado', 'explicado', 'camara', 'zona', 'registro_id', 'alerta_id', 'reencolos',
])]
class Evento extends Model
{
    public const SCHEMA_VERSION = 1;

    protected $attributes = [
        'schema_version' => self::SCHEMA_VERSION,
        'resultado' => 'pendiente',
        'explicado' => false,
        'registro_id' => null,
        'alerta_id' => null,
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'reencolos' => 'integer',
            'cantidad' => 'integer',
            'confianza' => 'float',
            'explicado' => 'boolean',
            'resultado' => ResultadoEvento::class,
        ];
    }
}
