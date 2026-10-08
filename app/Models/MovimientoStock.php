<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    public const TIPO_INGRESO = 'INGRESO';
    public const TIPO_TRANSFERENCIA = 'TRANSFERENCIA';

    protected $table = 'movimientos_stock';

    protected $primaryKey = 'idmovimiento';

    protected $fillable = [
        'idproducto',
        'idsucursalorigen',
        'idsucursaldestino',
        'idusuario',
        'tipo',
        'cantidad',
        'stockorigenantes',
        'stockorigendespues',
        'stockdestinoantes',
        'stockdestinodespues',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'stockorigenantes' => 'integer',
        'stockorigendespues' => 'integer',
        'stockdestinoantes' => 'integer',
        'stockdestinodespues' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'idproducto', 'idproducto');
    }

    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'idsucursalorigen', 'idsucursal');
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'idsucursaldestino', 'idsucursal');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idusuario', 'id');
    }
}
