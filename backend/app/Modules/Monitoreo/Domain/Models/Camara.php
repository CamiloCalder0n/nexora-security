<?php

namespace App\Modules\Monitoreo\Domain\Models;

use App\Modules\Monitoreo\Domain\EstadoCamara;
use App\Modules\Monitoreo\Infrastructure\Database\Factories\CamaraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('camaras')]
#[UseFactory(CamaraFactory::class)]
#[Fillable(['zona_id', 'nombre', 'url_stream', 'estado', 'ultima_conexion'])]
class Camara extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoCamara::class,
            'ultima_conexion' => 'datetime',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }
}
