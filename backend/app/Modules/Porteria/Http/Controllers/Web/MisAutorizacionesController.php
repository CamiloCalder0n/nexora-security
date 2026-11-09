<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Domain\EstadoAutorizacion;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\Models\Unidad;
use App\Modules\Porteria\Http\Requests\FiltroMisAutorizacionesRequest;
use App\Modules\Porteria\Http\Requests\StoreAutorizacionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** HU09 y HU10: el residente autoriza visitas y solo ve las de sus unidades. */
class MisAutorizacionesController extends Controller
{
    private const SIN_RESIDENTE = 'Tu cuenta no está vinculada a un residente. Pide a la administración que la asocie a tu unidad.';

    public function __construct(private readonly AutorizacionService $autorizaciones) {}

    public function index(FiltroMisAutorizacionesRequest $request): View
    {
        $residente = $this->residente();

        return view('porteria::mis-autorizaciones.index', [
            'residente' => $residente,
            'autorizaciones' => $residente ? $this->autorizaciones->listarDeResidente($residente, $request->filtros()) : null,
            'estados' => collect(EstadoAutorizacion::cases())->mapWithKeys(fn ($e) => [$e->value => ucfirst($e->value)])->all(),
            'sinResidente' => self::SIN_RESIDENTE,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $residente = $this->residente();
        if (! $residente) {
            return $this->sinResidente();
        }

        Gate::authorize('create', Autorizacion::class);

        return view('porteria::mis-autorizaciones.create', [
            'unidades' => $residente->unidades()->orderBy('torre')->orderBy('numero')->get()
                ->mapWithKeys(fn (Unidad $u) => [$u->id => $u->etiqueta])->all(),
            'maxDias' => AutorizacionService::MAX_DIAS,
        ]);
    }

    public function store(StoreAutorizacionRequest $request): RedirectResponse
    {
        $residente = $this->residente();
        if (! $residente) {
            return $this->sinResidente();
        }

        Gate::authorize('create', Autorizacion::class);

        $autorizacion = $this->autorizaciones->crear($residente, $request->datos());

        return redirect()->route('panel.porteria.mis-autorizaciones.index')
            ->with('exito', "Autorización creada para {$autorizacion->visitante_nombre}.");
    }

    /** Anular: la autorización queda en el historial. */
    public function destroy(Autorizacion $autorizacion): RedirectResponse
    {
        Gate::authorize('delete', $autorizacion);

        $this->autorizaciones->anular($autorizacion);

        return redirect()->route('panel.porteria.mis-autorizaciones.index')
            ->with('exito', "Autorización de {$autorizacion->visitante_nombre} anulada.");
    }

    private function residente(): ?Residente
    {
        return auth()->user()->residente;
    }

    /** El listado ya explica el caso; no se repite el mensaje como flash. */
    private function sinResidente(): RedirectResponse
    {
        return redirect()->route('panel.porteria.mis-autorizaciones.index');
    }
}
