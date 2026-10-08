<?php

namespace App\Models;

// Permite utilizar fábricas para generar datos de prueba.
use Illuminate\Database\Eloquent\Factories\HasFactory;

// Clase base de Eloquent para representar una tabla.
use Illuminate\Database\Eloquent\Model;

// Tipo de relación: un proveedor puede tener muchos catálogos.
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    use HasFactory;

    /*
     * Nombre exacto de la tabla relacionada con el modelo.
     *
     * En este caso Laravel encontraría correctamente "proveedores",
     * pero lo declaramos para que quede explícito.
     */
    protected $table = 'proveedores';

    /*
     * Clave primaria de la tabla proveedores.
     *
     * Laravel buscaría una columna llamada "id" si no
     * especificáramos "idproveedor".
     */
    protected $primaryKey = 'idproveedor';

    /*
     * Campos que pueden guardarse mediante asignación masiva,
     * utilizando métodos como Proveedor::create() o update().
     */
    protected $fillable = [
        'razonsocial',
        'cuit',
        'contacto',
        'telefono',
        'email',
        'direccion',
        'estado',
    ];

    /*
     * Convierte automáticamente el estado recuperado de la base
     * de datos en un número entero.
     *
     * En este sistema:
     * 1 significa proveedor activo.
     * 0 significa proveedor inactivo.
     */
    protected $casts = [
        'estado' => 'integer',
    ];

    /*
     * Relación uno a muchos:
     *
     * Un proveedor puede enviar muchos catálogos a lo largo del tiempo.
     * Por ejemplo, Tersuave puede enviar una lista en marzo y otra en abril.
     *
     * Primer parámetro: modelo relacionado.
     * Segundo parámetro: clave foránea en catalogo_proveedor.
     * Tercer parámetro: clave primaria en proveedores.
     */
    public function catalogos(): HasMany
    {
        return $this->hasMany(
            CatalogoProveedor::class,
            'idproveedor',
            'idproveedor'
        );
    }
}