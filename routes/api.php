<?php

use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\CategoriaController;
use App\Http\Controllers\API\V1\ColorController;
use App\Http\Controllers\API\V1\DashboardController;
use App\Http\Controllers\API\V1\DetalleFacturaController;
use App\Http\Controllers\API\V1\DocumentationController;
use App\Http\Controllers\API\V1\FacturaController;
use App\Http\Controllers\API\V1\FotoController;
use App\Http\Controllers\API\V1\HamacaController;
use App\Http\Controllers\API\V1\InventarioHamacaController;
use App\Http\Controllers\API\V1\PosVentaController;
use App\Http\Controllers\API\V1\MovimientoController;
use App\Http\Controllers\API\V1\PantallaController;
use App\Http\Controllers\API\V1\PantallaPermisoRolController;
use App\Http\Controllers\API\V1\PermisoController;
use App\Http\Controllers\API\V1\TamanoController;
use App\Http\Controllers\API\V1\UbicacionController;
use App\Http\Controllers\API\V1\UsuarioController;
use App\Http\Controllers\API\V1\HamacaVarianteController;
use App\Http\Controllers\API\V1\MaterialController;
use App\Http\Controllers\API\V1\ProcesoProduccionController;
use App\Http\Controllers\API\V1\ServicioAdicionalController;
use App\Http\Controllers\API\V1\RecetaHamacaController;
use App\Http\Controllers\API\V1\ServicioFormulaController;
use Illuminate\Support\Facades\Route;

$auth = ['api.key', 'auth:sanctum'];
$admin = ['api.key', 'auth:sanctum', 'role:admin'];
$inventoryManager = ['api.key', 'auth:sanctum', 'role:almacenista,admin'];
$sales = ['api.key', 'auth:sanctum', 'role:vendedor,admin'];
$productionCatalogView = ['api.key', 'auth:sanctum', 'role:admin,almacenista,socio'];
$serviceCatalogView = ['api.key', 'auth:sanctum', 'role:admin,vendedor,almacenista'];
$recipeView = ['api.key', 'auth:sanctum', 'role:admin,almacenista,socio'];
$formulaCostView = ['api.key', 'auth:sanctum', 'role:admin,socio'];

// Autenticacion administrativa.
Route::get('/v1/login', [AuthController::class, 'loginInfo']);
Route::post('/v1/login', [AuthController::class, 'login']);
// The browser must receive a session before requesting the CSRF token.
Route::get('/v1/csrf-token', [AuthController::class, 'csrfToken'])->middleware('web');
Route::get('/v1/me', [AuthController::class, 'me'])->middleware($auth);
Route::post('/v1/logout', [AuthController::class, 'logout'])->middleware($auth);

// Catalogos base.
Route::get('/v1/categorias', [CategoriaController::class, 'index']);
Route::get('/v1/categorias/{categoria}', [CategoriaController::class, 'show']);
Route::post('/v1/categorias', [CategoriaController::class, 'store'])->middleware($admin);
Route::put('/v1/categorias/{categoria}', [CategoriaController::class, 'update'])->middleware($admin);

Route::get('/v1/tamanos', [TamanoController::class, 'index']);
Route::get('/v1/tamanos/{tamano}', [TamanoController::class, 'show']);
Route::post('/v1/tamanos', [TamanoController::class, 'store'])->middleware($admin);
Route::put('/v1/tamanos/{tamano}', [TamanoController::class, 'update'])->middleware($admin);

Route::get('/v1/ubicaciones', [UbicacionController::class, 'index']);
Route::get('/v1/ubicaciones/{ubicacion}', [UbicacionController::class, 'show']);
Route::post('/v1/ubicaciones', [UbicacionController::class, 'store'])->middleware($admin);
Route::put('/v1/ubicaciones/{ubicacion}', [UbicacionController::class, 'update'])->middleware($admin);

Route::get('/v1/colores', [ColorController::class, 'index']);
Route::get('/v1/colores/{colore}', [ColorController::class, 'show']);
Route::post('/v1/colores', [ColorController::class, 'store'])->middleware($admin);
Route::put('/v1/colores/{colore}', [ColorController::class, 'update'])->middleware($admin);

