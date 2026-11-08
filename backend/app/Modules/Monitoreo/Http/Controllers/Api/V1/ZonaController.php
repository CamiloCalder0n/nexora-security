<?php

namespace App\Modules\Monitoreo\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Monitoreo\Application\ZonaService;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Http\Requests\StoreZonaRequest;
use App\Modules\Monitoreo\Http\Requests\UpdateZonaRequest;
use App\Modules\Monitoreo\Http\Resources\ZonaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ZonaController extends Controller
{
    public function __construct(private readonly ZonaService $zonas) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ZonaResource::collection($this->zonas->listar($request->only('search', 'tipo', 'activa')));
    }

    public function store(StoreZonaRequest $request): JsonResponse
    {
        return (new ZonaResource($this->zonas->crear($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Zona $zona): ZonaResource
    {
        return new ZonaResource($this->zonas->detalle($zona));
    }

    public function update(UpdateZonaRequest $request, Zona $zona): ZonaResource
    {
        return new ZonaResource($this->zonas->actualizar($zona, $request->validated()));
    }

    public function destroy(Zona $zona): Response
    {
        $this->zonas->eliminar($zona);

        return response()->noContent();
    }
}
