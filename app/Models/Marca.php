<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Marca extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en MySQL.
    protected $table = 'marcas';

    // Clave primaria personalizada.
    protected $primaryKey = 'idmarca';

    /**
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'nombre',
        'estado',
    ];

    /**
     * Convierte automáticamente el estado de 0/1 a false/true.
     */
    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Productos comercializados por Messirve que pertenecen a esta marca.
     *
     * marcas.idmarca → productos.idmarca
     */
    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'idmarca',
            'idmarca'
        );
    }

    /**
     * Artículos importados de los catálogos que fueron relacionados
     * con esta marca interna.
     */
    public function detallesCatalogo(): HasMany
    {
        return $this->hasMany(
            DetalleCatalogo::class,
            'idmarca',
            'idmarca'
        );
    }
}