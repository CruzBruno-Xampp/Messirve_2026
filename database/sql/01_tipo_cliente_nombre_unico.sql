-- Ejecutar una sola vez después de verificar que no existan nombres repetidos.
SELECT UPPER(TRIM(nombre)) AS nombre_normalizado, COUNT(*) AS cantidad
FROM tipo_cliente
GROUP BY UPPER(TRIM(nombre))
HAVING COUNT(*) > 1;

-- Si la consulta anterior devuelve 0 filas, ejecutar:
ALTER TABLE tipo_cliente
ADD CONSTRAINT uq_tipo_cliente_nombre UNIQUE (nombre);
