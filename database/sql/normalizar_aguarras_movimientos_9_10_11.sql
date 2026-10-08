-- MESSIRVE - Normalizacion de las pruebas de transferencia de Aguarras
-- Fecha: 18/09/2026
--
-- Estado comprobado antes de ejecutar:
--   idstock 7  - producto 6 - Almafuerte (2)            = 4 unidades
--   idstock 8  - producto 6 - Aristobulo del Valle (3) = 6 unidades
--   movimientos 9, 10 y 11 = INGRESO_STOCK de 2 unidades al destino
--
-- Resultado buscado:
--   Almafuerte = 2 unidades
--   Aristobulo del Valle = 2 unidades
--   movimiento 11 = transferencia valida
--   movimientos 9 y 10 = pruebas anuladas, sin borrar el historial
--
-- La actualizacion se realiza en una unica sentencia atomica. Si alguno de
-- los datos comprobados ya cambio, no se modifica ninguna fila.

START TRANSACTION;

UPDATE stock AS origen
INNER JOIN stock AS destino
    ON destino.idstock = 8
   AND destino.idproducto = 6
   AND destino.idsucursal = 3
INNER JOIN movimientos AS mov9
    ON mov9.idmovimiento = 9
INNER JOIN detalle_movimiento AS det9
    ON det9.idmovimiento = mov9.idmovimiento
INNER JOIN movimientos AS mov10
    ON mov10.idmovimiento = 10
INNER JOIN detalle_movimiento AS det10
    ON det10.idmovimiento = mov10.idmovimiento
INNER JOIN movimientos AS mov11
    ON mov11.idmovimiento = 11
INNER JOIN detalle_movimiento AS det11
    ON det11.idmovimiento = mov11.idmovimiento
INNER JOIN tipos_movimiento AS tipo_ingreso
    ON tipo_ingreso.codigo = 'INGRESO_STOCK'
INNER JOIN tipos_movimiento AS tipo_transferencia
    ON tipo_transferencia.codigo = 'TRANSFERENCIA_STOCK'
   AND tipo_transferencia.estado = 1
SET
    origen.stockactual = 2,
    origen.updated_at = CURRENT_TIMESTAMP,
    destino.stockactual = 2,
    destino.updated_at = CURRENT_TIMESTAMP,
    mov9.estado = 0,
    mov9.observacion =
        'ANULADO: ingreso generado durante una prueba de transferencia.',
    mov10.estado = 0,
    mov10.observacion =
        'ANULADO: ingreso generado durante una prueba de transferencia.',
    mov11.idtipomovimiento = tipo_transferencia.idtipomovimiento,
    mov11.idsucursalorigen = 2,
    mov11.idsucursaldestino = 3,
    mov11.referencia = 'TRF-000011',
    mov11.observacion =
        'Transferencia corregida. Origen: 4 a 2. Destino: 0 a 2.'
WHERE origen.idstock = 7
  AND origen.idproducto = 6
  AND origen.idsucursal = 2
  AND origen.stockactual = 4
  AND destino.stockactual = 6
  AND mov9.idtipomovimiento = tipo_ingreso.idtipomovimiento
  AND mov10.idtipomovimiento = tipo_ingreso.idtipomovimiento
  AND mov11.idtipomovimiento = tipo_ingreso.idtipomovimiento
  AND mov9.estado = 1
  AND mov10.estado = 1
  AND mov11.estado = 1
  AND mov9.idsucursalorigen IS NULL
  AND mov10.idsucursalorigen IS NULL
  AND mov11.idsucursalorigen IS NULL
  AND mov9.idsucursaldestino = 3
  AND mov10.idsucursaldestino = 3
  AND mov11.idsucursaldestino = 3
  AND det9.idproducto = 6
  AND det10.idproducto = 6
  AND det11.idproducto = 6
  AND det9.cantidad = 2
  AND det10.cantidad = 2
  AND det11.cantidad = 2;

SELECT ROW_COUNT() AS filas_corregidas;

COMMIT;

-- Comprobacion del stock final.
SELECT
    idstock,
    idproducto,
    idsucursal,
    stockactual,
    updated_at
FROM stock
WHERE idproducto = 6
  AND idsucursal IN (2, 3)
ORDER BY idsucursal;

-- Comprobacion del historial.
SELECT
    m.idmovimiento,
    tm.codigo AS tipo,
    m.idsucursalorigen,
    m.idsucursaldestino,
    m.referencia,
    m.observacion,
    m.estado,
    dm.idproducto,
    dm.cantidad
FROM movimientos AS m
INNER JOIN tipos_movimiento AS tm
    ON tm.idtipomovimiento = m.idtipomovimiento
INNER JOIN detalle_movimiento AS dm
    ON dm.idmovimiento = m.idmovimiento
WHERE m.idmovimiento IN (9, 10, 11)
ORDER BY m.idmovimiento;
