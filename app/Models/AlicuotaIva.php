<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlicuotaIva extends Model
{
    use HasFactory;

    // Nombre exacto de la tabla en MySQL.
    protected $table = 'alicuotas_iva';

    // Clave primaria personalizada.
    protected $primaryKey = 'idiva';

    /**
     * La tabla no posee created_at ni updated_at.
     * Por eso desactivamos los timestamps automáticos de Laravel.
     */
    public $timestamps = false;

    /**
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'nombre',
        'porcentaje',
        'vigente_desde',
        'vigente_hasta',
        'estado',
    ];

    /**
     * Conversión automática de tipos al recuperar datos desde MySQL.
     */
    protected $casts = [
        'porcentaje' => 'decimal:6',
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'estado' => 'boolean',
    ];

    /**
     * Productos que utilizan esta alícuota de IVA.
     *
     * alicuotas_iva.idiva → productos.idiva
     */
    public function productos(): HasMany
    {
        return $this->hasMany(
            Producto::class,
            'idiva',
            'idiva'
        );
    }

    /**
     * Filas importadas de los catálogos que utilizan esta alícuota.
     */
    public function detallesCatalogo(): HasMany
    {
        return $this->hasMany(
            DetalleCatalogo::class,
            'idiva',
            'idiva'
        );
    }
}