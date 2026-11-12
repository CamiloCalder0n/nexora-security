<?php

namespace App\Modules\Identidad\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Identidad\Application\AuditoriaService;
use App\Modules\Identidad\Http\Requests\FiltroAuditoriaRequest;
use App\Support\Csv;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function index(FiltroAuditoriaRequest $request): View
    {
        return view('identidad::auditoria.index', [
            'accesos' => $this->auditoria->listar($request->validated()),
            'ultimos30' => $this->auditoria->accesosUltimosDias(30),
            'recursos' => AuditoriaService::RECURSOS,
        ]);
    }

    public function csv(FiltroAuditoriaRequest $request): StreamedResponse
    {
        return Csv::descargar($this->auditoria->filasCsv($request->validated()), 'auditoria-nexora-'.now()->format('Y-m-d').'.csv');
    }
}
