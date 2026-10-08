-- Ejecutar una sola vez sobre la base de datos messirve.
-- Los permisos luego pueden modificarse desde el módulo Roles.

START TRANSACTION;

INSERT IGNORE INTO permisos (codigo, nombre, descripcion)
VALUES
    ('clientes.ver', 'Ver clientes', 'Consultar clientes registrados y sus datos de contacto'),
    ('clientes.crear', 'Crear clientes', 'Registrar clientes desde el panel administrativo'),
    ('clientes.editar', 'Editar clientes', 'Modificar datos y activar o inactivar clientes');

-- DUEÑO, GERENTE y EMPLEADO reciben inicialmente los tres permisos.
INSERT IGNORE INTO rol_permiso (idrol, idpermiso)
SELECT r.idrol, p.idpermiso
FROM roles AS r
CROSS JOIN permisos AS p
WHERE UPPER(r.nombre) IN ('DUEÑO', 'GERENTE', 'EMPLEADO')
  AND p.codigo IN ('clientes.ver', 'clientes.crear', 'clientes.editar');

COMMIT;

-- Comprobación final.
SELECT
    r.nombre AS rol,
    p.codigo AS permiso
FROM rol_permiso AS rp
INNER JOIN roles AS r ON r.idrol = rp.idrol
INNER JOIN permisos AS p ON p.idpermiso = rp.idpermiso
WHERE p.codigo LIKE 'clientes.%'
ORDER BY r.idrol, p.codigo;
