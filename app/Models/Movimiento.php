<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movimiento extends Model
{
    use HasFactory;

    protected $table = 'movimientos';

    protected $primaryKey = 'idmovimiento';

    /** La tabla posee created_at, pero no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'idtipomovimiento',
        'idusuario',
        'idsucursalorigen',
        'idsucursaldestino',
        'idcompra',
        'idventa',
        'idpedido',
        'iddevolucion',
        'fecha',
        'referencia',
        'observacion',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'estado' => 'integer',
        'created_at' => 'datetime',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(
            TipoMovimiento::class,
            'idtipomovimiento',
            'idtipomovimiento'
        );
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'idusuario', 'id');
    }

    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'idsucursalorigen',
            'idsucursal'
        );
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'idsucursaldestino',
            'idsucursal'
        );
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'idventa', 'idventa');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetalleMovimiento::class,
            'idmovimiento',
            'idmovimiento'
        );
    }
}
