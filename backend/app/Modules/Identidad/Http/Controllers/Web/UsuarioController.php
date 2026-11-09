<?php

namespace App\Modules\Identidad\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identidad\Application\UsuarioService;
use App\Modules\Identidad\Domain\Rol;
use App\Modules\Identidad\Http\Requests\FiltroUsuariosRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(FiltroUsuariosRequest $request): View
    {
        return view('identidad::usuarios.index', [
            'usuarios' => $this->usuarios->listar($request->validated()),
            'totales' => $this->usuarios->totales(),
            'roles' => collect(Rol::cases())->mapWithKeys(fn (Rol $rol) => [$rol->value => ucfirst($rol->value)])->all(),
        ]);
    }

    public function cambiarActivo(Request $request, User $usuario): RedirectResponse
    {
        // El formulario envía el estado deseado: reenviarlo no invierte el cambio.
        $activo = (bool) $request->validate(['activo' => ['required', 'boolean']])['activo'];

        try {
            $this->usuarios->cambiarActivo($usuario, $activo, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $mensaje = $activo
            ? "Se activó la cuenta de {$usuario->name}."
            : "Se desactivó la cuenta de {$usuario->name} y se cerraron sus accesos por API.";

        return back()->with('exito', $mensaje);
    }
}
