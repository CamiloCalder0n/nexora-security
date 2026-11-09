<?php

namespace App\Modules\Porteria\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Application\ResidenteService;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Http\Requests\StoreAutorizacionRequest;
use App\Modules\Porteria\Http\Requests\UpdateAutorizacionRequest;
use App\Modules\Porteria\Http\Resources\AutorizacionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AutorizacionController extends Controller
{
    public function __construct(
        private readonly AutorizacionService $autorizaciones,
        private readonly ResidenteService $residentes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Autorizacion::class);

        $usuario = $request->user();
        $filtros = $request->only('search', 'estado', 'unidad_id');

        $pagina = match ($usuario->rol) {
            Rol::Admin => $this->autorizaciones->listar($filtros),
            Rol::Vigilante => $this->autorizaciones->listar([...$filtros, 'vigentes' => true]),
            default => $this->autorizaciones->listarDeResidente($usuario->residente, $filtros),
        };

        return AutorizacionResource::collection($pagina);
    }

    public function store(StoreAutorizacionRequest $request): JsonResponse
    {
        Gate::authorize('create', Autorizacion::class);

        $usuario = $request->user();
        $residente = $usuario->rol === Rol::Admin
            ? $this->residentes->buscar($request->integer('residente_id'))
            : $usuario->residente;

        $autorizacion = $this->autorizaciones->crear($residente, $request->datos());

        return (new AutorizacionResource($autorizacion->load(['unidad', 'residente'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Autorizacion $autorizacion): AutorizacionResource
    {
        Gate::authorize('view', $autorizacion);

        return new AutorizacionResource($autorizacion->load(['unidad', 'residente']));
    }

    public function update(UpdateAutorizacionRequest $request, Autorizacion $autorizacion): AutorizacionResource
    {
        Gate::authorize('update', $autorizacion);

        $autorizacion = $this->autorizaciones->actualizar($autorizacion, $request->datos());

        return new AutorizacionResource($autorizacion->load(['unidad', 'residente']));
    }

    /** Eliminar una autorización es anularla: queda en el historial. */
    public function destroy(Autorizacion $autorizacion): Response
    {
        Gate::authorize('delete', $autorizacion);

        $this->autorizaciones->anular($autorizacion);

        return response()->noContent();
    }
}
