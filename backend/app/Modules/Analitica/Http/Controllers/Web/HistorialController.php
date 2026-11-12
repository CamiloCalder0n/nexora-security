<?php

namespace App\Modules\Analitica\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Analitica\Application\HistorialService;
use App\Modules\Analitica\Http\Requests\ConsultarEvidenciaRequest;
use App\Modules\Analitica\Http\Requests\FiltroHistorialRequest;
use App\Support\Csv;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistorialController extends Controller
{
    public function __construct(private readonly HistorialService $historial) {}

    public function index(FiltroHistorialRequest $request): View
    {
        return view('analitica::historial', [
            'eventos' => $this->historial->listar($request->validated()),
            'zonas' => $this->historial->zonas(),
            'resultados' => FiltroHistorialRequest::RESULTADOS,
        ]);
    }

    public function csv(FiltroHistorialRequest $request): StreamedResponse
    {
        return Csv::descargar($this->historial->filasCsv($request->validated()), 'historial-nexora-'.now()->format('Y-m-d').'.csv');
    }

    public function evidencia(string $uuid): View
    {
        return view('analitica::evidencia', ['evento' => $this->evento($uuid), 'consultada' => false]);
    }

    public function verEvidencia(ConsultarEvidenciaRequest $request, string $uuid): View
    {
        $evento = $this->historial->consultarEvidencia($request->user(), $this->evento($uuid), $request->validated('motivo'), $request->ip());

        return view('analitica::evidencia', ['evento' => $evento, 'consultada' => true]);
    }

    private function evento(string $uuid): array
    {
        return $this->historial->evento($uuid) ?? abort(404);
    }
}
