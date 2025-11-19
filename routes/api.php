<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\HamacaController;

//V1 API routes

//Categoy routes
Route::apiResource('/v1/categorias', App\Http\Controllers\API\V1\CategoriaController::class)
    ->only(['show', 'index', 'store', 'update']);

//Hamacas routes
    Route::apiResource('/v1/hamacas', HamacaController::class)
        ->only(['show', 'index', 'store', 'update']);

    Route::post('/v1/hamacas/{hamaca}/colores', [HamacaController::class, 'addColor']);
    Route::delete('/v1/hamacas/{hamaca}/colores/{color}', [HamacaController::class, 'removeColor']);

    // Asignar usuario a una hamaca
    Route::post('/v1/hamacas/{hamaca}/usuarios', [HamacaController::class, 'addUsuario']);

    // Quitar usuario de una hamaca
    Route::delete('/v1/hamacas/{hamaca}/usuarios/{usuario}', [HamacaController::class, 'removeUsuario']);


//Fotos Hamacas Route 
Route::apiResource('/v1/fotos_hamacas', App\Http\Controllers\API\V1\HamacaFotoController::class)
    ->only(['show', 'index', 'store', 'update']);

//Tamanos routes
Route::apiResource('/v1/tamanos', App\Http\Controllers\API\V1\TamanoController::class)
    ->only(['show', 'index', 'store', 'update']);

//ubicaciones routes
Route::apiResource('/v1/ubicacions', App\Http\Controllers\API\V1\UbicacionController::class)
    ->only(['show', 'index', 'store', 'update']);

//Usuarios routes
Route::apiResource('/v1/usuarios', App\Http\Controllers\API\V1\UsuarioController::class)
    ->only(['show', 'index', 'store', 'update']);

//Colores routes
Route::apiResource('/v1/colores', App\Http\Controllers\API\V1\ColorController::class)
    ->only(['show', 'index', 'store', 'update']);

//Movimientos routes
Route::apiResource('/v1/movimientos', App\Http\Controllers\API\V1\MovimientoController::class)
    ->only(['show', 'index', 'store', 'update']);

//Facturas Route 
Route::apiResource('/v1/facturas', App\Http\Controllers\API\V1\FacturaController::class)
    ->only(['show', 'index', 'store', 'update']);

//Detalle Facturas Route
Route::apiResource('/v1/detalle_facturas', App\Http\Controllers\API\V1\DetalleFacturaController::class)
    ->only(['show', 'index', 'store', 'update']);