<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleMovimiento extends Model
{
    use HasFactory;

    protected $table = 'detalle_movimiento';

    protected $primaryKey = 'iddetallemovimiento';

    public $timestamps = false;

    protected $fillable = [
        'idmovimiento',
        'idproducto',
        'cantidad',
        'costounitario',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'costounitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(
            Movimiento::class,
            'idmovimiento',
            'idmovimiento'
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
}
