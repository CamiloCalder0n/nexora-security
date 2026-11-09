<?php

namespace App\Modules\Analitica\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analitica\Application\ColaAlertasService;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class ColaAlertasController extends Controller
{
    /** Rutas del API de Alertas (PR de Santiago); sin ellas la cola es solo de lectura. */
    private const RUTA_ATENDER = 'api.v1.alertas.atender';

    private const RUTA_CERRAR = 'api.v1.alertas.cerrar';

    public function __invoke(ColaAlertasService $cola): View
    {
        return view('analitica::cola-alertas', [
            'alertas' => $cola->pendientes(),
            'limite' => ColaAlertasService::LIMITE,
            'acciones' => Route::has(self::RUTA_ATENDER) && Route::has(self::RUTA_CERRAR),
            'rutaAtender' => self::RUTA_ATENDER,
            'rutaCerrar' => self::RUTA_CERRAR,
        ]);
    }
}
