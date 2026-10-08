<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $primaryKey = 'idsucursal';

    protected $fillable = [
        'nombre',
        'tipo',
        'direccion',
        'telefono',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Registros de stock almacenados en esta ubicación.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(
            Stock::class,
            'idsucursal',
            'idsucursal'
        );
    }

    /**
     * Usuarios internos que trabajan en esta sucursal.
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(
            User::class,
            'idsucursal',
            'idsucursal'
        );
    }
}
