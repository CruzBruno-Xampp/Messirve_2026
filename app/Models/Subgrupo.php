<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subgrupo extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en MySQL.
    protected $table = 'subgrupos';

    // Clave primaria personalizada.
    protected $primaryKey = 'idsubgrupo';

    /**
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'idgrupo',
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
     * Grupo interno al que pertenece este subgrupo.
     *
     * subgrupos.idgrupo → grupos.idgrupo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(
            Grupo::class,
            'idgrupo',
            'idgrupo'
        );
    }

    /**
     * Productos comercializados por Messirve clasificados
     * dentro de este subgrupo.
     */
    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'idsubgrupo',
            'idsubgrupo'
        );
    }

    /**
     * Filas de catálogos de proveedores relacionadas
     * con este subgrupo interno.
     */
    public function detallesCatalogo(): HasMany
    {
        return $this->hasMany(
            DetalleCatalogo::class,
            'idsubgrupo',
            'idsubgrupo'
        );
    }
}