<?php

namespace App\Modules\Porteria\Domain\Models;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Table('autorizaciones')]
#[Fillable([
    'unidad_id', 'residente_id', 'visitante_nombre', 'visitante_documento', 'placa',
    'valida_desde', 'valida_hasta', 'estado', 'usada_at',
])]
class Autorizacion extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoAutorizacion::class,
            'valida_desde' => 'datetime',
            'valida_hasta' => 'datetime',
            'usada_at' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function residente(): BelongsTo
    {
        return $this->belongsTo(Residente::class);
    }

    public function registroPorteria(): HasOne
    {
        return $this->hasOne(RegistroPorteria::class);
    }

    /** Estado vigente y sin vencer (incluye las que empiezan más adelante). */
    #[Scope]
    protected function vigentes(Builder $query, ?CarbonInterface $momento = null): void
    {
        $query->where('estado', EstadoAutorizacion::Vigente)
            ->where('valida_hasta', '>=', $momento ?? now());
    }
}
