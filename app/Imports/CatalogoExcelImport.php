<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CatalogoExcelImport implements WithMultipleSheets
{
    /**
     * @param array $catalogosPorHoja
     *
     * Array que relaciona cada hoja seleccionada con el registro
     * creado en la tabla catalogo_proveedor.
     *
     * Ejemplo:
     *
     * [
     *     'LP 234' => 10,
     *     'Diluyentes D46' => 11,
     * ]
     *
     * En ese ejemplo:
     * - La hoja "LP 234" pertenece al catálogo con ID 10.
     * - La hoja "Diluyentes D46" pertenece al catálogo con ID 11.
     *
     * @param int|null $idIva
     * ID de la alícuota de IVA que se asignará a los productos.
     */
    public function __construct(
        private readonly array $catalogosPorHoja,
        private readonly ?int $idIva
    ) {
    }

    /**
     * Indica qué hojas del archivo deben importarse.
     *
     * Laravel Excel solamente procesará las hojas incluidas
     * en el array $catalogosPorHoja.
     */
    public function sheets(): array
    {
        $hojasAImportar = [];

        foreach ($this->catalogosPorHoja as $nombreHoja => $idCatalogoProveedor) {
            /**
             * La hoja LP191 Polvo tiene una distribución diferente:
             *
             * - El código comienza en la columna A.
             * - La descripción está en la columna B.
             * - No contiene Línea, Grupo ni Subgrupo.
             */
            $formatoPolvo = mb_strtolower(trim($nombreHoja), 'UTF-8')
                === 'lp191 polvo';

            /**
             * La clave debe ser el nombre exacto de la hoja del Excel.
             *
             * Cada hoja utilizará una instancia independiente de
             * DetalleCatalogoImport.
             */
            $hojasAImportar[$nombreHoja] = new DetalleCatalogoImport(
                (int) $idCatalogoProveedor,
                $this->idIva,
                $formatoPolvo
            );
        }

        return $hojasAImportar;
    }
}