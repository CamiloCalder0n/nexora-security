@extends('layouts.panel')

@section('titulo', 'Auditoría de accesos')
@section('subtitulo', 'Quién consultó evidencias, con qué motivo, y los intentos de entrar a funciones sin permiso. Los registros no se pueden editar ni borrar.')

@section('acciones')
    <a href="{{ route('panel.identidad.auditoria.csv', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-filetype-csv" aria-hidden="true"></i> Exportar CSV
    </a>
@endsection

@section('contenido')
    <x-aviso-filtro :campos="['search', 'recurso', 'desde', 'hasta']" />

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-4 col-xl-3">
            <div class="nx-tarjeta p-3 h-100">
                <div class="small nx-texto-suave">Consultas en los últimos 30 días</div>
                <div class="fs-4 fw-semibold">{{ $ultimos30 }}</div>
            </div>
        </div>
    </div>

    <x-tabla :paginador="$accesos" buscar="Buscar por usuario, motivo o id del evento" vacio="No hay accesos registrados con esos filtros.">
        <x-slot:filtros>
            <div class="col-6 col-md-auto">
                <label for="filtro-recurso" class="form-label small mb-1">Tipo</label>
                <select id="filtro-recurso" name="recurso" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($recursos as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('recurso') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="filtro-desde" class="form-label small mb-1">Desde</label>
                <input type="date" id="filtro-desde" name="desde" value="{{ request('desde') }}" class="form-control">
            </div>
            <div class="col-6 col-md-auto">
                <label for="filtro-hasta" class="form-label small mb-1">Hasta</label>
                <input type="date" id="filtro-hasta" name="hasta" value="{{ request('hasta') }}" class="form-control">
            </div>
        </x-slot:filtros>

        <x-slot:cabecera>
            <th>Fecha</th>
            <th>Usuario</th>
            <th>Recurso</th>
            <th>Motivo</th>
            <th>IP</th>
        </x-slot:cabecera>

        @foreach ($accesos as $acceso)
            <tr>
                <td class="text-nowrap">{{ $acceso->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                <td>
                    <div class="fw-medium">{{ $acceso->usuario?->name ?? '—' }}</div>
                    <div class="small nx-texto-suave">{{ $acceso->usuario ? ucfirst($acceso->usuario->rol->value) : '' }}</div>
                </td>
                <td class="text-nowrap">{{ $recursos[$acceso->recurso] ?? $acceso->recurso }} <span class="small nx-texto-suave font-monospace">{{ \Illuminate\Support\Str::limit($acceso->recurso_id, 30) }}</span></td>
                <td class="text-break">{{ $acceso->motivo }}</td>
                <td class="small font-monospace">{{ $acceso->ip ?? '—' }}</td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
