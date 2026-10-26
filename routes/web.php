<?php

use App\Http\Controllers\Auth\ClienteLoginController;
use App\Http\Controllers\Auth\ClientePasswordResetController;
use App\Http\Controllers\Auth\ClienteRegisterController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\AdminPasswordResetController;
use App\Http\Controllers\CatalogoImportController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\VerificarPermiso;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Página principal.
Route::get('/', function () {
    return view('welcome');
});

// Pantalla de inicio de sesión del cliente.
Route::get(
    '/login',
    [ClienteLoginController::class, 'create']
)->name('login');

// Procesar inicio de sesión.
Route::post(
    '/login',
    [ClienteLoginController::class, 'store']
)->name('cliente.login.store');

// Mostrar pantalla de registro.
Route::get(
    '/register',
    [ClienteRegisterController::class, 'create']
)->name('cliente.register');

// Procesar y guardar el registro.
Route::post(
    '/register',
    [ClienteRegisterController::class, 'store']
)->name('cliente.register.store');

// Mostrar pantalla para recuperar contraseña.
Route::get(
    '/recuperarcontra',
    [ClientePasswordResetController::class, 'create']
)->name('password.request');

// Enviar correo para recuperar contraseña.
Route::post(
    '/recuperarcontra',
    [ClientePasswordResetController::class, 'store']
)->name('password.email');

// Mostrar formulario para establecer una nueva contraseña.
Route::get(
    '/restablecercontra/{token}',
    [ClientePasswordResetController::class, 'edit']
)->name('password.reset');

// Guardar la nueva contraseña.
Route::post(
    '/restablecercontra',
    [ClientePasswordResetController::class, 'update']
)->name('password.update');

// Pantalla existente para cargar productos.
Route::get('/cargarproducto', function () {
    return view('auth.cargarproducto');
})
    ->middleware('auth:cliente')
    ->name('cargarproducto');

/*
|--------------------------------------------------------------------------
| Acceso administrativo
|--------------------------------------------------------------------------
|
| El guard "web" autentica exclusivamente a los usuarios internos
| almacenados en la tabla users. No comparte sesión de autenticación
| con los clientes de la tienda.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Formulario de acceso para dueño, gerente y empleados.
        Route::get(
            '/login',
            [AdminLoginController::class, 'create']
        )->name('login');

        // Comprueba las credenciales del usuario interno.
        Route::post(
            '/login',
            [AdminLoginController::class, 'store']
        )
            ->middleware('throttle:6,1')
            ->name('login.store');

        // Solicitud de recuperación de contraseña del personal.
        Route::get(
            '/recuperar-contrasena',
            [AdminPasswordResetController::class, 'create']
        )->name('password.request');

        Route::post(
            '/recuperar-contrasena',
            [AdminPasswordResetController::class, 'store']
        )
            ->middleware('throttle:3,1')
            ->name('password.email');

        // Formulario que recibe el token enviado por correo.
        Route::get(
            '/restablecer-contrasena/{token}',
            [AdminPasswordResetController::class, 'edit']
        )->name('password.reset');

        Route::post(
            '/restablecer-contrasena',
            [AdminPasswordResetController::class, 'update']
        )
            ->middleware('throttle:10,1')
            ->name('password.update');

        // Entrada principal del sector administrativo.
        Route::get('/', function () {
            return redirect()->route('dashboard.index');
        })
            ->middleware(AuthenticateAdmin::class)
            ->name('inicio');

        // Cierra únicamente el acceso administrativo.
        Route::post(
            '/logout',
            [AdminLoginController::class, 'destroy']
        )
            ->middleware(AuthenticateAdmin::class)
            ->name('logout');
    });

/*
|--------------------------------------------------------------------------
| Dashboard administrativo
|--------------------------------------------------------------------------
|
| Disponible para todo usuario interno autenticado. El controlador limita
| automáticamente los datos a la sucursal asignada, salvo el dueño global.
|
*/

