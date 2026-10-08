<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $primaryKey = 'idventa';

    protected $fillable = [
        'idcliente',
        'idusuario',
        'idsucursal',
        'fecha',
        'canal',
        'tipo',
        'estado',
        'subtotal',
        'descuento',
        'total',
        'facturada',
        'tipofactura',
        'numerofactura',
        'facturada_at',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'facturada' => 'boolean',
        'facturada_at' => 'datetime',
    ];

    /**
     * Cliente asociado a la venta. Puede quedar vacío en una venta de mostrador.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            Cliente::class,
            'idcliente',
            'idcliente'
        );
    }

    /**
     * Usuario interno que confirmó la venta.
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'idusuario',
            'id'
        );
    }

    /**
     * Sucursal en la que se realizó la operación.
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'idsucursal',
            'idsucursal'
        );
    }

    /**
     * Productos y cantidades incluidos en la venta.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetalleVenta::class,
            'idventa',
            'idventa'
        );
    }
}
