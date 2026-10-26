<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleCompra extends Model
{
    use HasFactory;

    protected $table = 'detalle_compra';

    protected $primaryKey = 'iddetallecompra';

    public $timestamps = false;

    protected $fillable = [
        'idcompra',
        'idproducto',
        'iddetallecatalogo',
        'idiva',
        'cantidad',
        'cantidadrecibida',
        'cantidadproblema',
        'observacionrecepcion',
        'estadoincidencia',
        'costounitario',
        'subtotal',
        'importeiva',
        'totallinea',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'cantidadrecibida' => 'integer',
        'cantidadproblema' => 'integer',
        'costounitario' => 'decimal:6',
        'subtotal' => 'decimal:6',
        'importeiva' => 'decimal:6',
        'totallinea' => 'decimal:6',
    ];

    public function compra(): BelongsTo
    {
        return $this->belongsTo(
            Compra::class,
            'idcompra',
            'idcompra'
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

    public function detalleCatalogo(): BelongsTo
    {
        return $this->belongsTo(
            DetalleCatalogo::class,
            'iddetallecatalogo',
            'iddetallecatalogo'
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