Route::get(
    '/dashboard',
    [DashboardController::class, 'index']
)
    ->middleware(AuthenticateAdmin::class)
    ->name('dashboard.index');

/*
|--------------------------------------------------------------------------
| Control de stock
|--------------------------------------------------------------------------
|
| La consulta y el ingreso de mercadería usan permisos separados. De esta
| manera un empleado puede consultar existencias sin poder modificarlas.
|
*/

Route::middleware([
    AuthenticateAdmin::class,
])
    ->prefix('stock')
    ->name('stock.')
    ->group(function () {
        Route::get(
            '/',
            [StockController::class, 'index']
        )
            ->middleware(VerificarPermiso::class . ':stock.ver')
            ->name('index');

        Route::put(
            '/{stock}/ingresos',
            [StockController::class, 'registrarIngreso']
        )
            ->whereNumber('stock')
            ->middleware(VerificarPermiso::class . ':stock.gestionar')
            ->name('ingresos.store');

        Route::get(
            '/productos/{producto}/sucursales/{sucursal}',
            [StockController::class, 'consultarUbicacion']
        )
            ->whereNumber('producto')
            ->whereNumber('sucursal')
            ->middleware([
                VerificarPermiso::class . ':stock.gestionar',
                'throttle:120,1',
            ])
            ->name('ubicaciones.show');

        Route::post(
            '/{stock}/transferencias',
            [StockController::class, 'transferir']
        )
            ->whereNumber('stock')
            ->middleware(VerificarPermiso::class . ':stock.gestionar')
            ->name('transferencias.store');

        Route::put(
            '/compras/detalles/{detalleCompra}/recepcion',
            [StockController::class, 'recibirCompra']
        )
            ->whereNumber('detalleCompra')
            ->middleware([VerificarPermiso::class . ':compras.recibir', 'throttle:60,1'])
            ->name('compras.recepcion');

        Route::patch(
            '/compras/detalles/{detalleCompra}/reemplazo',
            [StockController::class, 'registrarReemplazo']
        )
            ->whereNumber('detalleCompra')
            ->middleware([VerificarPermiso::class . ':compras.recibir', 'throttle:60,1'])
            ->name('compras.reemplazo');
    });

/*
|--------------------------------------------------------------------------
| Ventas
|--------------------------------------------------------------------------
|
| La pantalla permite elegir la sucursal autorizada, identificar al cliente
| y preparar los productos. Todas las búsquedas respetan ventas.crear.
|
*/

Route::get(
    '/ventas',
    [VentaController::class, 'index']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.ver',
    ])
    ->name('ventas.index');

Route::put(
    '/ventas/metodos-pago',
    [VentaController::class, 'actualizarMetodosPago']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.ver',
        'throttle:20,1',
    ])
    ->name('ventas.metodos-pago.actualizar');

Route::get(
    '/ventas/{venta}',
    [VentaController::class, 'mostrar']
)
    ->whereNumber('venta')
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.ver',
    ])
    ->name('ventas.detalle');

Route::patch(
    '/ventas/{venta}/anular',
    [VentaController::class, 'anular']
)
    ->whereNumber('venta')
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.anular',
        'throttle:10,1',
    ])
    ->name('ventas.anular');

Route::get(
    '/ventas/nueva',
    [VentaController::class, 'crear']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.crear',
    ])
    ->name('ventas.crear');

Route::post(
    '/ventas',
    [VentaController::class, 'guardar']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.crear',
        'throttle:30,1',
    ])
    ->name('ventas.guardar');

Route::get(
    '/ventas/productos/codigo',
    [VentaController::class, 'buscarProductoPorCodigo']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.crear',
        'throttle:180,1',
    ])
    ->name('ventas.productos.codigo');

Route::get(
    '/ventas/productos/buscar',
    [VentaController::class, 'buscarProductos']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.crear',
        'throttle:120,1',
    ])
    ->name('ventas.productos.buscar');

