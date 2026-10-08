-- Ejecutar una sola vez.
-- Requiere que no existan documentos duplicados (la consulta previa dio 0 filas).

ALTER TABLE clientes
ADD CONSTRAINT uq_clientes_documento UNIQUE (documento);

-- Comprobación opcional:
SHOW INDEX FROM clientes WHERE Key_name = 'uq_clientes_documento';
