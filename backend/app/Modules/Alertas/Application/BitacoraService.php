<?php

namespace App\Modules\Alertas\Application;

use App\Models\User;
use App\Modules\Alertas\Domain\Models\EntradaBitacora;
use Illuminate\Support\Facades\Cache;

/**
 * Bitácora encadenada (HU28): cada entrada guarda el hash de la anterior y el suyo cubre todos
 * sus campos, así que cambiar o borrar una entrada vieja rompe la cadena y `nexora:verificar-bitacora`
 * lo detecta. Lo que una cadena de hashes no detecta es que borren las últimas entradas.
 */
class BitacoraService
{
    public const HASH_INICIAL = '0000000000000000000000000000000000000000000000000000000000000000';

    public function registrar(
        string $tipo,
        string $descripcion,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?User $usuario = null,
        ?string $alertaId = null,
    ): EntradaBitacora {
        // Un solo escritor a la vez: dos workers no pueden tomar el mismo "anterior".
        return Cache::lock('alertas:bitacora', 10)->block(5, function () use (
            $tipo, $descripcion, $estadoAnterior, $estadoNuevo, $usuario, $alertaId
        ) {
            $anterior = EntradaBitacora::orderBy('secuencia', 'desc')->first();
            $fecha = now()->startOfSecond();
            $datosUsuario = $usuario
                ? ['usuario_id' => $usuario->id, 'nombre' => $usuario->name, 'rol' => $usuario->rol->value]
                : ['usuario_id' => null, 'nombre' => 'Sistema', 'rol' => 'sistema'];
            $entrada = new EntradaBitacora([
                'secuencia' => ($anterior->secuencia ?? 0) + 1,
                'fecha_hora' => $fecha,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'usuario' => $datosUsuario,
                'alerta_id' => $alertaId,
                'hash_anterior' => $anterior->hash ?? self::HASH_INICIAL,
            ]);
            $entrada->hash = self::hash($entrada);
            $entrada->save();

            return $entrada;
        });
    }

    public function existe(string $alertaId, string $tipo): bool
    {
        return EntradaBitacora::where('alerta_id', $alertaId)->where('tipo', $tipo)->exists();
    }

    /** JSON de todos los campos en orden fijo: el separador evita que un texto se corra al campo vecino. */
    private static function hash(EntradaBitacora $entrada): string
    {
        return hash('sha256', json_encode([
            $entrada->hash_anterior,
            $entrada->secuencia,
            $entrada->fecha_hora->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            $entrada->tipo,
            $entrada->descripcion,
            $entrada->estado_anterior,
            $entrada->estado_nuevo,
            $entrada->usuario['usuario_id'] ?? null,
            $entrada->usuario['nombre'] ?? null,
            $entrada->usuario['rol'] ?? null,
            $entrada->alerta_id,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Recorre la cadena en orden.
     *
     * @return array{entradas: int, rota: ?EntradaBitacora} la primera entrada que no cuadra, o null si todo está bien
     */
    public function verificar(): array
    {
        $hashAnterior = self::HASH_INICIAL;
        $esperada = 1;
        $entradas = 0;

        foreach (EntradaBitacora::orderBy('secuencia')->cursor() as $entrada) {
            $entradas++;
            if ($entrada->secuencia !== $esperada || $entrada->hash_anterior !== $hashAnterior || $entrada->hash !== self::hash($entrada)) {
                return ['entradas' => $entradas, 'rota' => $entrada];
            }

            $hashAnterior = $entrada->hash;
            $esperada++;
        }

        return ['entradas' => $entradas, 'rota' => null];
    }
}
