<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Porteria\Application\AutorizacionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AutorizacionesVigentesController extends Controller
{
    public function __construct(private readonly AutorizacionService $autorizaciones) {}

    public function __invoke(Request $request): View
    {
        $filtros = $request->validate(['search' => ['nullable', 'string', 'max:100']]);

        return view('porteria::autorizaciones-vigentes.index', [
            'autorizaciones' => $this->autorizaciones->listar([...$filtros, 'vigentes' => true]),
        ]);
    }
}
