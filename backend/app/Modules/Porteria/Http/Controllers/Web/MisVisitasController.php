<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Porteria\Application\RegistroPorteriaService;
use App\Modules\Porteria\Http\Requests\FiltroMisVisitasRequest;
use Illuminate\View\View;

/** HU18: ingresos registrados en portería hacia las unidades del residente. */
class MisVisitasController extends Controller
{
    public function __construct(private readonly RegistroPorteriaService $registros) {}

    public function __invoke(FiltroMisVisitasRequest $request): View
    {
        $residente = auth()->user()->residente;

        return view('porteria::mis-visitas.index', [
            'residente' => $residente,
            'visitas' => $residente ? $this->registros->visitasDeResidente($residente, $request->filtros()) : null,
        ]);
    }
}
