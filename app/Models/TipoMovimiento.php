<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoMovimiento extends Model
{
    use HasFactory;

    protected $table = 'tipos_movimiento';

    protected $primaryKey = 'idtipomovimiento';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'signo',
        'estado',
    ];

    protected $casts = [
        'signo' => 'integer',
        'estado' => 'integer',
    ];

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            Movimiento::class,
            'idtipomovimiento',
            'idtipomovimiento'
        );
    }
}