Route::get(
    '/ventas/clientes/buscar',
    [VentaController::class, 'buscarClientes']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':ventas.crear',
        'throttle:120,1',
    ])
    ->name('ventas.clientes.buscar');

/*
|--------------------------------------------------------------------------
| Administración de clientes
|--------------------------------------------------------------------------
|
| Permite consultar, registrar y actualizar clientes del local. El alta
| administrativa no crea una contraseña para el acceso web.
|
*/

Route::middleware(AuthenticateAdmin::class)
    ->prefix('clientes')
    ->name('clientes.')
    ->group(function () {
        Route::get(
            '/',
            [ClienteController::class, 'index']
        )
            ->middleware(VerificarPermiso::class . ':clientes.ver')
            ->name('index');

        Route::post(
            '/',
            [ClienteController::class, 'store']
        )
            ->middleware([
                VerificarPermiso::class . ':clientes.crear',
                'throttle:30,1',
            ])
            ->name('store');

        Route::put(
            '/{cliente}',
            [ClienteController::class, 'update']
        )
            ->whereNumber('cliente')
            ->middleware([
                VerificarPermiso::class . ':clientes.editar',
                'throttle:60,1',
            ])
            ->name('update');


        Route::post(
            '/tipos',
            [ClienteController::class, 'storeTipoCliente']
        )
            ->middleware([
                VerificarPermiso::class . ':clientes.crear',
                'throttle:30,1',
            ])
            ->name('tipos.store');

        Route::put(
            '/tipos/{tipoCliente}',
            [ClienteController::class, 'updateTipoCliente']
        )
            ->whereNumber('tipoCliente')
            ->middleware([
                VerificarPermiso::class . ':clientes.editar',
                'throttle:60,1',
            ])
            ->name('tipos.update');
    });

/*
|--------------------------------------------------------------------------
| Administración de proveedores
|--------------------------------------------------------------------------
|
| Permite consultar, registrar y actualizar proveedores. La carga y consulta
| de catálogos se conectarán a este módulo en el siguiente paso.
|
*/

Route::middleware(AuthenticateAdmin::class)
    ->prefix('proveedores')
    ->name('proveedores.')
    ->group(function () {
        Route::get(
            '/',
            [ProveedorController::class, 'index']
        )
            ->middleware(VerificarPermiso::class . ':proveedores.ver')
            ->name('index');

        Route::post(
            '/',
            [ProveedorController::class, 'store']
        )
            ->middleware([
                VerificarPermiso::class . ':proveedores.crear',
                'throttle:30,1',
            ])
            ->name('store');

        Route::put(
            '/{proveedor}',
            [ProveedorController::class, 'update']
        )
            ->whereNumber('proveedor')
            ->middleware([
                VerificarPermiso::class . ':proveedores.editar',
                'throttle:60,1',
            ])
            ->name('update');
    });


/*
|--------------------------------------------------------------------------
| Compras a proveedores
|--------------------------------------------------------------------------
|
| En esta primera etapa se habilita la consulta del historial de compras.
| La creación, recepción y anulación se incorporan en los próximos pasos.
|
*/

Route::middleware(AuthenticateAdmin::class)
    ->prefix('compras')
    ->name('compras.')
    ->group(function () {
        Route::get(
            '/',
            [CompraController::class, 'index']
        )
            ->middleware(VerificarPermiso::class . ':compras.ver')
            ->name('index');

        Route::get(
            '/nueva',
            [CompraController::class, 'crear']
        )
            ->middleware(VerificarPermiso::class . ':compras.crear')
            ->name('crear');

        Route::post(
            '/',
            [CompraController::class, 'guardar']
        )
            ->middleware([
                VerificarPermiso::class . ':compras.crear',
                'throttle:20,1',
            ])
            ->name('guardar');

        Route::get(
            '/articulos/buscar',
            [CompraController::class, 'buscarArticulos']
        )
            ->middleware([
                VerificarPermiso::class . ':compras.crear',
                'throttle:120,1',
            ])
            ->name('articulos.buscar');

        Route::get(
            '/articulos/{detalle}/preparar-producto',
            [CompraController::class, 'prepararProducto']
        )
            ->whereNumber('detalle')
            ->middleware([
                VerificarPermiso::class . ':compras.crear',
                'throttle:120,1',
            ])
            ->name('articulos.preparar-producto');
    });

