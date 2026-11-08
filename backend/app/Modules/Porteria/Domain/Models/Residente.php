<?php

namespace App\Modules\Porteria\Domain\Models;

use App\Models\User;
use App\Modules\Porteria\Domain\TipoResidente;
use App\Modules\Porteria\Infrastructure\Database\Factories\ResidenteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('residentes')]
#[UseFactory(ResidenteFactory::class)]
#[Fillable(['user_id', 'nombre', 'documento', 'telefono', 'tipo_residente'])]
class Residente extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tipo_residente' => TipoResidente::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'unidad_residente')
            ->withPivot('es_titular')
            ->withTimestamps();
    }

    public function autorizaciones(): HasMany
    {
        return $this->hasMany(Autorizacion::class);
    }
}
