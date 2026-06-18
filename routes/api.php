<?php

use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\CategoriaController;
use App\Http\Controllers\API\V1\ColorController;
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
use Illuminate\Support\Facades\Route;

$auth = ['api.key', 'auth:sanctum'];
$admin = ['api.key', 'auth:sanctum', 'role:admin'];
$inventoryManager = ['api.key', 'auth:sanctum', 'role:almacenista,admin'];
$sales = ['api.key', 'auth:sanctum', 'role:vendedor,admin'];

// Autenticacion administrativa.
Route::post('/v1/login', [AuthController::class, 'login']);
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

// Hamacas y su detalle.
Route::get('/v1/hamacas', [HamacaController::class, 'index']);
Route::get('/v1/hamacas/detalles', [HamacaController::class, 'getHamacasWithDetails']);
Route::get('/v1/hamacas/monthly-inventory', [HamacaController::class, 'getMonthlyInventory']);
Route::get('/v1/hamacas/{hamaca}', [HamacaController::class, 'show']);
Route::post('/v1/hamacas', [HamacaController::class, 'store'])->middleware($admin);
Route::put('/v1/hamacas/{hamaca}', [HamacaController::class, 'update'])->middleware($admin);

// Fotos.
Route::get('/v1/fotos', [FotoController::class, 'index']);
Route::get('/v1/fotos/{foto}', [FotoController::class, 'show']);
Route::post('/v1/fotos', [FotoController::class, 'store'])->middleware($admin);
Route::put('/v1/fotos/{foto}', [FotoController::class, 'update'])->middleware($admin);
Route::delete('/v1/fotos/{foto}', [FotoController::class, 'destroy'])->middleware($admin);

// Inventario fisico.
Route::get('/v1/inventario-hamacas', [InventarioHamacaController::class, 'index'])->middleware($auth);
Route::get('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'show'])->middleware($auth);
Route::post('/v1/inventario-hamacas', [InventarioHamacaController::class, 'store'])->middleware($inventoryManager);
Route::put('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'update'])->middleware($inventoryManager);
Route::delete('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'destroy'])->middleware($admin);
Route::post('/v1/inventario-hamacas/transfer', [InventarioHamacaController::class, 'transfer'])->middleware($inventoryManager);

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

// Facturacion y POS.
Route::apiResource('/v1/facturas', FacturaController::class)->only(['index', 'show'])->middleware($auth);
Route::apiResource('/v1/detalle_facturas', DetalleFacturaController::class)->only(['index', 'show'])->middleware($auth);
Route::post('/v1/pos/ventas', [PosVentaController::class, 'store'])->middleware($sales);

// Documentacion.
Route::get('/v1/documentation', [DocumentationController::class, 'ui']);
Route::get('/v1/openapi.json', [DocumentationController::class, 'json']);
