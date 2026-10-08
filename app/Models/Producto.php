<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en la base de datos.
    protected $table = 'productos';

    // Clave primaria personalizada.
    protected $primaryKey = 'idproducto';

    /**
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'codigointerno',
        'codigofabricante',
        'codigobarra',
        'descripcion',
        'idmarca',
        'idsubgrupo',
        'color',
        'contenido_neto',
        'idunidad',
        'rutaimagen',
        'idiva',
        'rentabilidadobjetivo',
        'pvp',
        'visibleweb',
        'destacado',
        'estado',
    ];

    /**
     * Conversión automática de tipos al recuperar datos desde MySQL.
     */
    protected $casts = [
        'contenido_neto' => 'decimal:3',
        'rentabilidadobjetivo' => 'decimal:2',
        'pvp' => 'decimal:2',
        'visibleweb' => 'boolean',
        'destacado' => 'boolean',
        'estado' => 'boolean',
    ];

    /**
     * Marca interna del producto.
     *
     * productos.idmarca → marcas.idmarca
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(
            Marca::class,
            'idmarca',
            'idmarca'
        );
    }

    /**
     * Subgrupo interno del producto.
     *
     * Desde el subgrupo también se puede obtener su grupo y línea.
     */
    public function subgrupo(): BelongsTo
    {
        return $this->belongsTo(
            Subgrupo::class,
            'idsubgrupo',
            'idsubgrupo'
        );
    }

    /**
     * Alícuota de IVA utilizada por el producto.
     *
     * productos.idiva → alicuotas_iva.idiva
     */
    public function alicuotaIva(): BelongsTo
    {
        return $this->belongsTo(
            AlicuotaIva::class,
            'idiva',
            'idiva'
        );
    }

    /**
     * Ofertas o apariciones de este producto en los catálogos
     * enviados por diferentes proveedores.
     */
    public function detallesCatalogo(): HasMany
    {
        return $this->hasMany(
            DetalleCatalogo::class,
            'idproducto',
            'idproducto'
        );
    }

    /**
     * Existencias del producto en cada sucursal.
     *
     * Un producto puede tener un registro de stock por sucursal.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(
            Stock::class,
            'idproducto',
            'idproducto'
        );
    }
}
