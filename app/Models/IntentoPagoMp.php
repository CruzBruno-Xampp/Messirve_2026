<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentoPagoMp extends Model
{
    protected $table = 'intentos_pago_mp';

    protected $fillable = [
        'referencia',
        'idusuario',
        'idsucursal',
        'idventa',
        'datos_venta',
        'importe',
        'estado',
        'mp_preference_id',
        'mp_payment_id',
        'pagado_at',
        'confirmado_at',
        'mp_order_id',
        'qr_idempotency_key',
        'qr_external_pos_id',
    ];

    protected $casts = [
        'datos_venta' => 'array',
        'importe' => 'decimal:2',
        'pagado_at' => 'datetime',
        'confirmado_at' => 'datetime',
    ];

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'idventa', 'idventa');
    }
}
