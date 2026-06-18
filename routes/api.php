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
use App\Http\Controllers\API\V1\TamanoController;
use App\Http\Controllers\API\V1\UbicacionController;
use App\Http\Controllers\API\V1\UsuarioController;
use Illuminate\Support\Facades\Route;

// Autenticacion administrativa.
Route::post('/v1/login', [AuthController::class, 'login']);
Route::get('/v1/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::post('/v1/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// Catalogos base.
Route::get('/v1/categorias', [CategoriaController::class, 'index']);
Route::get('/v1/categorias/{categoria}', [CategoriaController::class, 'show']);
Route::post('/v1/categorias', [CategoriaController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/categorias/{categoria}', [CategoriaController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);

Route::get('/v1/tamanos', [TamanoController::class, 'index']);
Route::get('/v1/tamanos/{tamano}', [TamanoController::class, 'show']);
Route::post('/v1/tamanos', [TamanoController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/tamanos/{tamano}', [TamanoController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);

Route::get('/v1/ubicaciones', [UbicacionController::class, 'index']);
Route::get('/v1/ubicaciones/{ubicacion}', [UbicacionController::class, 'show']);
Route::post('/v1/ubicaciones', [UbicacionController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/ubicaciones/{ubicacion}', [UbicacionController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);

Route::get('/v1/colores', [ColorController::class, 'index']);
Route::get('/v1/colores/{colore}', [ColorController::class, 'show']);
Route::post('/v1/colores', [ColorController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/colores/{colore}', [ColorController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);

// Hamacas y su detalle.
Route::get('/v1/hamacas', [HamacaController::class, 'index']);
Route::get('/v1/hamacas/detalles', [HamacaController::class, 'getHamacasWithDetails']);
Route::get('/v1/hamacas/monthly-inventory', [HamacaController::class, 'getMonthlyInventory']);
Route::get('/v1/hamacas/{hamaca}', [HamacaController::class, 'show']);
Route::post('/v1/hamacas', [HamacaController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/hamacas/{hamaca}', [HamacaController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);

// Fotos.
Route::get('/v1/fotos', [FotoController::class, 'index']);
Route::get('/v1/fotos/{foto}', [FotoController::class, 'show']);
Route::post('/v1/fotos', [FotoController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/fotos/{foto}', [FotoController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);
Route::delete('/v1/fotos/{foto}', [FotoController::class, 'destroy'])->middleware(['auth:sanctum', 'role:admin']);

// Inventario fisico.
Route::get('/v1/inventario-hamacas', [InventarioHamacaController::class, 'index'])->middleware(['auth:sanctum']);
Route::get('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'show'])->middleware(['auth:sanctum']);
Route::post('/v1/inventario-hamacas', [InventarioHamacaController::class, 'store'])->middleware(['auth:sanctum', 'role:almacenista,admin']);
Route::put('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'update'])->middleware(['auth:sanctum', 'role:almacenista,admin']);
Route::delete('/v1/inventario-hamacas/{inventarioHamaca}', [InventarioHamacaController::class, 'destroy'])->middleware(['auth:sanctum', 'role:admin']);
Route::post('/v1/inventario-hamacas/transfer', [InventarioHamacaController::class, 'transfer'])->middleware(['auth:sanctum', 'role:almacenista,admin']);

// Usuarios administrativos.
Route::get('/v1/usuarios', [UsuarioController::class, 'index'])->middleware(['auth:sanctum', 'role:admin']);
Route::get('/v1/usuarios/{usuario}', [UsuarioController::class, 'show'])->middleware(['auth:sanctum', 'role:admin']);
Route::post('/v1/usuarios', [UsuarioController::class, 'store'])->middleware(['auth:sanctum', 'role:admin']);
Route::put('/v1/usuarios/{usuario}', [UsuarioController::class, 'update'])->middleware(['auth:sanctum', 'role:admin']);
Route::delete('/v1/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->middleware(['auth:sanctum', 'role:admin']);

// Movimientos.
Route::get('/v1/movimientos/monthly-entries', [MovimientoController::class, 'getMonthlyEntries'])->middleware('auth:sanctum');
Route::get('/v1/movimientos/monthly-exits', [MovimientoController::class, 'getMonthlyExits'])->middleware('auth:sanctum');
Route::apiResource('/v1/movimientos', MovimientoController::class)->only(['index', 'show'])->middleware('auth:sanctum');

// Facturacion y POS.
Route::apiResource('/v1/facturas', FacturaController::class)->only(['index', 'show'])->middleware('auth:sanctum');
Route::apiResource('/v1/detalle_facturas', DetalleFacturaController::class)->only(['index', 'show'])->middleware('auth:sanctum');

