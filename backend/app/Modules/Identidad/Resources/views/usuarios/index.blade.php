@extends('layouts.panel')

@section('titulo', 'Usuarios')
@section('subtitulo', 'Cuentas del sistema: active o desactive el acceso al panel y a la API.')

@section('contenido')
    @if ($errors->hasAny(['search', 'rol', 'activo']))
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-funnel me-1"></i>Los filtros no eran válidos y se limpiaron: {{ $errors->first() }}
        </div>
    @endif

    <div class="row g-2 mb-3">
        @foreach ($roles as $valor => $texto)
            <div class="col-6 col-md-4 col-xl">
                <a href="{{ route('panel.identidad.usuarios.index', ['rol' => $valor]) }}"
                   class="nx-tarjeta d-block p-3 text-decoration-none text-body h-100 {{ request('rol') === $valor ? 'border-primary' : '' }}">
                    <div class="small nx-texto-suave">{{ $texto }}</div>
                    <div class="fs-4 fw-semibold">{{ $totales[$valor] ?? 0 }}</div>
                </a>
            </div>
        @endforeach
    </div>

    <x-tabla :paginador="$usuarios" buscar="Buscar por nombre o email" vacio="No hay usuarios que coincidan con los filtros.">
        <x-slot:filtros>
            <div class="col-6 col-md-auto">
                <label for="filtro-rol" class="visually-hidden">Rol</label>
                <select id="filtro-rol" name="rol" class="form-select">
                    <option value="">Todos los roles</option>
                    @foreach ($roles as $valor => $texto)
                        <option value="{{ $valor }}" @selected(request('rol') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-auto">
                <label for="filtro-activo" class="visually-hidden">Estado</label>
                <select id="filtro-activo" name="activo" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="1" @selected(request('activo') === '1')>Activos</option>
                    <option value="0" @selected(request('activo') === '0')>Inactivos</option>
                </select>
            </div>
        </x-slot:filtros>

        <x-slot:cabecera>
            <th>Nombre</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Rol</th>
            <th>Estado</th>
            <th class="text-end">Acciones</th>
        </x-slot:cabecera>

        @foreach ($usuarios as $usuario)
            @php($esPropio = $usuario->is(auth()->user()))
            <tr>
                <td class="fw-medium">
                    {{ $usuario->name }}
                    @if ($esPropio)<span class="badge text-bg-light border ms-1">Usted</span>@endif
                </td>
                <td class="text-break">{{ $usuario->email }}</td>
                <td>{{ $usuario->telefono ?: '—' }}</td>
                <td>{{ ucfirst($usuario->rol->value) }}</td>
                <td><x-estado :valor="(bool) $usuario->activo" /></td>
                <td class="text-end text-nowrap">
                    <form method="POST" action="{{ route('panel.identidad.usuarios.activo', $usuario) }}" class="d-inline"
                          onsubmit="return confirm(@js(($usuario->activo ? '¿Desactivar la cuenta de ' : '¿Activar la cuenta de ').$usuario->name.'?'))">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="activo" value="{{ $usuario->activo ? 0 : 1 }}">
                        @if ($usuario->activo)
                            <button type="submit" class="btn btn-sm btn-outline-danger" @disabled($esPropio)
                                    @if ($esPropio) title="No puede desactivar su propia cuenta" @endif>
                                <i class="bi bi-person-slash me-1"></i>Desactivar
                            </button>
                        @else
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-person-check me-1"></i>Activar
                            </button>
                        @endif
                    </form>
                </td>
            </tr>
        @endforeach
    </x-tabla>
@endsection
