@extends('layouts.invitado')

@section('titulo', 'Iniciar sesión')

@section('contenido')
    <div class="nx-tarjeta p-4">
        <h1 class="h5 mb-1">Iniciar sesión</h1>
        <p class="nx-texto-suave small mb-4">Panel de vigilancia del conjunto.</p>

        @include('layouts.partials.mensajes')

        <form method="POST" action="{{ route('login.entrar') }}" novalidate>
            @csrf
            <x-campo name="email" label="Correo" type="email" autocomplete="username" autofocus requerido />
            <x-campo name="password" label="Contraseña" type="password" autocomplete="current-password" requerido />
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="recordarme" id="recordarme" value="1">
                <label class="form-check-label" for="recordarme">Mantener la sesión</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Entrar</button>
        </form>
    </div>
    @if (app()->environment('local'))
        <p class="small nx-texto-suave text-center mt-3 mb-0">Demo: admin@nexora.test, vigilante@nexora.test, residente@nexora.test, consejo@nexora.test · clave <code>password</code></p>
    @endif
@endsection
