-- Messirve - soporte para precio neto informado por proveedor
-- Si esta columna ya existe en tu base actual, NO hace falta ejecutar de nuevo.

ALTER TABLE detalle_catalogo
ADD COLUMN IF NOT EXISTS precioneto DECIMAL(18,6) NULL
AFTER descuentoproducto;
