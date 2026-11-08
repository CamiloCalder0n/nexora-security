<?php

namespace App\Modules\Porteria\Domain\Models;

use App\Models\User;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Porteria\Domain\TipoIngreso;
use App\Modules\Porteria\Infrastructure\Database\Factories\RegistroPorteriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('registros_porteria')]
#[UseFactory(RegistroPorteriaFactory::class)]
#[Fillable([
    'tipo_ingreso', 'nombre_visitante', 'documento', 'placa', 'cantidad_personas',
    'unidad_id', 'zona_id', 'registrado_por', 'autorizacion_id', 'fecha_ingreso', 'fecha_salida',
])]
class RegistroPorteria extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo_ingreso' => TipoIngreso::class,
            'cantidad_personas' => 'integer',
            'fecha_ingreso' => 'datetime',
            'fecha_salida' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function vigilante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function autorizacion(): BelongsTo
    {
        return $this->belongsTo(Autorizacion::class);
    }
}
