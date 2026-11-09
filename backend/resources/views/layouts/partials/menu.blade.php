@php
    use App\Modules\Identidad\Domain\Rol;

    $rol = auth()->user()?->rol;

    // [ruta, texto, icono, roles]; solo se muestran las rutas que ya existen.
    $secciones = [
        'General' => [
            ['panel.dashboard', 'Dashboard', 'speedometer2', [Rol::Admin, Rol::Vigilante, Rol::Consejo]],
        ],
        'Portería' => [
            ['panel.porteria.ingresos.create', 'Registrar ingreso', 'box-arrow-in-right', [Rol::Vigilante, Rol::Admin]],
            ['panel.porteria.ingresos.index', 'Ingresos', 'journal-text', [Rol::Vigilante, Rol::Admin, Rol::Consejo]],
            ['panel.porteria.autorizaciones.vigentes', 'Autorizaciones vigentes', 'card-checklist', [Rol::Vigilante]],
            ['panel.porteria.autorizaciones.index', 'Autorizaciones', 'card-checklist', [Rol::Admin]],
            ['panel.porteria.residentes.index', 'Residentes', 'people', [Rol::Admin]],
        ],
        'Mi unidad' => [
            ['panel.porteria.mis-autorizaciones.index', 'Mis autorizaciones', 'card-checklist', [Rol::Residente]],
            ['panel.porteria.mis-visitas.index', 'Mis visitas', 'clock-history', [Rol::Residente]],
        ],
        'Monitoreo' => [
            ['panel.monitoreo.zonas.index', 'Zonas', 'geo-alt', [Rol::Admin]],
            ['panel.analitica.cola-alertas', 'Cola de alertas', 'bell-fill', [Rol::Vigilante, Rol::Admin]],
            ['panel.alertas.index', 'Alertas', 'bell', [Rol::Admin, Rol::Vigilante, Rol::Consejo]],
        ],
        'Administración' => [
            ['panel.identidad.usuarios.index', 'Usuarios', 'person-gear', [Rol::Admin]],
        ],
    ];
@endphp

@php
    $rutasDelMenu = collect($secciones)->flatten(1)->pluck(0)->all();
@endphp

<nav class="nx-menu nav flex-column px-2 pb-3">
    @foreach ($secciones as $titulo => $items)
        @php
            $visibles = array_filter($items, fn ($i) => in_array($rol, $i[3], true) && Route::has($i[0]));
        @endphp
        @if ($visibles)
            <div class="nx-seccion">{{ $titulo }}</div>
            @foreach ($visibles as [$ruta, $texto, $icono])
                @php
                    $base = \Illuminate\Support\Str::beforeLast($ruta, '.');
                    // Un ".index" cubre sus subrutas (show, edit…) salvo las que tienen su propio ítem.
                    $activo = request()->routeIs($ruta) || (
                        str_ends_with($ruta, '.index')
                        && request()->routeIs($base.'.*')
                        && ! request()->routeIs(...array_diff($rutasDelMenu, [$ruta]))
                    );
                @endphp
                <a href="{{ route($ruta) }}" @class(['nav-link', 'active' => $activo]) @if ($activo) aria-current="page" @endif>
                    <i class="bi bi-{{ $icono }}" aria-hidden="true"></i><span>{{ $texto }}</span>
                </a>
            @endforeach
        @endif
    @endforeach
</nav>
