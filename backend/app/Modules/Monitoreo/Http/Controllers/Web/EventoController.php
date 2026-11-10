<?php

namespace App\Modules\Monitoreo\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Monitoreo\Application\EventoService;
use App\Modules\Monitoreo\Http\Resources\EventoVivoResource;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class EventoController extends Controller
{
    private const LIMITE = 50;

    public function __construct(private readonly EventoService $eventos) {}

    public function index(): View
    {
        return view('monitoreo::eventos.index', ['eventos' => $this->recientes()]);
    }

    public function feed(): JsonResponse
    {
        return response()->json(['eventos' => $this->recientes()])->header('Cache-Control', 'no-store');
    }

    private function recientes(): array
    {
        return EventoVivoResource::collection($this->eventos->recientes(self::LIMITE))->resolve();
    }
}
