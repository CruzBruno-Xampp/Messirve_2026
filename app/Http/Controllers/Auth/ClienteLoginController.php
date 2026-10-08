<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ClienteLoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciales = $request->validate(
            [
                'email' => ['required', 'email', 'max:150'],
                'password' => ['required', 'string'],
            ],
            [
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresá un correo electrónico válido.',
                'password.required' => 'La contraseña es obligatoria.',
            ]
        );

        $cliente = Cliente::query()
            ->where('email', $credenciales['email'])
            ->where('estado', 1)
            ->first();

        if (!$cliente || !$this->passwordEsValida($credenciales['password'], $cliente->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'login' => 'El correo electrónico o la contraseña son incorrectos.',
                ]);
        }

        if (!password_get_info($cliente->password)['algo']) {
            $cliente->password = $credenciales['password'];
            $cliente->save();
        }

        Auth::guard('cliente')->login($cliente);
        $request->session()->regenerate();

        return redirect()->intended(route('cargarproducto'));
    }

    private function passwordEsValida(string $passwordIngresada, string $passwordGuardada): bool
    {
        if (password_get_info($passwordGuardada)['algo']) {
            return Hash::check($passwordIngresada, $passwordGuardada);
        }

        return hash_equals($passwordGuardada, $passwordIngresada);
    }
}
