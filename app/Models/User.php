<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'idrol',
        'idsucursal',
        'nombre',
        'apellido',
        'email',
        'telefono',
        'password',
        'estado',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'estado' => 'boolean',
    ];

    /**
     * Rol asignado al usuario interno.
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(
            Rol::class,
            'idrol',
            'idrol'
        );
    }

    /**
     * Sucursal en la que trabaja el usuario interno.
     *
     * La cuenta DUEÑO puede no tener una sucursal porque su alcance es global.
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'idsucursal',
            'idsucursal'
        );
    }

    /**
     * Indica si el usuario puede ejecutar una acción del sistema.
     *
     * El rol DUEÑO se mantiene protegido con acceso completo. Los demás
     * roles reciben sus permisos desde la tabla intermedia rol_permiso.
     */
    public function tienePermiso(string $codigo): bool
    {
        if (!$this->estado) {
            return false;
        }

        $rol = $this->rol;

        if (!$rol || !$rol->estado) {
            return false;
        }

        if ($rol->nombre === 'DUEÑO') {
            return true;
        }

        return $rol->permisos->contains(
            'codigo',
            $codigo
        );
    }
}
