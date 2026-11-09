<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Porteria\Application\ResidenteService;
use App\Modules\Porteria\Domain\Models\Residente;
use App\Modules\Porteria\Domain\TipoResidente;
use App\Modules\Porteria\Http\Requests\FiltroResidentesRequest;
use App\Modules\Porteria\Http\Requests\StoreResidenteRequest;
use App\Modules\Porteria\Http\Requests\UpdateResidenteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ResidenteController extends Controller
{
    public function __construct(private readonly ResidenteService $residentes) {}

    public function index(FiltroResidentesRequest $request): View
    {
        return view('porteria::residentes.index', [
            'residentes' => $this->residentes->listar($request->filtros()),
            ...$this->opciones(),
        ]);
    }

    public function create(): View
    {
        return view('porteria::residentes.create', [
            'residente' => new Residente,
            ...$this->opciones(),
        ]);
    }

    public function store(StoreResidenteRequest $request): RedirectResponse
    {
        $residente = $this->residentes->crear($request->validated());

        return redirect()->route('panel.porteria.residentes.show', $residente)
            ->with('exito', "Residente {$residente->nombre} creado.");
    }

    public function show(Residente $residente): View
    {
        return view('porteria::residentes.show', [
            'residente' => $this->residentes->buscar($residente->id)->loadMissing('user'),
        ]);
    }

    public function edit(Residente $residente): View
    {
        return view('porteria::residentes.edit', [
            'residente' => $this->residentes->buscar($residente->id),
            ...$this->opciones(),
        ]);
    }

    public function update(UpdateResidenteRequest $request, Residente $residente): RedirectResponse
    {
        $this->residentes->actualizar($residente, $request->validated());

        return redirect()->route('panel.porteria.residentes.show', $residente)
            ->with('exito', "Residente {$residente->nombre} actualizado.");
    }

    public function destroy(Residente $residente): RedirectResponse
    {
        $this->residentes->eliminar($residente);

        return redirect()->route('panel.porteria.residentes.index')
            ->with('exito', "Residente {$residente->nombre} eliminado.");
    }

    private function opciones(): array
    {
        return [
            'unidades' => $this->residentes->unidades(),
            'tipos' => collect(TipoResidente::cases())->mapWithKeys(fn (TipoResidente $t) => [$t->value => ucfirst($t->value)])->all(),
        ];
    }
}
