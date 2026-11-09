<?php

namespace App\Modules\Porteria\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Monitoreo\Application\ZonaService;
use App\Modules\Porteria\Application\AutorizacionService;
use App\Modules\Porteria\Application\RegistroPorteriaService;
use App\Modules\Porteria\Application\ResidenteService;
use App\Modules\Porteria\Domain\Models\Autorizacion;
use App\Modules\Porteria\Domain\Models\RegistroPorteria;
use App\Modules\Porteria\Domain\Models\Unidad;
use App\Modules\Porteria\Domain\TipoIngreso;
use App\Modules\Porteria\Http\Requests\FiltroIngresosRequest;
use App\Modules\Porteria\Http\Requests\StoreIngresoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IngresoController extends Controller
{
    public function __construct(
        private readonly RegistroPorteriaService $registros,
        private readonly AutorizacionService $autorizaciones,
        private readonly ResidenteService $residentes,
        private readonly ZonaService $zonas,
    ) {}

    public function index(FiltroIngresosRequest $request): View
    {
        return view('porteria::ingresos.index', [
            'registros' => $this->registros->listar($request->filtros()),
            'unidades' => $this->opcionesUnidades(),
            'tipos' => $this->opcionesTipos(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('porteria::ingresos.create', [
            'unidades' => $this->opcionesUnidades(),
            'zonas' => $this->zonas->todas()->where('activa', true)->pluck('nombre', 'id')->all(),
            'tipos' => $this->opcionesTipos(),
            'unidadInicial' => $request->integer('unidad_id') ?: null,
            'autorizacionInicial' => $request->integer('autorizacion_id') ?: null,
        ]);
    }

    public function store(StoreIngresoRequest $request): RedirectResponse
    {
        $registro = $this->registros->registrar($request->user(), $request->validated());

        return redirect()->route('panel.porteria.ingresos.create')
            ->with('exito', "Ingreso de {$registro->nombre_visitante} registrado.");
    }

    public function salida(RegistroPorteria $registro): RedirectResponse
    {
        if ($registro->fecha_salida) {
            return back()->with('error', 'Ese ingreso ya tiene la salida registrada.');
        }

        $this->registros->registrarSalida($registro);

        return back()->with('exito', "Salida de {$registro->nombre_visitante} registrada.");
    }

    /** Las autorizaciones que se pueden usar ya en la unidad, para precargar el formulario. */
    public function autorizacionesDeUnidad(Request $request): JsonResponse
    {
        $datos = $request->validate(['unidad_id' => ['required', 'integer', 'exists:unidades,id']]);

        $autorizaciones = $this->autorizaciones->vigentesDeUnidad((int) $datos['unidad_id'])
            ->map(fn (Autorizacion $a) => [
                'id' => $a->id,
                'visitante_nombre' => $a->visitante_nombre,
                'visitante_documento' => $a->visitante_documento,
                'placa' => $a->placa,
                'valida_hasta' => $a->valida_hasta->toIso8601String(),
                'valida_hasta_texto' => $a->valida_hasta->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ])
            ->values();

        return response()->json(['data' => $autorizaciones]);
    }

    private function opcionesUnidades(): array
    {
        return $this->residentes->unidades()->mapWithKeys(fn (Unidad $u) => [$u->id => $u->etiqueta])->all();
    }

    private function opcionesTipos(): array
    {
        return collect(TipoIngreso::cases())->mapWithKeys(fn (TipoIngreso $t) => [$t->value => match ($t) {
            TipoIngreso::Vehiculo => 'Vehículo',
            default => ucfirst($t->value),
        }])->all();
    }
}
