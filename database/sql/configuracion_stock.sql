START TRANSACTION;

INSERT IGNORE INTO tipos_movimiento (codigo, nombre, signo, estado)
VALUES
    (
        'INGRESO_STOCK',
        'Ingreso de mercadería',
        1,
        1
    ),
    (
        'TRANSFERENCIA_STOCK',
        'Transferencia entre sucursales',
        0,
        1
    );

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

SELECT idtipomovimiento, codigo, nombre, signo, estado
FROM tipos_movimiento
WHERE codigo IN ('INGRESO_STOCK', 'TRANSFERENCIA_STOCK')
ORDER BY idtipomovimiento;