/*
|--------------------------------------------------------------------------
| Reportes
|--------------------------------------------------------------------------
|
| El panel respeta la sucursal asignada al usuario. El dueño global puede
| consultar todas las ubicaciones o elegir una sucursal determinada.
|
*/

Route::get(
    '/reportes',
    [ReporteController::class, 'index']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':reportes.ver',
    ])
    ->name('reportes.index');

Route::get(
    '/reportes/stock',
    [ReporteController::class, 'stock']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':reportes.ver',
    ])
    ->name('reportes.stock');

Route::get(
    '/reportes/ventas/exportar',
    [ReporteController::class, 'exportarVentas']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':reportes.ver',
        'throttle:20,1',
    ])
    ->name('reportes.ventas.exportar');

Route::get(
    '/reportes/ventas/imprimir',
    [ReporteController::class, 'imprimirVentas']
)
    ->middleware([
        AuthenticateAdmin::class,
        VerificarPermiso::class . ':reportes.ver',
        'throttle:20,1',
    ])
    ->name('reportes.ventas.imprimir');

/*
|--------------------------------------------------------------------------
| Importación de catálogos de proveedores
|--------------------------------------------------------------------------
|
| Estas rutas utilizan el middleware "auth" porque corresponden
| al sistema administrativo de la pinturería.
|
*/

Route::middleware([
    AuthenticateAdmin::class,
    VerificarPermiso::class . ':catalogos.importar',
])
    ->prefix('catalogos/importar')
    ->group(function () {
        // Muestra la pantalla para subir el Excel.
        Route::get(
            '/',
            [CatalogoImportController::class, 'create']
        )->name('catalogos.importar');

        // Recibe el Excel y analiza todas sus hojas.
        Route::post(
            '/analizar',
            [CatalogoImportController::class, 'analizar']
        )->name('catalogos.importar.analizar');

        // Registra un proveedor sin abandonar la importación actual.
        Route::post(
            '/proveedores',
            [ProveedorController::class, 'guardarRapido']
        )
            ->middleware(
                VerificarPermiso::class . ':proveedores.crear'
            )
            ->name('catalogos.importar.proveedores.guardar');

        // Muestra la pantalla para confirmar las columnas.
        Route::get(
            '/mapear',
            [CatalogoImportController::class, 'mapear']
        )->name('catalogos.importar.mapear');

        // Genera los CSV e importa los productos.
        Route::post(
            '/procesar',
            [CatalogoImportController::class, 'importar']
        )->name('catalogos.importar.procesar');

        // Muestra el resultado de la importación.
        Route::get(
            '/resultado',
            [CatalogoImportController::class, 'resultado']
        )->name('catalogos.importar.resultado');

        // Cancela el proceso y elimina el archivo temporal.
        Route::post(
            '/cancelar',
            [CatalogoImportController::class, 'cancelar']
        )->name('catalogos.importar.cancelar');
    });

/*
|--------------------------------------------------------------------------
| Administración de productos
|--------------------------------------------------------------------------
|
| Permite revisar los artículos importados, crear productos internos
| o vincularlos con productos que ya existen en Messirve.
|
*/

