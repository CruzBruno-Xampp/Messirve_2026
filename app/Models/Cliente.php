<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Cliente extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, Notifiable;

    protected $table = 'clientes';

    protected $primaryKey = 'idcliente';

    protected $fillable = [
        'idtipocliente',
        'nombre',
        'apellido',
        'documento',
        'telefono',
        'email',
        'password',
        'direccion',
        'codigopostal',
        'estado',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'estado' => 'integer',
    ];

    public function tipoCliente(): BelongsTo
    {
        return $this->belongsTo(
            TipoCliente::class,
            'idtipocliente',
            'idtipocliente'
        );
    }
}
