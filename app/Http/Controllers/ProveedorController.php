<?php

namespace App\Http\Controllers;

use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    /**
     * Muestra los proveedores registrados, filtros y el perfil seleccionado.
     */
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', 'todos');

        if (!in_array($estado, ['todos', '1', '0'], true)) {
            $estado = 'todos';
        }

        $proveedores = Proveedor::query()
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $termino = '%' . $busqueda . '%';
                $digitos = preg_replace('/\D+/', '', $busqueda) ?? '';
                $idDesdeCodigo = $this->extraerIdDesdeCodigo($busqueda);

                $consulta->where(function ($subconsulta) use (
                    $termino,
                    $digitos,
                    $idDesdeCodigo
                ) {
                    $subconsulta
                        ->where('razonsocial', 'like', $termino)
                        ->orWhere('cuit', 'like', $termino)
                        ->orWhere('contacto', 'like', $termino)
                        ->orWhere('telefono', 'like', $termino)
                        ->orWhere('email', 'like', $termino)
                        ->orWhere('direccion', 'like', $termino);

                    if ($digitos !== '') {
                        $subconsulta->orWhereRaw(
                            "REPLACE(cuit, '-', '') LIKE ?",
                            ['%' . $digitos . '%']
                        );
                    }

                    if ($idDesdeCodigo !== null) {
                        $subconsulta->orWhere('idproveedor', $idDesdeCodigo);
                    }
                });
            })
            ->when(
                $estado !== 'todos',
                fn ($consulta) => $consulta->where('estado', (int) $estado)
            )
            ->orderByDesc('estado')
            ->orderBy('razonsocial')
            ->paginate(10)
            ->withQueryString();

        $idProveedorSeleccionado = $this->normalizarFiltroNumerico(
            $request->query('proveedor')
        );

        // Si la edición vuelve con errores, mantenemos abierto el proveedor editado.
        if (
            old('_formulario') === 'editar'
            && $this->normalizarFiltroNumerico(old('_proveedor_id')) !== null
        ) {
            $idProveedorSeleccionado = $this->normalizarFiltroNumerico(
                old('_proveedor_id')
            );
        }

        $proveedorSeleccionado = null;

        if ($idProveedorSeleccionado !== null) {
            $proveedorSeleccionado = Proveedor::query()
                ->find($idProveedorSeleccionado);
        }

        $proveedorSeleccionado ??= $proveedores->first();

        $resumen = Proveedor::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activos')
            ->selectRaw('SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) AS inactivos')
            ->first();

        return view(
            'proveedores.index',
            compact(
                'proveedores',
                'proveedorSeleccionado',
                'resumen',
                'busqueda',
                'estado'
            )
        );
    }

    /**
     * Registra un proveedor desde el panel administrativo.
     */
    public function store(Request $request): RedirectResponse
    {
        // Si falla la validación, el modal vuelve a abrirse con los datos cargados.
        $request->session()->flash('abrir_modal_nuevo_proveedor', true);

        $this->normalizarDatos($request);
        $datos = $this->validarProveedor($request);

        $proveedor = DB::transaction(function () use ($datos) {
            return Proveedor::create([
                'razonsocial' => $datos['razonsocial'],
                'cuit' => $this->nullable($datos['cuit'] ?? null),
                'contacto' => $this->nullable($datos['contacto'] ?? null),
                'telefono' => $this->nullable($datos['telefono'] ?? null),
                'email' => $this->nullable($datos['email'] ?? null),
                'direccion' => $this->nullable($datos['direccion'] ?? null),
                'estado' => (bool) $datos['estado'],
            ]);
        });

        $request->session()->forget('abrir_modal_nuevo_proveedor');

        return redirect()
            ->route('proveedores.index', ['proveedor' => $proveedor->idproveedor])
            ->with('success', 'El proveedor fue registrado correctamente.');
    }

    /**
     * Actualiza un proveedor existente.
     */
    public function update(
        Request $request,
        Proveedor $proveedor
    ): RedirectResponse {
        $this->normalizarDatos($request);
        $datos = $this->validarProveedor($request, $proveedor);

        DB::transaction(function () use ($datos, $proveedor) {
            $proveedor->update([
                'razonsocial' => $datos['razonsocial'],
                'cuit' => $this->nullable($datos['cuit'] ?? null),
                'contacto' => $this->nullable($datos['contacto'] ?? null),
                'telefono' => $this->nullable($datos['telefono'] ?? null),
                'email' => $this->nullable($datos['email'] ?? null),
                'direccion' => $this->nullable($datos['direccion'] ?? null),
                'estado' => (bool) $datos['estado'],
            ]);
        });

        return redirect()
            ->route('proveedores.index', ['proveedor' => $proveedor->idproveedor])
            ->with('success', 'El proveedor fue actualizado correctamente.');
    }

    /**
     * Registra un proveedor sin abandonar la importación de catálogo.
     */
    public function guardarRapido(Request $request): JsonResponse
    {
        // El alta rápida siempre crea un proveedor activo.
        $request->merge(['estado' => 1]);

        $this->normalizarDatos($request);
        $datos = $this->validarProveedor($request);

        $proveedor = Proveedor::create([
            'razonsocial' => $datos['razonsocial'],
            'cuit' => $this->nullable($datos['cuit'] ?? null),
            'contacto' => $this->nullable($datos['contacto'] ?? null),
            'telefono' => $this->nullable($datos['telefono'] ?? null),
            'email' => $this->nullable($datos['email'] ?? null),
            'direccion' => $this->nullable($datos['direccion'] ?? null),
            'estado' => 1,
        ]);

        return response()->json(
            [
                'mensaje' => 'Proveedor agregado correctamente.',
                'proveedor' => [
                    'idproveedor' => $proveedor->idproveedor,
                    'razonsocial' => $proveedor->razonsocial,
                ],
            ],
            201
        );
    }

    /**
     * Reglas compartidas por alta, edición y alta rápida.
     *
     * @return array<string, mixed>
     */
    private function validarProveedor(
        Request $request,
        ?Proveedor $proveedor = null
    ): array {
        $razonSocialUnica = Rule::unique('proveedores', 'razonsocial');
        $cuitUnico = Rule::unique('proveedores', 'cuit');

        if ($proveedor) {
            $razonSocialUnica->ignore($proveedor->idproveedor, 'idproveedor');
            $cuitUnico->ignore($proveedor->idproveedor, 'idproveedor');
        }

        return $request->validate(
            [
                'razonsocial' => [
                    'required',
                    'string',
                    'max:150',
                    $razonSocialUnica,
                ],
                'cuit' => [
                    'nullable',
                    'string',
                    'max:13',
                    'regex:/^\d{2}-\d{8}-\d$/',
                    $cuitUnico,
                ],
                'contacto' => [
                    'nullable',
                    'string',
                    'max:100',
                    "regex:/^[\\pL\\pM\\s'-]+$/u",
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
                ],
                'direccion' => ['nullable', 'string', 'max:200'],
                'estado' => ['required', 'boolean'],
            ],
            [
                'razonsocial.required' => 'Ingresá la razón social del proveedor.',
                'razonsocial.max' => 'La razón social no puede superar los 150 caracteres.',
                'razonsocial.unique' => 'Ya existe un proveedor con esa razón social.',
                'cuit.regex' => 'Ingresá un CUIT válido, por ejemplo 30-12345678-9.',
                'cuit.unique' => 'Ya existe un proveedor registrado con ese CUIT.',
                'contacto.regex' => 'La persona de contacto admite solamente letras y espacios.',
                'contacto.max' => 'La persona de contacto no puede superar los 100 caracteres.',
                'telefono.regex' => 'El teléfono debe contener entre 7 y 20 números.',
                'email.email' => 'Ingresá una dirección de correo electrónico válida.',
                'email.max' => 'El correo no puede superar los 150 caracteres.',
                'direccion.max' => 'La dirección no puede superar los 200 caracteres.',
                'estado.required' => 'Indicá el estado del proveedor.',
                'estado.boolean' => 'El estado seleccionado no es válido.',
            ]
        );
    }

    /**
     * Normaliza datos para guardar y comparar siempre con el mismo formato.
     */
    private function normalizarDatos(Request $request): void
    {
        $cuit = $this->normalizarCuit((string) $request->input('cuit', ''));
        $telefono = preg_replace(
            '/\D+/',
            '',
            (string) $request->input('telefono', '')
        ) ?? '';

        $request->merge([
            'razonsocial' => $this->normalizarTexto(
                (string) $request->input('razonsocial', '')
            ),
            'cuit' => $cuit,
            'contacto' => $this->normalizarTexto(
                (string) $request->input('contacto', '')
            ),
            'telefono' => $telefono,
            'email' => Str::lower(trim((string) $request->input('email', ''))),
            'direccion' => $this->normalizarTexto(
                (string) $request->input('direccion', '')
            ),
        ]);
    }

    private function normalizarCuit(string $cuit): string
    {
        $cuit = trim($cuit);

        if ($cuit === '') {
            return '';
        }

        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';

        if (strlen($digitos) !== 11) {
            return $cuit;
        }

        return substr($digitos, 0, 2)
            . '-'
            . substr($digitos, 2, 8)
            . '-'
            . substr($digitos, 10, 1);
    }

    private function normalizarTexto(string $texto): string
    {
        return preg_replace('/\s+/u', ' ', trim($texto)) ?? '';
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
        if (!preg_match('/^PRV-0*([1-9][0-9]*)$/i', trim($valor), $coincidencias)) {
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
