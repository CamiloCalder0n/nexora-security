<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Application\ResidenteService;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Http\Requests\FiltroAutorizacionesRequest;
use App\Modules\Porteria\Http\Requests\StoreAutorizacionRequest;
use App\Modules\Porteria\Http\Requests\UpdateAutorizacionRequest;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Panel del administrador: gestiona las autorizaciones de visita de todo el conjunto. */
class AutorizacionController extends Controller
{
    public function __construct(
        private readonly AutorizacionService $autorizaciones,
        private readonly ResidenteService $residentes,
    ) {}

    public function index(FiltroAutorizacionesRequest $request): View
    {
        Gate::authorize('viewAny', Autorizacion::class);

        return view('porteria::autorizaciones.index', [
            'autorizaciones' => $this->autorizaciones->listar($request->filtros()),
            'estados' => collect(EstadoAutorizacion::cases())->mapWithKeys(fn ($e) => [$e->value => ucfirst($e->value)])->all(),
            'unidades' => $this->residentes->unidades()->pluck('etiqueta', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Autorizacion::class);

        return view('porteria::autorizaciones.create', $this->residentes->opcionesParaAutorizar());
    }

    public function store(StoreAutorizacionRequest $request): RedirectResponse
    {
        Gate::authorize('create', Autorizacion::class);

        $residente = $this->residentes->buscar($request->integer('residente_id'));

        try {
            $autorizacion = $this->autorizaciones->crear($residente, $request->datos());
        } catch (AuthorizationException) {
            return back()->withInput()->withErrors(['unidad_id' => 'La unidad no pertenece al residente seleccionado.']);
        }

        return redirect()->route('panel.porteria.autorizaciones.show', $autorizacion)
            ->with('exito', 'Autorización creada.');
    }

    public function show(Autorizacion $autorizacion): View
    {
        Gate::authorize('view', $autorizacion);

        return view('porteria::autorizaciones.show', [
            'autorizacion' => $autorizacion->load(['unidad', 'residente']),
        ]);
    }

    public function edit(Autorizacion $autorizacion): View|RedirectResponse
    {
        Gate::authorize('update', $autorizacion);

        if ($autorizacion->estado !== EstadoAutorizacion::Vigente) {
            return $this->noModificable($autorizacion);
        }

        return view('porteria::autorizaciones.edit', [
            'autorizacion' => $autorizacion->load(['unidad', 'residente']),
        ]);
    }

    public function update(UpdateAutorizacionRequest $request, Autorizacion $autorizacion): RedirectResponse
    {
        Gate::authorize('update', $autorizacion);

        if ($autorizacion->estado !== EstadoAutorizacion::Vigente) {
            return $this->noModificable($autorizacion);
        }

        $this->autorizaciones->actualizar($autorizacion, $request->datos());

        return redirect()->route('panel.porteria.autorizaciones.show', $autorizacion)
            ->with('exito', 'Autorización actualizada.');
    }

    /** Anula la autorización: no se borra, queda en el historial. */
    public function destroy(Autorizacion $autorizacion): RedirectResponse
    {
        Gate::authorize('delete', $autorizacion);

        $this->autorizaciones->anular($autorizacion);

        return redirect()->route('panel.porteria.autorizaciones.index')
            ->with('exito', 'Autorización anulada.');
    }

    private function noModificable(Autorizacion $autorizacion): RedirectResponse
    {
        return redirect()->route('panel.porteria.autorizaciones.show', $autorizacion)
            ->with('error', 'Solo se puede modificar una autorización vigente.');
    }
}
