<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermiso
{
    /**
     * Comprueba que el usuario administrativo tenga el permiso solicitado.
     *
     * El código se recibe desde la ruta. Por ejemplo:
     * VerificarPermiso::class . ':productos.editar'
     */
    public function handle(
        Request $request,
        Closure $next,
        string $codigo
    ): Response {
        $usuario = Auth::guard('web')->user();

        if (!($usuario instanceof User)) {
            return redirect()
                ->guest(route('admin.login'))
                ->withErrors([
                    'acceso' =>
                        'Debés iniciar sesión como usuario del sistema.',
                ]);
        }

        if (!$usuario->tienePermiso($codigo)) {
            abort(
                Response::HTTP_FORBIDDEN,
                'No tenés permiso para realizar esta acción.'
            );
        }

        return $next($request);
    }
}
    