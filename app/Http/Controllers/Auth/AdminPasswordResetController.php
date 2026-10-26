<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AdminPasswordResetController extends Controller
{
    /**
     * Muestra el formulario para solicitar el enlace de recuperación.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('productos.index');
        }

        return view('admin.auth.forgot-password');
    }

    /**
     * Envía el enlace únicamente a usuarios internos activos.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate(
            [
                'email' => ['required', 'string', 'email', 'max:150'],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresá un correo electrónico válido.',
                'email.max' => 'El correo electrónico no puede superar los 150 caracteres.',
            ]
        );

        $usuarioActivo = User::query()
            ->where('email', $request->email)
            ->where('estado', 1)
            ->exists();

        /*
         * Por seguridad se muestra el mismo mensaje exista o no la cuenta.
         * Así no se revela qué correos pertenecen al personal de la empresa.
         */
        if ($usuarioActivo) {
            Password::broker('users')->sendResetLink(
                $request->only('email')
            );
        }

        return back()->with(
            'success',
            'Si el correo pertenece a un usuario activo, te enviamos un enlace para restablecer la contraseña.'
        );
    }

    /**
     * Muestra el formulario para crear la nueva contraseña.
     */
    public function edit(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('productos.index');
        }

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /**
     * Valida el token y guarda la nueva contraseña del usuario interno.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $datos = $request->validate(
            [
                'token' => ['required'],
                'email' => ['required', 'string', 'email', 'max:150'],
                'password' => [
                    'required',
                    'confirmed',
                    PasswordRule::min(8),
                    'regex:/[A-Z]/',
                    'regex:/[^A-Za-z0-9\\s]/',
                ],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresá un correo electrónico válido.',
                'password.required' => 'La nueva contraseña es obligatoria.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.regex' => 'La contraseña debe tener al menos una mayúscula y un símbolo.',
            ]
        );

        $usuarioActivo = User::query()
            ->where('email', $datos['email'])
            ->where('estado', 1)
            ->exists();

        if (!$usuarioActivo) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'El enlace es inválido o venció. Solicitá uno nuevo.',
                ]);
        }

        $estado = Password::broker('users')->reset(
            $datos,
            function ($usuario, string $password): void {
                $usuario->password = $password;
                $usuario->setRememberToken(Str::random(60));
                $usuario->save();

                event(new PasswordReset($usuario));
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            return redirect()
                ->route('admin.login')
                ->with(
                    'success',
                    'Tu contraseña fue actualizada. Ya podés iniciar sesión.'
                );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'El enlace es inválido o venció. Solicitá uno nuevo.',
            ]);
    }
}
