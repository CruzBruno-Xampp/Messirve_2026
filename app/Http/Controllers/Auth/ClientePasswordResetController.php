<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ClientePasswordResetController extends Controller
{
    public function create(): View
    {
        return view('auth.recuperarcontra');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['email' => ['required', 'email', 'max:150']],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresá un correo electrónico válido.',
            ]
        );

        $clienteActivo = Cliente::query()
            ->where('email', $request->email)
            ->where('estado', 1)
            ->exists();

        if (!$clienteActivo) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'No encontramos un cliente activo con ese correo electrónico.',
                ]);
        }

        $estado = Password::broker('clientes')->sendResetLink(
            $request->only('email')
        );

        if ($estado === Password::RESET_LINK_SENT) {
            return back()->with(
                'success',
                'Te enviamos las instrucciones para restablecer tu contraseña.'
            );
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'No encontramos un cliente activo con ese correo electrónico.',
            ]);
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.restablecercontra', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            [
                'token' => ['required'],
                'email' => ['required', 'email', 'max:150'],
                'password' => [
                    'required',
                    'confirmed',
                    PasswordRule::min(8)->letters()->numbers(),
                ],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresá un correo electrónico válido.',
                'password.required' => 'La nueva contraseña es obligatoria.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.letters' => 'La contraseña debe contener al menos una letra.',
                'password.numbers' => 'La contraseña debe contener al menos un número.',
            ]
        );

        $estado = Password::broker('clientes')->reset(
            $datos,
            function ($cliente, string $password): void {
                $cliente->password = $password;
                $cliente->save();

                event(new PasswordReset($cliente));
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('success', 'Tu contraseña fue actualizada. Ya podés iniciar sesión.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'El enlace es inválido o venció. Solicitá uno nuevo.',
            ]);
    }
}
