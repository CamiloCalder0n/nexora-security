<?php

namespace App\Modules\Monitoreo\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Monitoreo\Application\ZonaService;
use App\Modules\Monitoreo\Domain\Models\Zona;
use App\Modules\Monitoreo\Domain\TipoZona;
use App\Modules\Monitoreo\Http\Requests\FiltroZonasRequest;
use App\Modules\Monitoreo\Http\Requests\StoreZonaRequest;
use App\Modules\Monitoreo\Http\Requests\UpdateZonaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ZonaController extends Controller
{
    public function __construct(private readonly ZonaService $zonas) {}

    public function index(FiltroZonasRequest $request): View
    {
        return view('monitoreo::zonas.index', [
            'zonas' => $this->zonas->listar($request->validated()),
            'tipos' => self::tipos(),
        ]);
    }

    public function create(): View
    {
        return view('monitoreo::zonas.create', [
            'zona' => new Zona([
                'prioridad_base' => 2,
                'ventana_correlacion_min' => 10,
                'ventana_silencio_min' => 5,
                'activa' => true,
            ]),
            'tipos' => self::tipos(),
            'dias' => self::dias(),
        ]);
    }

    public function store(StoreZonaRequest $request): RedirectResponse
    {
        $zona = $this->zonas->crear($request->validated());

        return redirect()->route('panel.monitoreo.zonas.show', $zona)
            ->with('exito', "Zona «{$zona->nombre}» creada.");
    }

    public function show(Zona $zona): View
    {
        return view('monitoreo::zonas.show', [
            'zona' => $this->zonas->detalle($zona),
            'tipos' => self::tipos(),
            'dias' => self::dias(),
        ]);
    }

    public function edit(Zona $zona): View
    {
        return view('monitoreo::zonas.edit', [
            'zona' => $this->zonas->detalle($zona),
            'tipos' => self::tipos(),
            'dias' => self::dias(),
        ]);
    }

    public function update(UpdateZonaRequest $request, Zona $zona): RedirectResponse
    {
        // El formulario web siempre envía el horario completo: sin filas significa sin franjas.
        $datos = $request->validated() + ['franjas' => []];

        $zona = $this->zonas->actualizar($zona, $datos);

        return redirect()->route('panel.monitoreo.zonas.show', $zona)
            ->with('exito', "Zona «{$zona->nombre}» actualizada.");
    }

    /** Si la zona tiene cámaras o historial, ZonaEnUso se renderiza sola como error `zona`. */
    public function destroy(Zona $zona): RedirectResponse
    {
        $this->zonas->eliminar($zona);

        return redirect()->route('panel.monitoreo.zonas.index')
            ->with('exito', "Zona «{$zona->nombre}» eliminada.");
    }

    /** @return array<string, string> */
    private static function tipos(): array
    {
        return [
            TipoZona::AccesoPeatonal->value => 'Acceso peatonal',
            TipoZona::AccesoVehicular->value => 'Acceso vehicular',
            TipoZona::Parqueadero->value => 'Parqueadero',
            TipoZona::Perimetro->value => 'Perímetro',
            TipoZona::ZonaComun->value => 'Zona común',
            TipoZona::Servicio->value => 'Servicio',
        ];
    }

    /** Mismo índice que Carbon::dayOfWeek (0 = domingo). */
    private static function dias(): array
    {
        return ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    }
}
