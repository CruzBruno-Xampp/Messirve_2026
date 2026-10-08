-- Ejecutar una sola vez sobre la base de datos messirve.
-- INSERT IGNORE evita duplicar los permisos si se vuelve a ejecutar el archivo.

START TRANSACTION;

INSERT IGNORE INTO permisos (codigo, nombre, descripcion)
VALUES
    ('stock.ver', 'Ver stock', 'Consultar existencias y alertas por sucursal'),
    ('stock.gestionar', 'Gestionar stock', 'Registrar ingresos y definir los límites de stock');

-- El rol principal conserva todos los permisos.
INSERT IGNORE INTO rol_permiso (idrol, idpermiso)
SELECT r.idrol, p.idpermiso
FROM roles AS r
CROSS JOIN permisos AS p
WHERE UPPER(r.nombre) = 'DUEÑO'
  AND p.codigo IN ('stock.ver', 'stock.gestionar');

-- El gerente puede consultar y registrar ingresos.
INSERT IGNORE INTO rol_permiso (idrol, idpermiso)
SELECT r.idrol, p.idpermiso
FROM roles AS r
CROSS JOIN permisos AS p
WHERE UPPER(r.nombre) = 'GERENTE'
  AND p.codigo IN ('stock.ver', 'stock.gestionar');

-- El empleado solamente puede consultar las existencias.
INSERT IGNORE INTO rol_permiso (idrol, idpermiso)
SELECT r.idrol, p.idpermiso
FROM roles AS r
CROSS JOIN permisos AS p
WHERE UPPER(r.nombre) = 'EMPLEADO'
  AND p.codigo = 'stock.ver';

COMMIT;

-- Comprobación final.
SELECT
    r.nombre AS rol,
    p.codigo AS permiso
FROM rol_permiso AS rp
INNER JOIN roles AS r ON r.idrol = rp.idrol
INNER JOIN permisos AS p ON p.idpermiso = rp.idpermiso
WHERE p.codigo IN ('stock.ver', 'stock.gestionar')
ORDER BY r.nombre, p.codigo;
