<?php

namespace App\Modules\Analitica\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analitica\Application\InformeMensualService;
use App\Modules\Analitica\Http\Requests\InformeMensualRequest;
use App\Support\Csv;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformeMensualController extends Controller
{
    public function __construct(private readonly InformeMensualService $informes) {}

    public function show(InformeMensualRequest $request): View
    {
        return view('analitica::informe-mensual', ['informe' => $this->informes->generar($request->mes())]);
    }

    public function csv(InformeMensualRequest $request): StreamedResponse
    {
        $informe = $this->informes->generar($request->mes());

        return Csv::descargar($this->informes->filasCsv($informe), "informe-nexora-{$informe['mes']}.csv");
    }
}
