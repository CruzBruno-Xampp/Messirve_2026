<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleVenta extends Model
{
    use HasFactory;

    protected $table = 'detalle_venta';

    protected $primaryKey = 'iddetalleventa';

    /**
     * detalle_venta no posee las columnas created_at y updated_at.
     */
    public $timestamps = false;

    protected $fillable = [
        'idventa',
        'idproducto',
        'idiva',
        'cantidad',
        'costounitario',
        'preciounitario',
        'subtotal',
        'descuento',
        'importeiva',
        'totallinea',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'costounitario' => 'decimal:2',
        'preciounitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'importeiva' => 'decimal:2',
        'totallinea' => 'decimal:2',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(
            Venta::class,
            'idventa',
            'idventa'
        );
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(
            Producto::class,
            'idproducto',
            'idproducto'
        );
    }

    public function alicuotaIva(): BelongsTo
    {
        return $this->belongsTo(
            AlicuotaIva::class,
            'idiva',
            'idiva'
        );
    }
}
