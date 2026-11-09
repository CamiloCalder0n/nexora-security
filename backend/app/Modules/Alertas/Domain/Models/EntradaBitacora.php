<?php

namespace App\Modules\Alertas\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Entrada de la bitácora; cada una guarda el hash de la anterior para detectar alteraciones (HU28).
 * `secuencia` es única: la cadena no se puede bifurcar aunque escriban dos workers a la vez.
 */
#[Connection('mongodb')]
#[Table('bitacora')]
#[Fillable([
    'schema_version', 'secuencia', 'fecha_hora', 'tipo', 'descripcion', 'estado_anterior', 'estado_nuevo',
    'usuario', 'alerta_id', 'hash_anterior', 'hash',
])]
class EntradaBitacora extends Model
{
    public const SCHEMA_VERSION = 1;

    protected $attributes = ['schema_version' => self::SCHEMA_VERSION];

    protected function casts(): array
    {
        return ['secuencia' => 'integer', 'fecha_hora' => 'datetime'];
    }
}
