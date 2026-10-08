<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en MySQL.
    protected $table = 'grupos';

    // Clave primaria personalizada.
    protected $primaryKey = 'idgrupo';

    /**
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'idlinea',
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
     * Línea interna a la que pertenece este grupo.
     *
     * grupos.idlinea → lineas.idlinea
     */
    public function linea(): BelongsTo
    {
        return $this->belongsTo(
            Linea::class,
            'idlinea',
            'idlinea'
        );
    }

    /**
     * Subgrupos que pertenecen a este grupo.
     */
    public function subgrupos(): HasMany
    {
        return $this->hasMany(
            Subgrupo::class,
            'idgrupo',
            'idgrupo'
        );
    }
}