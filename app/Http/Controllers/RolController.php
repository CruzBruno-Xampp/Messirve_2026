<?php

namespace App\Http\Controllers;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RolController extends Controller
{
    /**
     * Muestra los roles junto con los permisos asignados a cada uno.
     */
    public function index(Request $request): View
    {
        $roles = Rol::query()
            ->with([
                'permisos' => function ($consulta) {
                    $consulta->orderBy('codigo');
                },
            ])
            ->withCount('usuarios')
            ->orderBy('idrol')
            ->get();

        $permisos = Permiso::query()
            ->orderBy('codigo')
            ->get();

        $idRolSeleccionado = (int) $request->query('rol', 0);

        $rolSeleccionado = $roles->firstWhere(
            'idrol',
            $idRolSeleccionado
        ) ?? $roles->first();

        return view(
            'roles.index',
            compact('roles', 'permisos', 'rolSeleccionado')
        );
    }

    /**
     * Crea un rol personalizado y registra sus permisos.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'nombre' => $this->normalizarNombre(
                (string) $request->input('nombre')
            ),
        ]);

        $datos = $this->validarRol($request);

        if ($datos['nombre'] === 'DUEÑO') {
            throw ValidationException::withMessages([
                'nombre' =>
                    'El nombre DUEÑO está reservado para el rol principal.',
            ]);
        }

        $rol = DB::transaction(function () use ($datos) {
            $descripcion = trim((string) ($datos['descripcion'] ?? ''));

            $rol = Rol::create([
                'nombre' => $datos['nombre'],
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'estado' => (bool) $datos['estado'],
            ]);

            $rol->permisos()->sync(
                $this->normalizarPermisos($datos['permisos'])
            );

            return $rol;
        });

        return redirect()
            ->route('roles.index', ['rol' => $rol->idrol])
            ->with('success', 'El rol fue creado correctamente.');
    }

    /**
     * Actualiza los datos y permisos de un rol existente.
     */
    public function update(
        Request $request,
        Rol $rol
    ): RedirectResponse {
        if ($this->esRolDueno($rol)) {
            return redirect()
                ->route('roles.index', ['rol' => $rol->idrol])
                ->with(
                    'error',
                    'El rol DUEÑO está protegido y no puede modificarse.'
                );
        }

        $request->merge([
            'nombre' => $this->normalizarNombre(
                (string) $request->input('nombre')
            ),
        ]);

        $datos = $this->validarRol($request, $rol);

        if ($datos['nombre'] === 'DUEÑO') {
            throw ValidationException::withMessages([
                'nombre' =>
                    'El nombre DUEÑO está reservado para el rol principal.',
            ]);
        }

        DB::transaction(function () use ($datos, $rol) {
            $descripcion = trim((string) ($datos['descripcion'] ?? ''));

            $rol->update([
                'nombre' => $datos['nombre'],
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'estado' => (bool) $datos['estado'],
            ]);

            $rol->permisos()->sync(
                $this->normalizarPermisos($datos['permisos'])
            );
        });

        return redirect()
            ->route('roles.index', ['rol' => $rol->idrol])
            ->with('success', 'El rol fue actualizado correctamente.');
    }

    /**
     * Valida los campos compartidos por el alta y la edición.
     *
     * @return array<string, mixed>
     */
    private function validarRol(
        Request $request,
        ?Rol $rol = null
    ): array {
        $nombreUnico = Rule::unique('roles', 'nombre');

        if ($rol) {
            $nombreUnico->ignore($rol->idrol, 'idrol');
        }

        return $request->validate(
            [
                'nombre' => [
                    'required',
                    'string',
                    'max:50',
                    $nombreUnico,
                ],
                'descripcion' => [
                    'nullable',
                    'string',
                    'max:150',
                ],
                'estado' => [
                    'required',
                    'boolean',
                ],
                'permisos' => [
                    'required',
                    'array',
                    'min:1',
                ],
                'permisos.*' => [
                    'required',
                    'integer',
                    'distinct',
                    Rule::exists('permisos', 'idpermiso'),
                ],
            ],
            [
                'nombre.required' => 'El nombre del rol es obligatorio.',
                'nombre.max' =>
                    'El nombre del rol no puede superar los 50 caracteres.',
                'nombre.unique' => 'Ya existe un rol con ese nombre.',
                'descripcion.max' =>
                    'La descripción no puede superar los 150 caracteres.',
                'estado.required' => 'Tenés que indicar el estado del rol.',
                'estado.boolean' => 'El estado seleccionado no es válido.',
                'permisos.required' =>
                    'Seleccioná al menos un permiso para el rol.',
                'permisos.array' => 'Los permisos seleccionados no son válidos.',
                'permisos.min' =>
                    'Seleccioná al menos un permiso para el rol.',
                'permisos.*.integer' =>
                    'Uno de los permisos seleccionados no es válido.',
                'permisos.*.distinct' =>
                    'No se puede repetir un mismo permiso.',
                'permisos.*.exists' =>
                    'Uno de los permisos seleccionados no existe.',
            ]
        );
    }

    /**
     * Convierte el nombre a mayúsculas y elimina espacios repetidos.
     */
    private function normalizarNombre(string $nombre): string
    {
        $nombre = preg_replace('/\s+/u', ' ', trim($nombre)) ?? '';

        return Str::upper($nombre);
    }

    /**
     * Convierte los identificadores validados a números enteros.
     *
     * @param array<int, mixed> $permisos
     * @return array<int, int>
     */
    private function normalizarPermisos(array $permisos): array
    {
        return array_map(
            static fn ($idPermiso): int => (int) $idPermiso,
            $permisos
        );
    }

    /**
     * Evita que se desactive o altere el rol principal del sistema.
     */
    private function esRolDueno(Rol $rol): bool
    {
        return $rol->idrol === 1
            || Str::upper($rol->nombre) === 'DUEÑO';
    }
}
