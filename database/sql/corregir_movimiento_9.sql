-- Correccion puntual del movimiento 9.
--
-- Situacion comprobada:
--   producto 6 (Aguarras)
--   origen: Almafuerte, idsucursal 2, idstock 7, stock actual incorrecto 4
--   destino: Aristobulo del Valle, idsucursal 3, idstock 8, stock actual 2
--   movimiento 9 registrado por error como INGRESO_STOCK por 2 unidades
--
-- Esta sentencia es atomica y esta protegida por todos esos valores.
-- Si algun dato ya cambio, no modifica ninguna fila.

START TRANSACTION;

UPDATE stock AS origen
INNER JOIN movimientos AS movimiento
    ON movimiento.idmovimiento = 9
INNER JOIN detalle_movimiento AS detalle
    ON detalle.idmovimiento = movimiento.idmovimiento
INNER JOIN tipos_movimiento AS tipo_actual
    ON tipo_actual.idtipomovimiento = movimiento.idtipomovimiento
   AND tipo_actual.codigo = 'INGRESO_STOCK'
INNER JOIN tipos_movimiento AS tipo_transferencia
    ON tipo_transferencia.codigo = 'TRANSFERENCIA_STOCK'
   AND tipo_transferencia.estado = 1
SET
    origen.stockactual = origen.stockactual - detalle.cantidad,
    origen.updated_at = CURRENT_TIMESTAMP,
    movimiento.idtipomovimiento = tipo_transferencia.idtipomovimiento,
    movimiento.idsucursalorigen = 2,
    movimiento.idsucursaldestino = 3,
    movimiento.referencia = 'TRF-000009',
    movimiento.observacion =
        'Transferencia corregida. Origen: 4 a 2. Destino: 0 a 2.'
WHERE origen.idstock = 7
  AND origen.idproducto = 6
  AND origen.idsucursal = 2
  AND origen.stockactual = 4
  AND movimiento.idsucursalorigen IS NULL
  AND movimiento.idsucursaldestino = 3
  AND detalle.idproducto = 6
  AND detalle.cantidad = 2;

SELECT ROW_COUNT() AS filas_corregidas;

COMMIT;

-- Comprobacion final: deben aparecer 2 unidades en cada sucursal y
-- el movimiento 9 debe figurar como TRANSFERENCIA_STOCK, origen 2, destino 3.
SELECT
    s.idstock,
    s.idproducto,
    s.idsucursal,
    s.stockactual
FROM stock AS s
WHERE s.idproducto = 6
  AND s.idsucursal IN (2, 3)
ORDER BY s.idsucursal;

SELECT
    m.idmovimiento,
    tm.codigo AS tipo,
    m.idsucursalorigen,
    m.idsucursaldestino,
    m.referencia,
    dm.idproducto,
    dm.cantidad
FROM movimientos AS m
INNER JOIN tipos_movimiento AS tm
    ON tm.idtipomovimiento = m.idtipomovimiento
INNER JOIN detalle_movimiento AS dm
    ON dm.idmovimiento = m.idmovimiento
WHERE m.idmovimiento = 9;
