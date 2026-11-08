<?php

namespace App\Modules\Monitoreo\Domain\Models;

use App\Modules\Monitoreo\Domain\TipoZona;
use App\Modules\Monitoreo\Infrastructure\Database\Factories\ZonaFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('zonas')]
#[UseFactory(ZonaFactory::class)]
#[Fillable(['nombre', 'tipo', 'prioridad_base', 'ventana_correlacion_min', 'ventana_silencio_min', 'activa'])]
class Zona extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo' => TipoZona::class,
            'prioridad_base' => 'integer',
            'ventana_correlacion_min' => 'integer',
            'ventana_silencio_min' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function camaras(): HasMany
    {
        return $this->hasMany(Camara::class);
    }

    public function franjas(): HasMany
    {
        return $this->hasMany(Franja::class);
    }

    public function estaVigilada(CarbonInterface $momento): bool
    {
        if (! $this->activa) {
            return false;
        }

        return $this->franjas->contains(fn (Franja $franja) => $franja->cubre($momento));
    }
}
