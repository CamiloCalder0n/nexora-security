<?php

namespace App\Modules\Porteria\Domain\Models;

use App\Modules\Porteria\Infrastructure\Database\Factories\UnidadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('unidades')]
#[UseFactory(UnidadFactory::class)]
#[Fillable(['torre', 'numero'])]
class Unidad extends Model
{
    use HasFactory;

    public function residentes(): BelongsToMany
    {
        return $this->belongsToMany(Residente::class, 'unidad_residente')
            ->withPivot('es_titular')
            ->withTimestamps();
    }

    public function autorizaciones(): HasMany
    {
        return $this->hasMany(Autorizacion::class);
    }

    public function registrosPorteria(): HasMany
    {
        return $this->hasMany(RegistroPorteria::class);
    }

    protected function etiqueta(): Attribute
    {
        return Attribute::get(fn () => "Torre {$this->torre} - {$this->numero}");
    }
}
