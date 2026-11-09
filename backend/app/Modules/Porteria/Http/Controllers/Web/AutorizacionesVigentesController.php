<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Http\Requests\FiltroVigentesRequest;
use Illuminate\View\View;

class AutorizacionesVigentesController extends Controller
{
    public function __construct(private readonly AutorizacionService $autorizaciones) {}

    public function __invoke(FiltroVigentesRequest $request): View
    {
        return view('porteria::autorizaciones-vigentes.index', [
            'autorizaciones' => $this->autorizaciones->listar([...$request->filtros(), 'vigentes' => true]),
        ]);
    }
}