Route::middleware(AuthenticateAdmin::class)
    ->prefix('productos')
    ->name('productos.')
    ->group(function () {
        // Productos que Messirve ya comercializa.
        Route::get(
            '/',
            [ProductoController::class, 'index']
        )
            ->middleware(VerificarPermiso::class . ':productos.ver')
            ->name('index');

        // Alta manual: no necesita un artículo de catálogo.
        Route::get('/manual/crear', [ProductoController::class, 'crearManual'])
            ->middleware(VerificarPermiso::class . ':productos.crear')
            ->name('manual.crear');

        Route::post('/manual', [ProductoController::class, 'guardarManual'])
            ->middleware(VerificarPermiso::class . ':productos.crear')
            ->name('manual.guardar');

        // Artículos importados que todavía no tienen producto interno.
        Route::get(
            '/catalogo',
            [ProductoController::class, 'catalogo']
        )
            ->middleware(VerificarPermiso::class . ':catalogos.ver')
            ->name('catalogo');

        // Compara precios vigentes partiendo de un artículo del catálogo.
        Route::get(
            '/catalogo/{detalle}/comparar-proveedores',
            [ProductoController::class, 'compararProveedores']
        )
            ->whereNumber('detalle')
            ->middleware(VerificarPermiso::class . ':catalogos.ver')
            ->name('catalogo.comparar-proveedores');

        // Formulario para crear o vincular un artículo del catálogo.
        Route::get(
            '/catalogo/{detalle}/crear',
            [ProductoController::class, 'crearDesdeCatalogo']
        )
            ->whereNumber('detalle')
            ->middleware(VerificarPermiso::class . ':productos.crear')
            ->name('catalogo.crear');

        // Guarda el producto nuevo o la vinculación seleccionada.
        Route::post(
            '/catalogo/{detalle}',
            [ProductoController::class, 'guardarDesdeCatalogo']
        )
            ->whereNumber('detalle')
            ->middleware(VerificarPermiso::class . ':productos.crear')
            ->name('catalogo.guardar');

        // Muestra el formulario para corregir un producto interno.
        Route::get(
            '/{producto}/editar',
            [ProductoController::class, 'editar']
        )
            ->whereNumber('producto')
            ->middleware(VerificarPermiso::class . ':productos.editar')
            ->name('editar');

        // Guarda los cambios sin modificar el stock ni el catálogo original.
        Route::put(
            '/{producto}',
            [ProductoController::class, 'actualizar']
        )
            ->whereNumber('producto')
            ->middleware(VerificarPermiso::class . ':productos.editar')
            ->name('actualizar');
    });

/*
|--------------------------------------------------------------------------
| Administración de roles y permisos
|--------------------------------------------------------------------------
|
| Solamente los usuarios con el permiso roles.gestionar pueden consultar,
| crear y modificar los perfiles de acceso del sistema.
|
*/

Route::middleware([
    AuthenticateAdmin::class,
    VerificarPermiso::class . ':roles.gestionar',
])
    ->prefix('roles')
    ->name('roles.')
    ->group(function () {
        Route::get(
            '/',
            [RolController::class, 'index']
        )->name('index');

        Route::post(
            '/',
            [RolController::class, 'store']
        )->name('store');

        Route::put(
            '/{rol}',
            [RolController::class, 'update']
        )
            ->whereNumber('rol')
            ->name('update');
    });

/*
|--------------------------------------------------------------------------
| Administración del personal
|--------------------------------------------------------------------------
|
| Permite consultar, registrar y actualizar usuarios internos. Solamente
| pueden ingresar quienes tengan el permiso usuarios.gestionar.
|
*/

Route::middleware([
    AuthenticateAdmin::class,
    VerificarPermiso::class . ':usuarios.gestionar',
])
    ->prefix('usuarios')
    ->name('usuarios.')
    ->group(function () {
        Route::get(
            '/',
            [UsuarioController::class, 'index']
        )->name('index');

        Route::post(
            '/',
            [UsuarioController::class, 'store']
        )->name('store');

        Route::put(
            '/{usuario}',
            [UsuarioController::class, 'update']
        )
            ->whereNumber('usuario')
            ->name('update');
    });
