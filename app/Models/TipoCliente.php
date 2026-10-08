<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCliente extends Model
{
    protected $table = 'tipo_cliente';

    protected $primaryKey = 'idtipocliente';

    public $timestamps = false;

    protected $fillable = [
        'idlistaprecio',
        'nombre',
        'descripcion',
        'descuento',
        'aplica_web',
        'aplica_local',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'descuento' => 'decimal:2',
            'aplica_web' => 'boolean',
            'aplica_local' => 'boolean',
            'estado' => 'integer',
        ];
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(
            Cliente::class,
            'idtipocliente',
            'idtipocliente'
        );
    }

    public function esClienteWeb(): bool
    {
        return mb_strtoupper(trim((string) $this->nombre), 'UTF-8') === 'CLIENTE_WEB';
    }
}
