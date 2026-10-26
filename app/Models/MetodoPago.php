<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';

    protected $primaryKey = 'idmetodopago';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'recargo',
        'estado',
    ];

    protected $casts = [
        'recargo' => 'decimal:2',
        'estado' => 'boolean',
    ];

    public function ventas(): HasMany
    {
        return $this->hasMany(
            Venta::class,
            'idmetodopago',
            'idmetodopago'
        );
    }
}
