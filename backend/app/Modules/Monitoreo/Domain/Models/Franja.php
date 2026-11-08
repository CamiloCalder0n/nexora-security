<?php

namespace App\Modules\Monitoreo\Domain\Models;

use App\Modules\Monitoreo\Infrastructure\Database\Factories\FranjaFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('franjas')]
#[UseFactory(FranjaFactory::class)]
#[Fillable(['zona_id', 'dia_semana', 'hora_inicio', 'hora_fin'])]
class Franja extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    /**
     * Si hora_fin < hora_inicio la franja cruza la medianoche: la parte de la
     * madrugada pertenece al día en que empezó. Si son iguales cubre el día completo.
     */
    public function cubre(CarbonInterface $momento): bool
    {
        $momento = $momento->copy()->setTimezone(config('app.timezone'));
        $hora = $momento->format('H:i:s');
        $dia = $momento->dayOfWeek;

        $inicio = self::normalizarHora($this->hora_inicio);
        $fin = self::normalizarHora($this->hora_fin);

        if ($inicio === $fin) {
            return $this->aplicaEnDia($dia);
        }

        if ($inicio < $fin) {
            return $this->aplicaEnDia($dia) && $hora >= $inicio && $hora < $fin;
        }

        if ($hora >= $inicio) {
            return $this->aplicaEnDia($dia);
        }

        return $hora < $fin && $this->aplicaEnDia(($dia + 6) % 7);
    }

    private function aplicaEnDia(int $dia): bool
    {
        return $this->dia_semana === null || $this->dia_semana === $dia;
    }

    private static function normalizarHora(string $hora): string
    {
        return date('H:i:s', strtotime($hora));
    }
}
