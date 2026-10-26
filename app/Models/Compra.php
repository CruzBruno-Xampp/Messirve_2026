<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use HasFactory;

    protected $table = 'compras';

    protected $primaryKey = 'idcompra';

    protected $fillable = [
        'idproveedor',
        'idsucursal',
        'idusuario',
        'fecha',
        'tipocomprobante',
        'numerocomprobante',
        'total',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'total' => 'decimal:2',
    ];

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(
            Proveedor::class,
            'idproveedor',
            'idproveedor'
        );
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'idsucursal',
            'idsucursal'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'idusuario',
            'id'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetalleCompra::class,
            'idcompra',
            'idcompra'
        );
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            Movimiento::class,
            'idcompra',
            'idcompra'
        );
    }
}