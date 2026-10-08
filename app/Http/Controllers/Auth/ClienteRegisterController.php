<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\TipoCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ClienteRegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate(
            [
                'nombre' => [
                'required',
                    'string',
                    'max:100',
                    'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'-]+$/u',
                ],

                'apellido' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s\'-]+$/u',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:150',
                    'unique:clientes,email',
                ],

               'telefono' => [
                'required',
                'string',
                'regex:/^[0-9]{8,15}$/',
                ],

                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)
                        ->letters()
                        ->numbers(),
                ],
            ],
           [
            'nombre.required' =>
                'El nombre es obligatorio.',

            'nombre.regex' =>
                'El nombre solamente puede contener letras, espacios, apóstrofes y guiones.',

            'apellido.required' =>
                'El apellido es obligatorio.',

            'apellido.regex' =>
                'El apellido solamente puede contener letras, espacios, apóstrofes y guiones.',

            'email.required' =>
                'El correo electrónico es obligatorio.',

            'email.email' =>
                'Ingresá un correo electrónico válido.',

            'email.unique' =>
                'Este correo ya se encuentra registrado.',

            'telefono.required' =>
                'El teléfono es obligatorio.',

            'telefono.regex' =>
                'El teléfono debe contener solamente números y tener entre 8 y 15 dígitos.',

            'password.required' =>
                'La contraseña es obligatoria.',

            'password.confirmed' =>
                'Las contraseñas no coinciden.',
        ]
        );

        $tipoClienteWeb = TipoCliente::query()
            ->where('nombre', 'CLIENTE_WEB')
            ->where('estado', 1)
            ->first();

        if (!$tipoClienteWeb) {
            return back()
                ->withInput()
                ->withErrors([
                    'registro' =>
                        'No se encontró el tipo de cliente CLIENTE_WEB.',
                ]);
        }

        Cliente::create([
            'idtipocliente' =>
                $tipoClienteWeb->idtipocliente,

            'nombre' =>
                $datos['nombre'],

            'apellido' =>
                $datos['apellido'],

            'email' =>
                $datos['email'],

            'telefono' =>
                $datos['telefono'],

            'password' =>
                $datos['password'],

            'documento' => null,
            'direccion' => null,
            'codigopostal' => null,
            'estado' => 1,
        ]);

        return redirect()
            ->route('cliente.register')
            ->with(
                'success',
                'Tu cuenta fue creada correctamente.'
            );
    }
}