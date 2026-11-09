<?php

namespace App\Modules\Analitica\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analitica\Application\ColaAlertasService;
use Illuminate\View\View;

class ColaAlertasController extends Controller
{
    public function __invoke(ColaAlertasService $cola): View
    {
        return view('analitica::cola-alertas', [
            'alertas' => $cola->pendientes(),
            'limite' => ColaAlertasService::LIMITE,
        ]);
    }
}
