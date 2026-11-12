<?php

namespace App\Modules\Identidad\Application;

use App\Models\User;
use App\Modules\Identidad\Domain\Models\AccesoAuditado;
use App\Modules\Identidad\Infrastructure\Repositories\AuditoriaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * RF007: todo acceso a datos personales (por ahora, la evidencia de un evento) queda
 * registrado antes de mostrarse. Otros módulos llaman a registrar(); solo el admin consulta.
 */
class AuditoriaService
{
    public const RECURSO_EVIDENCIA = 'evidencia';

    public const RECURSO_ACCESO_DENEGADO = 'acceso_denegado';

    public const RECURSOS = [self::RECURSO_EVIDENCIA => 'Evidencia', self::RECURSO_ACCESO_DENEGADO => 'Acceso denegado'];

    public const LIMITE_CSV = 5000;

    public function __construct(private readonly AuditoriaRepository $auditoria) {}

    public function registrar(User $usuario, string $recurso, string $recursoId, string $motivo, ?string $ip = null): AccesoAuditado
    {
        return $this->auditoria->crear([
            'user_id' => $usuario->id,
            'recurso' => $recurso,
            'recurso_id' => $recursoId,
            'motivo' => trim($motivo),
            'ip' => $ip,
        ]);
    }

    /** CU10: intento de entrar a una función que el rol no tiene. */
    public function registrarRechazo(User $usuario, string $recurso, ?string $ip = null): AccesoAuditado
    {
        $motivo = "El rol {$usuario->rol->value} no tiene permiso para esta función.";

        return $this->registrar($usuario, self::RECURSO_ACCESO_DENEGADO, mb_substr($recurso, 0, 64), $motivo, $ip);
    }

    /** CU07: exportar el registro de auditoría con los mismos filtros de la pantalla. */
    public function filasCsv(array $filtros = []): array
    {
        $filas = [['Fecha', 'Usuario', 'Rol', 'Recurso', 'Id', 'Motivo', 'IP']];
        foreach ($this->auditoria->buscar($filtros, self::LIMITE_CSV) as $a) {
            $filas[] = [
                $a->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                $a->usuario?->name, $a->usuario?->rol->value, $a->recurso, $a->recurso_id, $a->motivo, $a->ip,
            ];
        }

        return $filas;
    }

    public function listar(array $filtros = []): LengthAwarePaginator
    {
        return $this->auditoria->paginar($filtros);
    }

    public function accesosUltimosDias(int $dias = 30): int
    {
        return $this->auditoria->contarDesde(now()->subDays($dias - 1)->startOfDay());
    }
}
