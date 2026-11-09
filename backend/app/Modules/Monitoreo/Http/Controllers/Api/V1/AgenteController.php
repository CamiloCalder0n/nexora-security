<?php

namespace App\Modules\Monitoreo\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Monitoreo\Application\CamaraService;
use App\Modules\Monitoreo\Application\IngestaEventosService;
use App\Modules\Monitoreo\Domain\Models\Camara;
use App\Modules\Monitoreo\Http\Requests\StoreEventoRequest;
use App\Modules\Monitoreo\Http\Resources\CamaraAgenteResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Endpoints que consume el agente de borde (docs/CONTRATO_API_AGENTE.md). */
class AgenteController extends Controller
{
    public function camaras(CamaraService $camaras): AnonymousResourceCollection
    {
        return CamaraAgenteResource::collection($camaras->paraAgente());
    }

    /** 202 si quedó en cola para correlacionar; 200 si el uuid ya se había recibido. */
    public function eventos(StoreEventoRequest $request, IngestaEventosService $ingesta): JsonResponse
    {
        $nuevo = $ingesta->registrar($request->validated());

        return response()->json(
            ['data' => ['uuid' => $request->validated('uuid'), 'estado' => $nuevo ? 'recibido' : 'duplicado']],
            $nuevo ? Response::HTTP_ACCEPTED : Response::HTTP_OK,
        );
    }

    public function heartbeat(Camara $camara, CamaraService $camaras): Response
    {
        $camaras->registrarLatido($camara);

        return response()->noContent();
    }
}
