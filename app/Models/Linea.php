<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Linea extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en MySQL.
    protected $table = 'lineas';

    // Clave primaria personalizada.
    protected $primaryKey = 'idlinea';

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
     * Grupos internos que pertenecen a esta línea.
     *
     * lineas.idlinea → grupos.idlinea
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(
            Grupo::class,
            'idlinea',
            'idlinea'
        );
    }
}