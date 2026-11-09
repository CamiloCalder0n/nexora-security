@props([
    'paginador',
    'buscar' => 'Buscar…',
    'vacio' => 'No hay registros para mostrar.',
    'conBuscador' => true,
])
{{--
    Uso:
    <x-tabla :paginador="$zonas" buscar="Buscar por nombre">
        <x-slot:filtros> (columnas extra del formulario GET, opcional: <div class="col-6 col-md-auto">…</div>) </x-slot:filtros>
        <x-slot:cabecera><th>Nombre</th><th class="text-end">Acciones</th></x-slot:cabecera>
        @foreach ($zonas as $zona) <tr>…</tr> @endforeach
    </x-tabla>
--}}
@php
    // Página pedida más allá de la última (p. ej. ?page=99): hay registros, pero no en esta página.
    $fueraDeRango = $paginador->isEmpty() && $paginador->total() > 0;
@endphp
<div {{ $attributes->merge(['class' => 'nx-tarjeta']) }}>
    @if ($conBuscador || isset($filtros))
        <form method="GET" action="{{ url()->current() }}" class="p-3 border-bottom" role="search">
            <div class="row g-2 align-items-end">
                @if ($conBuscador)
                    <div class="col-12 col-md">
                        <label for="search" class="visually-hidden">Buscar</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="search" id="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ $buscar }}">
                        </div>
                    </div>
                @endif
                {{ $filtros ?? '' }}
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                    @if (collect(request()->except('page'))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty())
                        <a href="{{ url()->current() }}" class="btn btn-link text-decoration-none">Limpiar</a>
                    @endif
                </div>
            </div>
        </form>
    @endif

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>{{ $cabecera }}</tr></thead>
            <tbody>
                @if ($fueraDeRango)
                    <tr>
                        <td colspan="100" class="text-center nx-texto-suave py-5">
                            No hay registros en esta página.
                            <a href="{{ $paginador->url(1) }}">Ir a la página 1</a>
                        </td>
                    </tr>
                @elseif ($paginador->isEmpty())
                    <tr><td colspan="100" class="text-center nx-texto-suave py-5">{{ $vacio }}</td></tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @if ($paginador->total() > 0 && ! $fueraDeRango)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 p-3 border-top">
            <small class="nx-texto-suave">
                Mostrando {{ $paginador->firstItem() }}–{{ $paginador->lastItem() }} de {{ $paginador->total() }}
            </small>
            @if ($paginador->hasPages())
                <div class="nx-paginacion">{{ $paginador->onEachSide(1)->links() }}</div>
            @endif
        </div>
    @endif
</div>
