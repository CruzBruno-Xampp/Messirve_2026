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
        'porcentajedescuentocliente',
        'porcentajedescuentoadicional',
        'idmetodopago',
        'porcentajerecargo',
        'importerecargo',
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
        'porcentajedescuentocliente' => 'decimal:2',
        'porcentajedescuentoadicional' => 'decimal:2',
        'porcentajerecargo' => 'decimal:2',
        'importerecargo' => 'decimal:2',
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
     * Medio de pago utilizado al confirmar la venta.
     */
    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(
            MetodoPago::class,
            'idmetodopago',
            'idmetodopago'
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
