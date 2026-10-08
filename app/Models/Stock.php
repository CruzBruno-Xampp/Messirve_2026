<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    use HasFactory;

    protected $table = 'stock';

    protected $primaryKey = 'idstock';

    protected $fillable = [
        'idproducto',
        'idsucursal',
        'stockactual',
        'stockminimo',
        'stockmaximo',
        'estado',
    ];

    protected $casts = [
        'stockactual' => 'integer',
        'stockminimo' => 'integer',
        'stockmaximo' => 'integer',
        'estado' => 'boolean',
    ];

    /**
     * Producto al que pertenece este registro de stock.
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
     * Sucursal o depósito donde se encuentra el stock.
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'idsucursal',
            'idsucursal'
        );
    }
}