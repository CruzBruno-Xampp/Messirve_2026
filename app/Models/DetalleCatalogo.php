<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCatalogo extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en MySQL.
    protected $table = 'detalle_catalogo';

    // Clave primaria personalizada.
    protected $primaryKey = 'iddetallecatalogo';

    /**
     * Campos que pueden guardarse mediante create() o update().
     */
    protected $fillable = [
        'idcatalogoproveedor',

        // Queda NULL hasta que el artículo se relacione con un producto de Messirve.
        'idproducto',

        // Identificadores recibidos del proveedor.
        'codigoproveedor',
        'codigofabricante',
        'codigobarra',
        'descripcionproveedor',

        // Clasificación original recibida en el Excel.
        'linea_proveedor',
        'tipo_producto_proveedor',
        'grupo_proveedor',
        'subgrupo_proveedor',

        // Relaciones con la clasificación interna de Messirve.
        // Pueden quedar NULL mientras se revisa la importación.
        'idmarca',
        'idsubgrupo',
        'idiva',

        // Datos económicos enviados por el proveedor.
        'preciolista',
        'descuentoproducto',
        'precioneto',

        'estado',
    ];

    /**
     * Conversión automática de tipos al recuperar datos de MySQL.
     */
    protected $casts = [
        // Se conservan seis decimales para respetar la precisión del Excel.
        'preciolista' => 'decimal:6',

        'descuentoproducto' => 'decimal:2',
        'precioneto' => 'decimal:6',
        'estado' => 'boolean',
    ];

    /**
     * Lista u hoja del proveedor a la que pertenece esta fila.
     *
     * detalle_catalogo.idcatalogoproveedor
     * → catalogo_proveedor.idcatalogoproveedor
     */
    public function catalogoProveedor(): BelongsTo
    {
        return $this->belongsTo(
            CatalogoProveedor::class,
            'idcatalogoproveedor',
            'idcatalogoproveedor'
        );
    }

    /**
     * Producto interno de Messirve relacionado con esta oferta.
     *
     * Puede ser NULL mientras el artículo solamente forme parte
     * del catálogo del proveedor.
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class,
            'idproducto',
            'idproducto'
        );
    }

    /**
     * Marca interna asignada al artículo importado.
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
     * Subgrupo interno de Messirve.
     *
     * Desde este subgrupo se pueden obtener su grupo y su línea,
     * por eso no guardamos idgrupo ni idlinea en detalle_catalogo.
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
     * Alícuota de IVA que corresponde al artículo.
     */
    public function alicuotaIva(): BelongsTo
    {
        return $this->belongsTo(
            AlicuotaIva::class,
            'idiva',
            'idiva'
        );
    }
}