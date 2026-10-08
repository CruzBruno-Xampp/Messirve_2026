<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAdmin
{
    /**
     * Permite continuar únicamente a usuarios internos autenticados.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $guard = Auth::guard('web');
        $usuario = $guard->user();

        if (!$usuario) {
            return redirect()
                ->guest(route('admin.login'))
                ->withErrors([
                    'acceso' =>
                        'Debés iniciar sesión como usuario del sistema.',
                ]);
        }

        /*
         * Un usuario autenticado no puede conservar el acceso si después
         * se desactiva su cuenta o el rol que tiene asignado.
         */
        if (!$usuario->estado || !$usuario->rol || !$usuario->rol->estado) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'acceso' =>
                        'Tu usuario o tu rol se encuentra inactivo.',
                ]);
        }

        return $next($request);
    }
}
