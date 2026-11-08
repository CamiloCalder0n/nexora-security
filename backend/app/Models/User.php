<?php

namespace App\Models;

use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Residente;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'rol', 'activo', 'telefono', 'telegram_chat_id', 'telegram_codigo'])]
#[Hidden(['password', 'remember_token', 'telegram_codigo'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'activo' => 'boolean',
        ];
    }

    public function residente(): HasOne
    {
        return $this->hasOne(Residente::class);
    }

    public function registrosPorteria(): HasMany
    {
        return $this->hasMany(RegistroPorteria::class, 'registrado_por');
    }
}