// Catalogos de produccion y servicios adicionales.
Route::get('/v1/materiales', [MaterialController::class, 'index'])->middleware($productionCatalogView);
Route::get('/v1/materiales/{material}', [MaterialController::class, 'show'])->middleware($productionCatalogView);
Route::post('/v1/materiales', [MaterialController::class, 'store'])->middleware($admin);
Route::put('/v1/materiales/{material}', [MaterialController::class, 'update'])->middleware($admin);
Route::delete('/v1/materiales/{material}', [MaterialController::class, 'destroy'])->middleware($admin);

Route::get('/v1/procesos-produccion', [ProcesoProduccionController::class, 'index'])->middleware($productionCatalogView);
Route::get('/v1/procesos-produccion/{procesoProduccion}', [ProcesoProduccionController::class, 'show'])->middleware($productionCatalogView);
Route::post('/v1/procesos-produccion', [ProcesoProduccionController::class, 'store'])->middleware($admin);
Route::put('/v1/procesos-produccion/{procesoProduccion}', [ProcesoProduccionController::class, 'update'])->middleware($admin);
Route::delete('/v1/procesos-produccion/{procesoProduccion}', [ProcesoProduccionController::class, 'destroy'])->middleware($admin);

Route::get('/v1/servicios-adicionales', [ServicioAdicionalController::class, 'index'])->middleware($serviceCatalogView);
Route::get('/v1/servicios-adicionales/{servicioAdicional}', [ServicioAdicionalController::class, 'show'])->middleware($serviceCatalogView);
Route::post('/v1/servicios-adicionales', [ServicioAdicionalController::class, 'store'])->middleware($admin);
Route::put('/v1/servicios-adicionales/{servicioAdicional}', [ServicioAdicionalController::class, 'update'])->middleware($admin);
Route::delete('/v1/servicios-adicionales/{servicioAdicional}', [ServicioAdicionalController::class, 'destroy'])->middleware($admin);
Route::get('/v1/servicios-adicionales/{servicioAdicional}/formula', [ServicioFormulaController::class, 'show'])->middleware($formulaCostView);
Route::put('/v1/servicios-adicionales/{servicioAdicional}/formula', [ServicioFormulaController::class, 'update'])->middleware($admin);
Route::get('/v1/servicios-adicionales/{servicioAdicional}/costos', [ServicioFormulaController::class, 'costs'])->middleware($formulaCostView);

// Hamacas y su detalle.
Route::get('/v1/hamacas', [HamacaController::class, 'index']);
Route::get('/v1/hamacas/detalles', [HamacaController::class, 'getHamacasWithDetails'])->middleware($auth);
Route::get('/v1/hamacas/monthly-inventory', [HamacaController::class, 'getMonthlyInventory'])->middleware($auth);
Route::get('/v1/hamacas/{hamaca}', [HamacaController::class, 'show']);
Route::post('/v1/hamacas', [HamacaController::class, 'store'])->middleware($admin);
Route::put('/v1/hamacas/{hamaca}', [HamacaController::class, 'update'])->middleware($admin);
Route::delete('/v1/hamacas/{hamaca}', [HamacaController::class, 'destroy'])->middleware($admin);
Route::get('/v1/hamacas/{hamaca}/recetas', [RecetaHamacaController::class, 'index'])->middleware($recipeView);
Route::get('/v1/hamacas/{hamaca}/recetas/activa', [RecetaHamacaController::class, 'active'])->middleware($recipeView);
Route::post('/v1/hamacas/{hamaca}/recetas', [RecetaHamacaController::class, 'store'])->middleware($admin);
Route::get('/v1/recetas-hamaca/{recetaHamaca}', [RecetaHamacaController::class, 'show'])->middleware($recipeView);
Route::put('/v1/recetas-hamaca/{recetaHamaca}', [RecetaHamacaController::class, 'update'])->middleware($admin);
Route::get('/v1/recetas-hamaca/{recetaHamaca}/costos', [RecetaHamacaController::class, 'costs'])->middleware($recipeView);
Route::post('/v1/recetas-hamaca/{recetaHamaca}/activar', [RecetaHamacaController::class, 'activate'])->middleware($admin);
Route::post('/v1/recetas-hamaca/{recetaHamaca}/descartar', [RecetaHamacaController::class, 'discard'])->middleware($admin);
// Fotos.
Route::get('/v1/fotos', [FotoController::class, 'index']);
Route::get('/v1/fotos/{foto}', [FotoController::class, 'show']);
Route::post('/v1/fotos', [FotoController::class, 'store'])->middleware($admin);
Route::put('/v1/fotos/{foto}', [FotoController::class, 'update'])->middleware($admin);
Route::delete('/v1/fotos/{foto}', [FotoController::class, 'destroy'])->middleware($admin);
Route::get('/v1/fotos/{foto}/copy-source', [FotoController::class, 'copySource']);

