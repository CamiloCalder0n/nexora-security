<?php

namespace App\Modules\Alertas\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Alertas\Application\AlertaService;
use App\Modules\Alertas\Domain\Models\Alerta;
use App\Modules\Alertas\Domain\TransicionInvalida;
use App\Modules\Alertas\Http\Requests\CerrarAlertaRequest;
use App\Modules\Alertas\Http\Resources\AlertaResource;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Panel del vigilante: la cola de alertas activas, atender y cerrar (HU11, HU03). */
class AlertaController extends Controller
{
    public function __construct(private readonly AlertaService $alertas) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autorizar($request);

        return AlertaResource::collection($this->alertas->activas());
    }

    public function atender(Request $request, Alerta $alerta): AlertaResource|JsonResponse
    {
        $this->autorizar($request);

        return $this->cambiar(fn () => $this->alertas->atender($alerta, $request->user()));
    }

    /** El permiso lo revisa CerrarAlertaRequest antes de validar. */
    public function cerrar(CerrarAlertaRequest $request, Alerta $alerta): AlertaResource|JsonResponse
    {
        return $this->cambiar(fn () => $this->alertas->cerrar(
            $alerta, $request->user(), $request->validated('clasificacion'), $request->validated('observacion'),
        ));
    }

    /** Vigilante o administración con la cuenta activa: la misma regla que en Telegram. */
    private function autorizar(Request $request): void
    {
        abort_unless($this->alertas->puedeAtender($request->user()), Response::HTTP_FORBIDDEN, AlertaService::SIN_PERMISO);
    }

    /**
     * 409 si el estado ya no lo permite (otro la atendió o la cerró): el panel recarga la alerta.
     * 503 con Retry-After si la zona está ocupada un instante: el panel reintenta.
     */
    private function cambiar(Closure $cambio): AlertaResource|JsonResponse
    {
        try {
            return new AlertaResource($cambio());
        } catch (TransicionInvalida $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'La alerta se está actualizando; intenta de nuevo.'], Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => 2]);
        }
    }
}
