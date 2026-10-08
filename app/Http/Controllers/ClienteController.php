<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\TipoCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClienteController extends Controller
{
    /**
     * Muestra los clientes registrados, sus filtros y el perfil seleccionado.
     */
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', 'todos');

        if (!in_array($estado, ['todos', '1', '0'], true)) {
            $estado = 'todos';
        }

        $idTipoCliente = $this->normalizarFiltroNumerico(
            $request->query('tipo')
        );

        $clientes = Cliente::query()
            ->with('tipoCliente')
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $termino = '%' . $busqueda . '%';
                $idDesdeCodigo = $this->extraerIdDesdeCodigo($busqueda);

                $consulta->where(function ($subconsulta) use (
                    $termino,
                    $idDesdeCodigo
                ) {
                    $subconsulta
                        ->where('nombre', 'like', $termino)
                        ->orWhere('apellido', 'like', $termino)
                        ->orWhere('documento', 'like', $termino)
                        ->orWhere('telefono', 'like', $termino)
                        ->orWhere('email', 'like', $termino)
                        ->orWhereRaw(
                            "CONCAT(nombre, ' ', apellido) LIKE ?",
                            [$termino]
                        );

                    if ($idDesdeCodigo !== null) {
                        $subconsulta->orWhere('idcliente', $idDesdeCodigo);
                    }
                });
            })
            ->when(
                $idTipoCliente !== null,
                fn ($consulta) => $consulta->where(
                    'idtipocliente',
                    $idTipoCliente
                )
            )
            ->when(
                $estado !== 'todos',
                fn ($consulta) => $consulta->where('estado', (int) $estado)
            )
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $tiposCliente = TipoCliente::query()
            ->withCount('clientes')
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get();

        $tiposClienteActivos = $tiposCliente
            ->filter(fn (TipoCliente $tipo) => (bool) $tipo->estado)
            ->values();

        $tipoPredeterminado = $tiposClienteActivos->first(
            fn (TipoCliente $tipo) => Str::upper($tipo->nombre) === 'CLIENTE_WEB'
        ) ?? $tiposClienteActivos->first();

        $idClienteSeleccionado = $this->normalizarFiltroNumerico(
            $request->query('cliente')
        );

        $clienteSeleccionado = null;

        if ($idClienteSeleccionado !== null) {
            $clienteSeleccionado = Cliente::query()
                ->with('tipoCliente')
                ->find($idClienteSeleccionado);
        }

        $clienteSeleccionado ??= $clientes->first();

        $resumen = Cliente::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activos')
            ->selectRaw('SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) AS inactivos')
            ->first();

        return view(
            'clientes.index',
            compact(
                'clientes',
                'tiposCliente',
                'tiposClienteActivos',
                'tipoPredeterminado',
                'clienteSeleccionado',
                'resumen',
                'busqueda',
                'estado',
                'idTipoCliente'
            )
        );
    }

    /**
     * Registra un cliente desde el panel administrativo.
     * La contraseña queda vacía: el cliente puede definirla después mediante
     * recuperación de contraseña si quiere utilizar la web.
     */
    public function store(Request $request): RedirectResponse
    {
        // Si la validación falla, esta marca sobrevive al redirect y permite
        // reabrir el modal de alta mostrando los errores dentro del formulario.
        $request->session()->flash('abrir_modal_nuevo_cliente', true);

        $this->normalizarDatos($request);
        $datos = $this->validarCliente($request);

        $cliente = DB::transaction(function () use ($datos) {
            return Cliente::create([
                'idtipocliente' => (int) $datos['idtipocliente'],
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'documento' => $this->nullable($datos['documento'] ?? null),
                'telefono' => $this->nullable($datos['telefono'] ?? null),
                'email' => $this->nullable($datos['email'] ?? null),
                'password' => null,
                'direccion' => $this->nullable($datos['direccion'] ?? null),
                'codigopostal' => $this->nullable($datos['codigopostal'] ?? null),
                'estado' => (bool) $datos['estado'],
            ]);
        });

        // En un alta exitosa no corresponde volver a abrir el modal.
        $request->session()->forget('abrir_modal_nuevo_cliente');

        return redirect()
            ->route('clientes.index', ['cliente' => $cliente->idcliente])
            ->with('success', 'El cliente fue registrado correctamente.');
    }

    /**
     * Actualiza los datos administrativos sin modificar la contraseña.
     */
    public function update(
        Request $request,
        Cliente $cliente
    ): RedirectResponse {
        $this->normalizarDatos($request);
        $datos = $this->validarCliente($request, $cliente);

        DB::transaction(function () use ($datos, $cliente) {
            $emailNuevo = $this->nullable($datos['email'] ?? null);

            $cambios = [
                'idtipocliente' => (int) $datos['idtipocliente'],
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'documento' => $this->nullable($datos['documento'] ?? null),
                'telefono' => $this->nullable($datos['telefono'] ?? null),
                'email' => $emailNuevo,
                'direccion' => $this->nullable($datos['direccion'] ?? null),
                'codigopostal' => $this->nullable($datos['codigopostal'] ?? null),
                'estado' => (bool) $datos['estado'],
            ];

            if ($cliente->email !== $emailNuevo) {
                $cambios['email_verified_at'] = null;
            }

            $cliente->update($cambios);
        });

        return redirect()
            ->route('clientes.index', ['cliente' => $cliente->idcliente])
            ->with('success', 'El cliente fue actualizado correctamente.');
    }


    /**
     * Registra un nuevo tipo de cliente y su política de descuento.
     */
    public function storeTipoCliente(Request $request): RedirectResponse
    {
        $request->session()->flash('abrir_modal_tipos_cliente', true);

        $this->normalizarDatosTipoCliente($request);
        $datos = $this->validarTipoCliente($request);

        TipoCliente::create([
            'idlistaprecio' => null,
            'nombre' => $datos['tipo_nombre'],
            'descripcion' => $this->nullable($datos['tipo_descripcion'] ?? null),
            'descuento' => (float) $datos['tipo_descuento'],
            'aplica_web' => (bool) $datos['tipo_aplica_web'],
            'aplica_local' => (bool) $datos['tipo_aplica_local'],
            'estado' => (bool) $datos['tipo_estado'],
        ]);

        $request->session()->forget('abrir_modal_tipos_cliente');

        return redirect()
            ->route('clientes.index')
            ->with('success', 'El tipo de cliente fue registrado correctamente.');
    }

    /**
     * Actualiza un tipo de cliente existente.
     * CLIENTE_WEB queda protegido porque el registro público depende de él.
     */
    public function updateTipoCliente(
        Request $request,
        TipoCliente $tipoCliente
    ): RedirectResponse {
        $request->session()->flash('abrir_modal_tipos_cliente', true);

        $esClienteWeb = $tipoCliente->esClienteWeb();

        if ($esClienteWeb) {
            $request->merge([
                'tipo_nombre' => 'CLIENTE_WEB',
                'tipo_descuento' => '0',
                'tipo_aplica_web' => '1',
                'tipo_aplica_local' => '1',
                'tipo_estado' => '1',
            ]);
        }

        $this->normalizarDatosTipoCliente($request);
        $datos = $this->validarTipoCliente($request, $tipoCliente);

        $tipoCliente->update([
            'nombre' => $esClienteWeb ? 'CLIENTE_WEB' : $datos['tipo_nombre'],
            'descripcion' => $this->nullable($datos['tipo_descripcion'] ?? null),
            'descuento' => $esClienteWeb ? 0 : (float) $datos['tipo_descuento'],
            'aplica_web' => $esClienteWeb ? true : (bool) $datos['tipo_aplica_web'],
            'aplica_local' => $esClienteWeb ? true : (bool) $datos['tipo_aplica_local'],
            'estado' => $esClienteWeb ? true : (bool) $datos['tipo_estado'],
        ]);

        $request->session()->forget('abrir_modal_tipos_cliente');

        return redirect()
            ->route('clientes.index')
            ->with(
                'success',
                $esClienteWeb
                    ? 'La descripción de CLIENTE_WEB fue actualizada. Sus datos protegidos se conservaron.'
                    : 'El tipo de cliente fue actualizado correctamente.'
            );
    }

    /**
     * Reglas compartidas para crear y editar tipos de cliente.
     *
     * @return array<string, mixed>
     */
    private function validarTipoCliente(
        Request $request,
        ?TipoCliente $tipoCliente = null
    ): array {
        $nombreUnico = Rule::unique('tipo_cliente', 'nombre');

        if ($tipoCliente) {
            $nombreUnico->ignore($tipoCliente->idtipocliente, 'idtipocliente');
        }

        $validador = Validator::make(
            $request->all(),
            [
                'tipo_nombre' => [
                    'required',
                    'string',
                    'max:50',
                    "regex:/^[\\pL0-9\\s'-]+$/u",
                    $nombreUnico,
                ],
                'tipo_descripcion' => ['nullable', 'string', 'max:50'],
                'tipo_descuento' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:100',
                    'decimal:0,2',
                ],
                'tipo_aplica_web' => ['required', 'boolean'],
                'tipo_aplica_local' => ['required', 'boolean'],
                'tipo_estado' => ['required', 'boolean'],
            ],
            [
                'tipo_nombre.required' => 'Ingresá el nombre del tipo de cliente.',
                'tipo_nombre.max' => 'El nombre no puede superar los 50 caracteres.',
                'tipo_nombre.regex' => 'El nombre solamente puede contener letras, números, espacios, apóstrofes y guiones.',
                'tipo_nombre.unique' => 'Ya existe un tipo de cliente con ese nombre.',
                'tipo_descripcion.max' => 'La descripción no puede superar los 50 caracteres.',
                'tipo_descuento.required' => 'Ingresá el porcentaje de descuento.',
                'tipo_descuento.numeric' => 'El descuento debe ser un número.',
                'tipo_descuento.min' => 'El descuento no puede ser menor a 0%.',
                'tipo_descuento.max' => 'El descuento no puede superar el 100%.',
                'tipo_descuento.decimal' => 'El descuento puede tener como máximo dos decimales.',
                'tipo_aplica_web.boolean' => 'La opción Web no es válida.',
                'tipo_aplica_local.boolean' => 'La opción Local no es válida.',
                'tipo_estado.boolean' => 'El estado seleccionado no es válido.',
            ]
        );

        $validador->after(function ($validador) use ($request) {
            $aplicaWeb = (string) $request->input('tipo_aplica_web', '0') === '1';
            $aplicaLocal = (string) $request->input('tipo_aplica_local', '0') === '1';

            if (!$aplicaWeb && !$aplicaLocal) {
                $validador->errors()->add(
                    'tipo_canales',
                    'Seleccioná al menos un canal: Web, Local o ambos.'
                );
            }
        });

        return $validador->validateWithBag('tiposCliente');
    }

    private function normalizarDatosTipoCliente(Request $request): void
    {
        $descuento = str_replace(
            ',',
            '.',
            trim((string) $request->input('tipo_descuento', '0'))
        );

        $request->merge([
            'tipo_nombre' => Str::upper(
                $this->normalizarTexto((string) $request->input('tipo_nombre'))
            ),
            'tipo_descripcion' => $this->normalizarTexto(
                (string) $request->input('tipo_descripcion')
            ),
            'tipo_descuento' => $descuento,
            'tipo_aplica_web' => (string) $request->input('tipo_aplica_web', '0') === '1' ? '1' : '0',
            'tipo_aplica_local' => (string) $request->input('tipo_aplica_local', '0') === '1' ? '1' : '0',
            'tipo_estado' => (string) $request->input('tipo_estado', '0') === '1' ? '1' : '0',
        ]);
    }

    /**
     * Reglas compartidas para alta y edición.
     *
     * @return array<string, mixed>
     */
    private function validarCliente(
        Request $request,
        ?Cliente $cliente = null
    ): array {
        $emailUnico = Rule::unique('clientes', 'email');
        $documentoUnico = Rule::unique('clientes', 'documento');

        if ($cliente) {
            $emailUnico->ignore($cliente->idcliente, 'idcliente');
            $documentoUnico->ignore($cliente->idcliente, 'idcliente');
        }

        return $request->validate(
            [
                'idtipocliente' => [
                    'required',
                    'integer',
                    Rule::exists('tipo_cliente', 'idtipocliente')->where(
                        fn ($consulta) => $consulta->where('estado', 1)
                    ),
                ],
                'nombre' => [
                    'required',
                    'string',
                    'max:100',
                    "regex:/^[\\pL0-9\\s'-]+$/u",
                ],
                'apellido' => [
                    'required',
                    'string',
                    'max:100',
                    "regex:/^[\\pL0-9\\s'-]+$/u",
                ],
                'documento' => [
                    'nullable',
                    'string',
                    'max:20',
                    "regex:/^[0-9]+$/",
                    $documentoUnico,
                ],
                'telefono' => [
                    'nullable',
                    'string',
                    'regex:/^[0-9]{7,20}$/',
                ],
                'email' => [
                    'nullable',
                    'string',
                    'email',
                    'max:150',
                    $emailUnico,
                ],
                'direccion' => ['nullable', 'string', 'max:200'],
                'codigopostal' => ['nullable', 'string', 'max:20'],
                'estado' => ['required', 'boolean'],
            ],
            [
                'idtipocliente.required' => 'Seleccioná un tipo de cliente.',
                'idtipocliente.exists' => 'El tipo de cliente seleccionado no está disponible.',
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
                'nombre.regex' => 'El nombre solamente puede contener letras.',
                'apellido.required' => 'El apellido es obligatorio.',
                'apellido.max' => 'El apellido no puede superar los 100 caracteres.',
                'apellido.regex' => 'El apellido solamente puede contener letras.',
                'documento.max' => 'El documento no puede superar los 20 caracteres.',
                'documento.regex' => 'El documento debe contener solamente números.',
                'documento.unique' => 'Ese documento ya está registrado.',
                'telefono.regex' => 'El teléfono debe contener entre 7 y 20 números.',
                'email.email' => 'Ingresá un correo electrónico válido.',
                'email.max' => 'El correo no puede superar los 150 caracteres.',
                'email.unique' => 'Ese correo electrónico ya está registrado.',
                'direccion.max' => 'La dirección no puede superar los 200 caracteres.',
                'codigopostal.max' => 'El código postal no puede superar los 20 caracteres.',
                'estado.required' => 'Indicá el estado del cliente.',
                'estado.boolean' => 'El estado seleccionado no es válido.',
            ]
        );
    }

    /**
     * Normaliza los campos para evitar duplicados por espacios o mayúsculas.
     */
    private function normalizarDatos(Request $request): void
    {
        $telefono = preg_replace(
            '/\\D+/',
            '',
            (string) $request->input('telefono')
        ) ?? '';

        $request->merge([
            'nombre' => $this->normalizarTexto((string) $request->input('nombre')),
            'apellido' => $this->normalizarTexto((string) $request->input('apellido')),
            'documento' => Str::upper(
                preg_replace('/\\s+/u', '', trim((string) $request->input('documento'))) ?? ''
            ),
            'telefono' => $telefono,
            'email' => Str::lower(trim((string) $request->input('email'))),
            'direccion' => $this->normalizarTexto((string) $request->input('direccion')),
            'codigopostal' => Str::upper(trim((string) $request->input('codigopostal'))),
        ]);
    }

    private function normalizarTexto(string $texto): string
    {
        return preg_replace('/\\s+/u', ' ', trim($texto)) ?? '';
    }

    private function normalizarFiltroNumerico(mixed $valor): ?int
    {
        $valor = (string) ($valor ?? '');

        if ($valor === '' || !ctype_digit($valor) || (int) $valor < 1) {
            return null;
        }

        return (int) $valor;
    }

    private function extraerIdDesdeCodigo(string $valor): ?int
    {
        if (!preg_match('/^CLI-0*([1-9][0-9]*)$/i', trim($valor), $coincidencias)) {
            return null;
        }

        return (int) $coincidencias[1];
    }

    private function nullable(mixed $valor): mixed
    {
        if (!is_string($valor)) {
            return $valor;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }
}
