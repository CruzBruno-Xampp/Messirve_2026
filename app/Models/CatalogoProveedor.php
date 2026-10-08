<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoProveedor extends Model
{
    use HasFactory;

    // Nombre real de la tabla en la base de datos.
    protected $table = 'catalogo_proveedor';

    // Clave primaria personalizada de la tabla.
    protected $primaryKey = 'idcatalogoproveedor';

    /*
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'idproveedor',
        'idusuario_importa',
        'nombre_lista',
        'archivo_original',
        'fecha_vigencia',
        'descuento_caja',
        'descuento_volumen',
        'descuento_especial',
        'descuento_total',

        // Indica si los precios importados ya incluyen IVA.
        // 1 = incluyen IVA.
        // 0 = no incluyen IVA.
        'precios_incluyen_iva',

        'rentabilidad_predeterminada',
        'estado',
    ];

    /*
     * Convierte automáticamente los valores obtenidos de MySQL
     * a tipos de datos apropiados para trabajar en Laravel.
     */
    protected $casts = [
        'fecha_vigencia' => 'date',
        'descuento_caja' => 'decimal:2',
        'descuento_volumen' => 'decimal:2',
        'descuento_especial' => 'decimal:2',
        'descuento_total' => 'decimal:4',
        'rentabilidad_predeterminada' => 'decimal:2',

        // Laravel convierte el 0 y 1 de MySQL en false y true.
        'precios_incluyen_iva' => 'boolean',
        'estado' => 'boolean',
    ];

    /*
     * Proveedor al que pertenece este catálogo.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(
            Proveedor::class,
            'idproveedor',
            'idproveedor'
        );
    }

    /*
     * Usuario del sistema que realizó la importación.
     */
    public function usuarioImporta(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'idusuario_importa',
            'id'
        );
    }

    /*
     * Productos o filas que contiene este catálogo.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetalleCatalogo::class,
            'idcatalogoproveedor',
            'idcatalogoproveedor'
        );
    }
}