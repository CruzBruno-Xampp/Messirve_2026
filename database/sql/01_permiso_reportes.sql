START TRANSACTION;

INSERT INTO permisos (codigo, nombre, descripcion)
SELECT
    'reportes.ver',
    'Ver reportes',
    'Consultar reportes comerciales y de stock'
WHERE NOT EXISTS (
    SELECT 1
    FROM permisos
    WHERE codigo = 'reportes.ver'
);

INSERT IGNORE INTO rol_permiso (idrol, idpermiso)
SELECT
    r.idrol,
    p.idpermiso
FROM roles AS r
INNER JOIN permisos AS p
    ON p.codigo = 'reportes.ver'
WHERE r.nombre IN ('DUEÑO', 'GERENTE');

COMMIT;

SELECT
    r.nombre AS rol,
    p.codigo AS permiso
FROM rol_permiso AS rp
INNER JOIN roles AS r
    ON r.idrol = rp.idrol
INNER JOIN permisos AS p
    ON p.idpermiso = rp.idpermiso
WHERE p.codigo = 'reportes.ver'
ORDER BY r.idrol;
