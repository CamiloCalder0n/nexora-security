<?php

namespace App\Modules\Porteria\Application;

use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Exceptions\AutorizacionFueraDeVentana;
use App\Modules\Porteria\Domain\Exceptions\AutorizacionNoVigente;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Infrastructure\Repositories\AutorizacionRepository;
use App\Modules\Porteria\Infrastructure\Repositories\ResidenteRepository;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AutorizacionService
{
    public const MAX_DIAS = 7;

    public function __construct(
        private readonly AutorizacionRepository $autorizaciones,
        private readonly ResidenteRepository $residentes,
    ) {}

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->autorizaciones->paginar($filtros);
    }

    public function listarDeResidente(Residente $residente, array $filtros = []): LengthAwarePaginator
    {
        $filtros['unidad_ids'] = $this->residentes->idsUnidades($residente);

        return $this->autorizaciones->paginar($filtros);
    }

    public function buscar(int $id): Autorizacion
    {
        return $this->autorizaciones->buscar($id);
    }

    /** Las que el vigilante puede usar ahora mismo para registrar un ingreso. */
    public function vigentesDeUnidad(int $unidadId): Collection
    {
        return $this->autorizaciones->vigentesDeUnidad($unidadId);
    }

    public function crear(Residente $residente, array $datos): Autorizacion
    {
        if (! $this->residentes->perteneceUnidad($residente, (int) $datos['unidad_id'])) {
            throw new AuthorizationException('La unidad no pertenece al residente.');
        }

        $this->validarVentana($datos['valida_desde'], $datos['valida_hasta']);

        return $this->autorizaciones->crear([
            ...$datos,
            'residente_id' => $residente->id,
            'estado' => EstadoAutorizacion::Vigente,
        ]);
    }

    public function actualizar(Autorizacion $autorizacion, array $datos): Autorizacion
    {
        $this->exigirVigente($autorizacion, 'Solo se puede modificar una autorización vigente.');

        $this->validarVentana(
            $datos['valida_desde'] ?? $autorizacion->valida_desde,
            $datos['valida_hasta'] ?? $autorizacion->valida_hasta,
        );

        return $this->autorizaciones->actualizar($autorizacion, $datos);
    }

    public function anular(Autorizacion $autorizacion): Autorizacion
    {
        $this->exigirVigente($autorizacion, 'Solo se puede anular una autorización vigente.');

        return $this->autorizaciones->actualizar($autorizacion, ['estado' => EstadoAutorizacion::Anulada]);
    }

    /**
     * Marca la autorización como usada. Si venció la deja expirada; quien la llame
     * dentro de una transacción debe persistir ese cambio tras el rollback.
     */
    public function consumir(Autorizacion $autorizacion, CarbonInterface $momento): Autorizacion
    {
        $autorizacion = $this->autorizaciones->bloquear($autorizacion);

        if (in_array($autorizacion->estado, [EstadoAutorizacion::Usada, EstadoAutorizacion::Anulada], true)) {
            throw new AutorizacionNoVigente("La autorización ya está {$autorizacion->estado->value}.");
        }

        if ($autorizacion->estado === EstadoAutorizacion::Expirada || $momento->gt($autorizacion->valida_hasta)) {
            $this->marcarExpirada($autorizacion);
            throw new AutorizacionFueraDeVentana($autorizacion, expiro: true);
        }

        if ($momento->lt($autorizacion->valida_desde)) {
            throw new AutorizacionFueraDeVentana($autorizacion, expiro: false);
        }

        return $this->autorizaciones->actualizar($autorizacion, [
            'estado' => EstadoAutorizacion::Usada,
            'usada_at' => $momento,
        ]);
    }

    public function marcarExpirada(Autorizacion $autorizacion): void
    {
        $this->autorizaciones->cambiarEstado($autorizacion, EstadoAutorizacion::Expirada);
    }

    public function expirarVencidas(): int
    {
        return $this->autorizaciones->expirarVencidas(now());
    }

    private function exigirVigente(Autorizacion $autorizacion, string $mensaje): void
    {
        if ($autorizacion->estado !== EstadoAutorizacion::Vigente) {
            throw new AutorizacionNoVigente($mensaje);
        }
    }

    private function validarVentana(mixed $desde, mixed $hasta): void
    {
        $desde = Carbon::parse($desde);
        $hasta = Carbon::parse($hasta);

        if ($hasta->lte($desde)) {
            throw ValidationException::withMessages([
                'valida_hasta' => 'La fecha final debe ser posterior a la inicial.',
            ]);
        }

        if ($desde->diffInMinutes($hasta) > self::MAX_DIAS * 24 * 60) {
            throw ValidationException::withMessages([
                'valida_hasta' => 'Una autorización no puede durar más de '.self::MAX_DIAS.' días.',
            ]);
        }
    }
}
