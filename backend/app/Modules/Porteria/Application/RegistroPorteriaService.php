<?php

namespace App\Modules\Porteria\Application;

use App\Models\User;
use App\Modules\Porteria\Domain\Events\IngresoRegistrado;
use App\Modules\Porteria\Domain\Exceptions\AutorizacionFueraDeVentana;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Infrastructure\Repositories\RegistroPorteriaRepository;
use App\Modules\Porteria\Infrastructure\Repositories\ResidenteRepository;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegistroPorteriaService
{
    public function __construct(
        private readonly RegistroPorteriaRepository $registros,
        private readonly AutorizacionService $autorizaciones,
        private readonly ResidenteRepository $residentes,
    ) {}

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->registros->paginar($filtros);
    }

    public function visitasDeResidente(Residente $residente, array $filtros = []): LengthAwarePaginator
    {
        $filtros['unidad_ids'] = $this->residentes->idsUnidades($residente);

        return $this->registros->paginar($filtros);
    }

    public function ingresosPorDia(int $dias = 30): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();
        $conteo = $this->registros->ingresosPorDia($desde);

        $serie = [];
        for ($dia = $desde->copy(); $dia->lte(now()); $dia->addDay()) {
            $serie[$dia->toDateString()] = $conteo[$dia->toDateString()] ?? 0;
        }

        return $serie;
    }

    public function registrar(User $vigilante, array $datos): RegistroPorteria
    {
        $momento = isset($datos['fecha_ingreso']) ? Carbon::parse($datos['fecha_ingreso']) : now();

        try {
            $registro = DB::transaction(function () use ($vigilante, $datos, $momento) {
                if (! empty($datos['autorizacion_id'])) {
                    $autorizacion = $this->autorizaciones->consumir($this->autorizaciones->buscar((int) $datos['autorizacion_id']), $momento);
                    $datos = [
                        'nombre_visitante' => $autorizacion->visitante_nombre,
                        'documento' => $autorizacion->visitante_documento,
                        'placa' => $autorizacion->placa,
                        ...array_filter($datos, fn ($valor) => $valor !== null && $valor !== ''),
                        'unidad_id' => $autorizacion->unidad_id,
                    ];
                }

                return $this->registros->crear([
                    ...$datos,
                    'registrado_por' => $vigilante->id,
                    'fecha_ingreso' => $momento,
                ]);
            });
        } catch (AutorizacionFueraDeVentana $e) {
            if ($e->expiro) {
                $this->autorizaciones->marcarExpirada($e->autorizacion);
            }
            throw $e;
        }

        IngresoRegistrado::dispatch($registro);

        return $registro;
    }

    public function registrarSalida(RegistroPorteria $registro, ?CarbonInterface $momento = null): RegistroPorteria
    {
        return $this->registros->marcarSalida($registro, $momento ?? now());
    }
}
