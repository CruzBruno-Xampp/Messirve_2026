<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    /**
     * Muestra el formulario de acceso administrativo.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('productos.index');
        }

        return view('admin.auth.login');
    }

    /**
     * Autentica a un usuario interno de la tabla users.
     */
    public function store(Request $request): RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('productos.index');
        }

        /*
         * Evita que espacios accidentales o mayúsculas
         * en el correo provoquen un rechazo innecesario.
         */
        $request->merge([
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
        ]);

        $credenciales = $request->validate(
            [
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:150',
                ],

                'password' => [
                    'required',
                    'string',
                ],
            ],
            [
                'email.required' =>
                    'El correo electrónico es obligatorio.',

                'email.email' =>
                    'Ingresá un correo electrónico válido.',

                'email.max' =>
                    'El correo electrónico no puede superar los 150 caracteres.',

                'password.required' =>
                    'La contraseña es obligatoria.',
            ]
        );

        $recordar = $request->boolean('recordar');

        // Los usuarios desactivados no pueden iniciar una sesión nueva.
        $credenciales['estado'] = 1;

        if (!Auth::guard('web')->attempt($credenciales, $recordar)) {
            return back()
                ->withInput(
                    $request->only(['email', 'recordar'])
                )
                ->withErrors([
                    'login' =>
                        'El correo electrónico o la contraseña son incorrectos.',
                ]);
        }

        $usuario = Auth::guard('web')->user();

        if (!$usuario->rol || !$usuario->rol->estado) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'login' =>
                        'El rol asignado a este usuario se encuentra inactivo.',
                ]);
        }

        /*
         * Genera un identificador de sesión nuevo después
         * del acceso para evitar fijación de sesión.
         */
        $request->session()->regenerate();

        return redirect()->intended(
            route('productos.index')
        );
    }

    /**
     * Cierra solamente la autenticación del guard administrativo.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with(
                'success',
                'La sesión administrativa se cerró correctamente.'
            );
    }
}
