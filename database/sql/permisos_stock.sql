START TRANSACTION;

INSERT IGNORE INTO permisos (codigo, nombre, descripcion)
VALUES
    (
        'stock.ver',
        'Ver stock',
        'Consultar las existencias registradas por sucursal'
    ),
    (
        'stock.gestionar',
        'Gestionar stock',
        'Registrar ingresos y transferir mercadería entre sucursales'
    );

COMMIT;

SELECT idpermiso, codigo, nombre, descripcion
FROM permisos
WHERE codigo LIKE 'stock.%'
ORDER BY idpermiso;
