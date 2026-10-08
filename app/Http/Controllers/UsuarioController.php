<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    /**
     * Muestra el personal y permite buscarlo por sus datos principales.
     */
    public function index(Request $request): View
    {
        $busqueda = trim((string) $request->query('buscar', ''));
        $estado = (string) $request->query('estado', 'todos');

        if (!in_array($estado, ['todos', '1', '0'], true)) {
            $estado = 'todos';
        }

        $idRol = $this->normalizarFiltroNumerico(
            $request->query('rol')
        );

        $idSucursal = $this->normalizarFiltroNumerico(
            $request->query('sucursal')
        );

        $usuarios = User::query()
            ->with([
                'rol.permisos',
                'sucursal',
            ])
            ->when($busqueda !== '', function ($consulta) use ($busqueda) {
                $termino = '%' . $busqueda . '%';

                $consulta->where(function ($subconsulta) use ($termino) {
                    $subconsulta
                        ->where('nombre', 'like', $termino)
                        ->orWhere('apellido', 'like', $termino)
                        ->orWhere('email', 'like', $termino)
                        ->orWhere('telefono', 'like', $termino)
                        ->orWhereRaw(
                            "CONCAT(nombre, ' ', apellido) LIKE ?",
                            [$termino]
                        );
                });
            })
            ->when(
                $idRol !== null,
                fn ($consulta) => $consulta->where('idrol', $idRol)
            )
            ->when(
                $idSucursal !== null,
                fn ($consulta) => $consulta->where(
                    'idsucursal',
                    $idSucursal
                )
            )
            ->when(
                $estado !== 'todos',
                fn ($consulta) => $consulta->where(
                    'estado',
                    (int) $estado
                )
            )
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString();

        $roles = Rol::query()
            ->withCount('usuarios')
            ->orderBy('idrol')
            ->get();

        $rolesAsignables = $roles
            ->filter(fn (Rol $rol) => $rol->estado && !$this->esRolDueno($rol))
            ->values();

        $sucursales = Sucursal::query()
            ->withCount('usuarios')
            ->orderBy('nombre')
            ->get();

        $idUsuarioSeleccionado = $this->normalizarFiltroNumerico(
            $request->query('usuario')
        );

        $usuarioSeleccionado = null;

        if ($idUsuarioSeleccionado !== null) {
            $usuarioSeleccionado = User::query()
                ->with([
                    'rol.permisos',
                    'sucursal',
                ])
                ->find($idUsuarioSeleccionado);
        }

        $usuarioSeleccionado ??= $usuarios->first();

        $resumen = User::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS activos')
            ->selectRaw('SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) AS inactivos')
            ->first();

        return view(
            'usuarios.index',
            compact(
                'usuarios',
                'roles',
                'rolesAsignables',
                'sucursales',
                'usuarioSeleccionado',
                'resumen',
                'busqueda',
                'estado',
                'idRol',
                'idSucursal'
            )
        );
    }

    /**
     * Registra un nuevo usuario interno y le asigna un rol.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->normalizarDatos($request);

        $datos = $this->validarUsuario($request, null, true);
        $this->comprobarRolAsignable((int) $datos['idrol']);

        $usuario = DB::transaction(function () use ($datos) {
            return User::create([
                'idrol' => (int) $datos['idrol'],
                'idsucursal' => (int) $datos['idsucursal'],
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'email' => $datos['email'],
                'telefono' => $datos['telefono'],
                'password' => Hash::make($datos['password']),
                'estado' => (bool) $datos['estado'],
            ]);
        });

        return redirect()
            ->route('usuarios.index', ['usuario' => $usuario->id])
            ->with('success', 'El usuario fue creado correctamente.');
    }

    /**
     * Actualiza los datos laborales de un usuario existente.
     */
    public function update(
        Request $request,
        User $usuario
    ): RedirectResponse {
        if ($this->esUsuarioDueno($usuario)) {
            return redirect()
                ->route('usuarios.index', ['usuario' => $usuario->id])
                ->with(
                    'error',
                    'La cuenta DUEÑO está protegida y no puede modificarse.'
                );
        }

        $this->normalizarDatos($request);

        $datos = $this->validarUsuario($request, $usuario, false);
        $this->comprobarRolAsignable((int) $datos['idrol']);

        $usuarioAutenticado = $request->user('web');

        if (
            $usuarioAutenticado
            && $usuarioAutenticado->is($usuario)
            && !(bool) $datos['estado']
        ) {
            throw ValidationException::withMessages([
                'estado' => 'No podés desactivar tu propia cuenta.',
            ]);
        }

        DB::transaction(function () use ($datos, $usuario) {
            $cambios = [
                'idrol' => (int) $datos['idrol'],
                'idsucursal' => (int) $datos['idsucursal'],
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'email' => $datos['email'],
                'telefono' => $datos['telefono'],
                'estado' => (bool) $datos['estado'],
            ];

            if (!empty($datos['password'])) {
                $cambios['password'] = Hash::make($datos['password']);
            }

            $usuario->update($cambios);
        });

        return redirect()
            ->route('usuarios.index', ['usuario' => $usuario->id])
            ->with('success', 'El usuario fue actualizado correctamente.');
    }

    /**
     * Reglas compartidas entre la creación y la edición.
     *
     * @return array<string, mixed>
     */
    private function validarUsuario(
        Request $request,
        ?User $usuario,
        bool $crear
    ): array {
        $emailUnico = Rule::unique('users', 'email');

        if ($usuario) {
            $emailUnico->ignore($usuario->id, 'id');
        }

        $reglaPassword = Password::min(8)
            ->mixedCase()
            ->numbers();

        return $request->validate(
            [
                'nombre' => [
                    'required',
                    'string',
                    'max:100',
                    "regex:/^[\\pL\\s'-]+$/u",
                ],
                'apellido' => [
                    'required',
                    'string',
                    'max:100',
                    "regex:/^[\\pL\\s'-]+$/u",
                ],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    $emailUnico,
                ],
                'telefono' => [
                    'required',
                    'string',
                    'regex:/^[0-9]{7,20}$/',
                ],
                'idrol' => [
                    'required',
                    'integer',
                    Rule::exists('roles', 'idrol')->where(
                        fn ($consulta) => $consulta->where('estado', 1)
                    ),
                ],
                'idsucursal' => [
                    'required',
                    'integer',
                    Rule::exists('sucursales', 'idsucursal')->where(
                        fn ($consulta) => $consulta->where('estado', 1)
                    ),
                ],
                'estado' => [
                    'required',
                    'boolean',
                ],
                'password' => [
                    $crear ? 'required' : 'nullable',
                    'confirmed',
                    $reglaPassword,
                ],
            ],
            [
                'nombre.required' => 'El nombre es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
                'nombre.regex' => 'El nombre solamente puede contener letras.',
                'apellido.required' => 'El apellido es obligatorio.',
                'apellido.max' => 'El apellido no puede superar los 100 caracteres.',
                'apellido.regex' => 'El apellido solamente puede contener letras.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'Ingresá un correo electrónico válido.',
                'email.unique' => 'Ese correo electrónico ya está registrado.',
                'telefono.required' => 'El teléfono es obligatorio.',
                'telefono.regex' => 'El teléfono debe contener entre 7 y 20 números.',
                'idrol.required' => 'Seleccioná un rol.',
                'idrol.exists' => 'El rol seleccionado no está disponible.',
                'idsucursal.required' => 'Seleccioná una sucursal.',
                'idsucursal.exists' => 'La sucursal seleccionada no está disponible.',
                'estado.required' => 'Indicá el estado del usuario.',
                'estado.boolean' => 'El estado seleccionado no es válido.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
            ]
        );
    }

    /**
     * Limpia los campos antes de validarlos y guardarlos.
     */
    private function normalizarDatos(Request $request): void
    {
        $telefono = preg_replace(
            '/\\D+/',
            '',
            (string) $request->input('telefono')
        ) ?? '';

        $request->merge([
            'nombre' => $this->normalizarTexto(
                (string) $request->input('nombre')
            ),
            'apellido' => $this->normalizarTexto(
                (string) $request->input('apellido')
            ),
            'email' => Str::lower(
                trim((string) $request->input('email'))
            ),
            'telefono' => $telefono,
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

    /**
     * Evita asignar el rol principal desde la administración de personal.
     */
    private function comprobarRolAsignable(int $idRol): void
    {
        $rol = Rol::query()->find($idRol);

        if (!$rol || $this->esRolDueno($rol)) {
            throw ValidationException::withMessages([
                'idrol' => 'El rol DUEÑO no puede asignarse desde este formulario.',
            ]);
        }
    }

    private function esRolDueno(Rol $rol): bool
    {
        return (int) $rol->idrol === 1
            || Str::upper($rol->nombre) === 'DUEÑO';
    }

    private function esUsuarioDueno(User $usuario): bool
    {
        $usuario->loadMissing('rol');

        return (int) $usuario->id === 1
            || ($usuario->rol && $this->esRolDueno($usuario->rol));
    }
}