//Hamaca Variante.
Route::get('/v1/hamaca-variantes', [HamacaVarianteController::class, 'index'])->middleware($auth);
Route::get('/v1/hamaca-variantes/{hamacaVariante}', [HamacaVarianteController::class, 'show'])->middleware($auth);
Route::post('/v1/hamaca-variantes', [HamacaVarianteController::class, 'store'])->middleware($inventoryManager);
Route::put('/v1/hamaca-variantes/{hamacaVariante}', [HamacaVarianteController::class, 'update'])->middleware($inventoryManager);
Route::delete('/v1/hamaca-variantes/{hamacaVariante}', [HamacaVarianteController::class, 'destroy'])->middleware($admin);


// Inventario fisico.
Route::post('/v1/inventario/entradas', [InventarioHamacaController::class, 'entrada'])->middleware($inventoryManager);
Route::post('/v1/inventario/salidas', [InventarioHamacaController::class, 'salida'])->middleware($inventoryManager);
Route::post('/v1/inventario/transferencias', [InventarioHamacaController::class, 'transfer'])->middleware($inventoryManager);

//Inventario-hamacas. 
Route::get('/v1/inventario-hamacas', [InventarioHamacaController::class, 'index'])->middleware($auth);
Route::get('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'show'])->middleware($auth);
//Route::post('/v1/inventario-hamacas', [InventarioHamacaController::class, 'store'])->middleware($inventoryManager);
//Route::put('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'update'])->middleware($inventoryManager);
Route::delete('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'destroy'])->middleware($admin);

// Usuarios administrativos.
Route::get('/v1/usuarios', [UsuarioController::class, 'index'])->middleware($admin);
Route::get('/v1/usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware($admin);
Route::post('/v1/usuarios', [UsuarioController::class, 'store'])->middleware($admin);
Route::put('/v1/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware($admin);
Route::delete('/v1/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->middleware($admin);

// Pantallas, permisos y accesos por rol.
Route::apiResource('/v1/pantallas', PantallaController::class)->middleware($admin);
Route::apiResource('/v1/permisos', PermisoController::class)->middleware($admin);
Route::get('/v1/pantalla-permiso-roles/current', [PantallaPermisoRolController::class, 'current'])->middleware($auth);
Route::apiResource('/v1/pantalla-permiso-roles', PantallaPermisoRolController::class)->middleware($admin);

// Movimientos.
Route::get('/v1/movimientos/monthly-entries', [MovimientoController::class, 'getMonthlyEntries'])->middleware($auth);
Route::get('/v1/movimientos/monthly-exits', [MovimientoController::class, 'getMonthlyExits'])->middleware($auth);
Route::apiResource('/v1/movimientos', MovimientoController::class)->only(['index', 'show'])->middleware($auth);

// Dashboard.
Route::get('/v1/dashboard/summary', [DashboardController::class, 'summary'])->middleware($auth);
Route::get('/v1/dashboard/movements-by-category', [DashboardController::class, 'movementsByCategory'])->middleware($auth);
Route::get('/v1/dashboard/categories/{categoriaId}/stats', [DashboardController::class, 'categoryStats'])->middleware($auth);

// Facturacion y POS.
Route::apiResource('/v1/facturas', FacturaController::class)->only(['index', 'show'])->middleware($auth);
Route::apiResource('/v1/detalle_facturas', DetalleFacturaController::class)->only(['index', 'show'])->middleware($auth);
Route::post('/v1/pos/ventas', [PosVentaController::class, 'store'])->middleware($sales);

// Documentacion.
Route::get('/v1/documentation', [DocumentationController::class, 'ui']);
Route::get('/v1/openapi.json', [DocumentationController::class, 'json']);
