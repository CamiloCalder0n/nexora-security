<?php

namespace App\Modules\Identidad\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro de solo inserción: no se edita ni se borra desde la aplicación. */
class AccesoAuditado extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auditoria_accesos';

    protected $fillable = ['user_id', 'recurso', 'recurso_id', 'motivo', 'ip'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
