<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoProveedor extends Model
{
    use HasFactory;

    // Nombre real de la tabla en la base de datos.
    protected $table = 'catalogo_proveedor';

    // Clave primaria personalizada de la tabla.
    protected $primaryKey = 'idcatalogoproveedor';

    /*
     * Campos que Laravel puede guardar mediante create() o update().
     */
    protected $fillable = [
        'idproveedor',
        'idusuario_importa',
        'nombre_lista',
        'archivo_original',
        'fecha_vigencia',
        'descuento_caja',
        'descuento_volumen',
        'descuento_especial',
        'descuento_total',

        // Indica si los precios importados ya incluyen IVA.
        // 1 = incluyen IVA.
        // 0 = no incluyen IVA.
        'precios_incluyen_iva',

        'rentabilidad_predeterminada',
        'estado',
    ];

    /*
     * Convierte automáticamente los valores obtenidos de MySQL
     * a tipos de datos apropiados para trabajar en Laravel.
     */
    protected $casts = [
        'fecha_vigencia' => 'date',
        'descuento_caja' => 'decimal:2',
        'descuento_volumen' => 'decimal:2',
        'descuento_especial' => 'decimal:2',
        'descuento_total' => 'decimal:4',
        'rentabilidad_predeterminada' => 'decimal:2',

        // Laravel convierte el 0 y 1 de MySQL en false y true.
        'precios_incluyen_iva' => 'boolean',
        'estado' => 'boolean',
    ];



    /**
     * Sincroniza qué lista debe quedar vigente según la fecha actual.
     *
     * Reglas:
     * - una lista con fecha futura queda programada (estado 0);
     * - la lista con fecha más reciente menor o igual a hoy queda vigente;
     * - si no hay listas fechadas aplicables, se usa la lista sin fecha más reciente;
     * - si todas las listas tienen fecha futura, ninguna queda vigente todavía.
     *
     * Puede recibir un proveedor concreto o sincronizar todos.
     */
    public static function sincronizarVigencias(?int $idProveedor = null): void
    {
        $consultaProveedores = static::query()
            ->select('idproveedor')
            ->distinct();

        if ($idProveedor !== null) {
            $consultaProveedores->where('idproveedor', $idProveedor);
        }

        $idsProveedores = $consultaProveedores
            ->pluck('idproveedor')
            ->map(fn ($id) => (int) $id);

        $hoy = now()->toDateString();

        foreach ($idsProveedores as $id) {
            static::query()
                ->where('idproveedor', $id)
                ->update(['estado' => 0]);

            $catalogoVigente = static::query()
                ->where('idproveedor', $id)
                ->where(function ($query) use ($hoy): void {
                    $query
                        ->whereNull('fecha_vigencia')
                        ->orWhereDate('fecha_vigencia', '<=', $hoy);
                })
                ->orderByRaw('fecha_vigencia IS NULL ASC')
                ->orderByDesc('fecha_vigencia')
                ->orderByDesc('idcatalogoproveedor')
                ->first();

            if ($catalogoVigente !== null) {
                static::query()
                    ->where(
                        'idcatalogoproveedor',
                        $catalogoVigente->idcatalogoproveedor
                    )
                    ->update(['estado' => 1]);
            }
        }
    }

    /**
     * Estado que se muestra en pantalla para una lista.
     * "programada" tiene prioridad sobre el valor persistido de estado.
     */
    public function estadoTemporal(): string
    {
        if (
            $this->fecha_vigencia !== null
            && $this->fecha_vigencia->isAfter(now()->startOfDay())
        ) {
            return 'programada';
        }

        return $this->estado ? 'vigente' : 'historica';
    }

    public function esVigenteHoy(): bool
    {
        return $this->estadoTemporal() === 'vigente';
    }

    public function esProgramada(): bool
    {
        return $this->estadoTemporal() === 'programada';
    }

    /*
     * Proveedor al que pertenece este catálogo.
     */
    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(
            Proveedor::class,
            'idproveedor',
            'idproveedor'
        );
    }

    /*
     * Usuario del sistema que realizó la importación.
     */
    public function usuarioImporta(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'idusuario_importa',
            'id'
        );
    }

    /*
     * Productos o filas que contiene este catálogo.
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetalleCatalogo::class,
            'idcatalogoproveedor',
            'idcatalogoproveedor'
        );
    }
}