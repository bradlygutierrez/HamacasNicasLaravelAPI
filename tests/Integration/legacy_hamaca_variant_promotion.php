<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$connection = 'legacy_hamaca_promotion_test';
$database = 'hamacas_legacy_promotion_test_'.bin2hex(random_bytes(4));
$mysql = config('database.connections.mysql');
$admin = new PDO(
    sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $mysql['host'], $mysql['port'] ?? 3306),
    $mysql['username'],
    $mysql['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
config(["database.connections.{$connection}" => [...$mysql, 'database' => $database]]);
DB::purge($connection);
DB::setDefaultConnection($connection);

$schema = Schema::connection($connection);
$db = DB::connection($connection);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FALLO MIGRACIÓN LEGACY: '.$message);
};
$create = static function (string $table, Closure $definition) use ($schema): void {
    $schema->create($table, $definition);
};

try {
    $create('categorias', function (Blueprint $table): void { $table->integer('id', true); $table->string('nombre', 50); });
    $create('tamanos', function (Blueprint $table): void { $table->integer('id', true); $table->string('nombre', 50); });
    $create('colores', function (Blueprint $table): void { $table->integer('id', true); $table->string('nombre', 50); $table->timestamps(); });
    $create('fotos', function (Blueprint $table): void { $table->integer('id', true); $table->string('ruta', 255); $table->timestamps(); });
    $create('hamacas', function (Blueprint $table): void { $table->integer('id', true); $table->string('nombre', 100); $table->text('descripcion')->nullable(); $table->integer('categoria_id'); $table->integer('tamano_id'); $table->decimal('precio', 10, 2); $table->timestamp('deleted_at')->nullable(); $table->timestamps(); });
    $create('hamaca_foto', function (Blueprint $table): void { $table->integer('hamaca_id'); $table->integer('foto_id'); $table->timestamps(); $table->primary(['hamaca_id', 'foto_id']); $table->foreign('hamaca_id')->references('id')->on('hamacas'); $table->foreign('foto_id')->references('id')->on('fotos'); });
    $create('hamaca_variantes', function (Blueprint $table): void { $table->integer('id', true); $table->integer('hamaca_id'); $table->string('nombre', 100)->nullable(); $table->boolean('state')->default(true); $table->timestamps(); $table->foreign('hamaca_id')->references('id')->on('hamacas'); });
    $create('hamaca_variante_color', function (Blueprint $table): void { $table->integer('hamaca_variante_id'); $table->integer('color_id'); $table->timestamps(); $table->primary(['hamaca_variante_id', 'color_id']); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes'); $table->foreign('color_id')->references('id')->on('colores'); });
    $create('hamaca_variante_foto', function (Blueprint $table): void { $table->integer('hamaca_variante_id'); $table->integer('foto_id'); $table->timestamps(); $table->primary(['hamaca_variante_id', 'foto_id']); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes'); $table->foreign('foto_id')->references('id')->on('fotos'); });
    $create('inventario_hamacas', function (Blueprint $table): void { $table->integer('id', true); $table->integer('hamaca_id'); $table->integer('hamaca_variante_id')->nullable(); $table->integer('usuario_id'); $table->integer('ubicacion_id'); $table->string('composicion_clave', 64); $table->integer('cantidad')->default(0); $table->timestamps(); $table->unique(['hamaca_id', 'usuario_id', 'ubicacion_id', 'composicion_clave'], 'inventario_hamaca_usuario_ubicacion_composicion_unique'); $table->foreign('hamaca_id')->references('id')->on('hamacas'); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes'); });
    $create('inventario_hamaca_color', function (Blueprint $table): void { $table->integer('inventario_hamaca_id'); $table->integer('color_id'); $table->timestamps(); $table->primary(['inventario_hamaca_id', 'color_id']); $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas'); $table->foreign('color_id')->references('id')->on('colores'); });
    $create('recetas_hamaca', function (Blueprint $table): void { $table->integer('id', true); $table->integer('hamaca_id'); $table->integer('hamaca_variante_id')->nullable(); $table->integer('version'); $table->string('estado', 30); $table->text('observaciones')->nullable(); $table->integer('usuario_id')->nullable(); $table->integer('activado_por_id')->nullable(); $table->timestamp('activated_at')->nullable(); $table->timestamps(); $table->unique(['hamaca_variante_id', 'version'], 'recetas_variante_version_unique'); $table->foreign('hamaca_id')->references('id')->on('hamacas'); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes'); });
    $create('receta_materiales', function (Blueprint $table): void { $table->integer('id', true); $table->integer('receta_hamaca_id'); $table->integer('material_id'); $table->decimal('cantidad', 12, 4); $table->decimal('porcentaje_merma', 8, 4)->nullable(); $table->timestamps(); });
    $create('receta_mano_obra', function (Blueprint $table): void { $table->integer('id', true); $table->integer('receta_hamaca_id'); $table->integer('proceso_produccion_id'); $table->decimal('costo_unitario', 10, 2); $table->integer('orden')->default(0); $table->timestamps(); });
    $create('proforma_detalles', function (Blueprint $table): void { $table->integer('id', true); $table->integer('proforma_id'); $table->integer('hamaca_id')->nullable(); $table->integer('hamaca_variante_id')->nullable(); $table->integer('receta_hamaca_id')->nullable(); $table->integer('receta_version_snapshot')->nullable(); $table->string('hamaca_nombre_snapshot', 150); $table->text('hamaca_descripcion_snapshot')->nullable(); $table->integer('cantidad'); $table->decimal('precio_unitario', 10, 2); $table->decimal('descuento', 10, 2)->default(0); $table->decimal('subtotal', 10, 2); $table->timestamps(); $table->foreign('hamaca_id')->references('id')->on('hamacas'); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes'); });
    $create('pedido_detalles', function (Blueprint $table): void { $table->integer('id', true); $table->integer('pedido_id'); $table->integer('proforma_detalle_id')->nullable(); $table->integer('hamaca_id')->nullable(); $table->integer('hamaca_variante_id')->nullable(); $table->integer('receta_hamaca_id')->nullable(); $table->integer('receta_version_snapshot')->nullable(); $table->string('hamaca_nombre_snapshot', 150); $table->text('hamaca_descripcion_snapshot')->nullable(); $table->integer('cantidad'); $table->decimal('precio_unitario', 10, 2); $table->decimal('descuento', 10, 2)->default(0); $table->decimal('subtotal', 10, 2); $table->timestamps(); $table->foreign('hamaca_id')->references('id')->on('hamacas'); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes'); });
    $create('movimientos', function (Blueprint $table): void { $table->integer('id', true); $table->integer('inventario_hamaca_id'); $table->string('tipo', 30); $table->integer('cantidad'); $table->timestamps(); $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas'); });
    $create('detalle_facturas', function (Blueprint $table): void { $table->integer('id', true); $table->integer('factura_id'); $table->integer('inventario_hamaca_id'); $table->integer('hamaca_id'); $table->integer('usuario_id'); $table->integer('ubicacion_id'); $table->string('hamaca_nombre', 100); $table->text('hamaca_descripcion')->nullable(); $table->text('colores_snapshot')->nullable(); $table->integer('cantidad'); $table->decimal('precio_unitario', 10, 2); $table->decimal('subtotal', 10, 2); $table->timestamps(); $table->foreign('hamaca_id')->references('id')->on('hamacas'); $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas'); });

    $now = now()->toDateTimeString();
    $categoryId = $db->table('categorias')->insertGetId(['nombre' => 'Con palo']);
    $sizeId = $db->table('tamanos')->insertGetId(['nombre' => 'Familiar']);
    $parentId = $db->table('hamacas')->insertGetId(['nombre' => 'Modelo padre histórico', 'descripcion' => 'Descripción original', 'categoria_id' => $categoryId, 'tamano_id' => $sizeId, 'precio' => 1500, 'created_at' => $now, 'updated_at' => $now]);
    $colorIds = [];
    foreach (['Azul', 'Blanco', 'Rojo'] as $name) $colorIds[$name] = $db->table('colores')->insertGetId(['nombre' => $name, 'created_at' => $now, 'updated_at' => $now]);
    $photoIds = [];
    foreach (['a-1.jpg', 'a-2.jpg', 'b-1.jpg'] as $path) $photoIds[$path] = $db->table('fotos')->insertGetId(['ruta' => $path, 'created_at' => $now, 'updated_at' => $now]);
    $variantA = $db->table('hamaca_variantes')->insertGetId(['hamaca_id' => $parentId, 'nombre' => 'A', 'state' => true, 'created_at' => $now, 'updated_at' => $now]);
    $variantB = $db->table('hamaca_variantes')->insertGetId(['hamaca_id' => $parentId, 'nombre' => 'B', 'state' => true, 'created_at' => $now, 'updated_at' => $now]);
    foreach ([$colorIds['Azul'], $colorIds['Blanco']] as $colorId) $db->table('hamaca_variante_color')->insert(['hamaca_variante_id' => $variantA, 'color_id' => $colorId, 'created_at' => $now, 'updated_at' => $now]);
    $db->table('hamaca_variante_color')->insert(['hamaca_variante_id' => $variantB, 'color_id' => $colorIds['Rojo'], 'created_at' => $now, 'updated_at' => $now]);
    foreach ([$photoIds['a-1.jpg'], $photoIds['a-2.jpg']] as $photoId) $db->table('hamaca_variante_foto')->insert(['hamaca_variante_id' => $variantA, 'foto_id' => $photoId, 'created_at' => $now, 'updated_at' => $now]);
    $db->table('hamaca_variante_foto')->insert(['hamaca_variante_id' => $variantB, 'foto_id' => $photoIds['b-1.jpg'], 'created_at' => $now, 'updated_at' => $now]);

    $recipeIds = [];
    foreach ([[$variantA, 1, 'activa'], [$variantB, 1, 'archivada'], [$variantB, 2, 'activa']] as [$variantId, $version, $state]) {
        $recipeId = $db->table('recetas_hamaca')->insertGetId(['hamaca_id' => $parentId, 'hamaca_variante_id' => $variantId, 'version' => $version, 'estado' => $state, 'observaciones' => "Formula {$variantId} v{$version}", 'usuario_id' => 7, 'activado_por_id' => 8, 'activated_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        $recipeIds["{$variantId}:{$version}"] = $recipeId;
        $db->table('receta_materiales')->insert(['receta_hamaca_id' => $recipeId, 'material_id' => 11, 'cantidad' => $version * 2, 'porcentaje_merma' => 5, 'created_at' => $now, 'updated_at' => $now]);
        $db->table('receta_mano_obra')->insert(['receta_hamaca_id' => $recipeId, 'proceso_produccion_id' => 12, 'costo_unitario' => 75 + $version, 'orden' => 1, 'created_at' => $now, 'updated_at' => $now]);
    }

    $inventoryIds = [];
    foreach ([[$variantA, 'a-blue-white-1', 2], [$variantA, 'a-blue-white-2', 3], [$variantB, 'b-red', 4]] as [$variantId, $composition, $quantity]) {
        $inventoryId = $db->table('inventario_hamacas')->insertGetId(['hamaca_id' => $parentId, 'hamaca_variante_id' => $variantId, 'usuario_id' => 21, 'ubicacion_id' => 31, 'composicion_clave' => $composition, 'cantidad' => $quantity, 'created_at' => $now, 'updated_at' => $now]);
        $inventoryIds[] = $inventoryId;
    }
    foreach ([$inventoryIds[0], $inventoryIds[1]] as $inventoryId) foreach ([$colorIds['Azul'], $colorIds['Blanco']] as $colorId) $db->table('inventario_hamaca_color')->insert(['inventario_hamaca_id' => $inventoryId, 'color_id' => $colorId, 'created_at' => $now, 'updated_at' => $now]);
    foreach ($inventoryIds as $index => $inventoryId) {
        $db->table('movimientos')->insert(['inventario_hamaca_id' => $inventoryId, 'tipo' => 'entrada', 'cantidad' => [2, 3, 4][$index], 'created_at' => $now, 'updated_at' => $now]);
        $db->table('detalle_facturas')->insert(['factura_id' => 80 + $index, 'inventario_hamaca_id' => $inventoryId, 'hamaca_id' => $parentId, 'usuario_id' => 21, 'ubicacion_id' => 31, 'hamaca_nombre' => "Factura snapshot {$index}", 'hamaca_descripcion' => 'Snapshot de factura', 'colores_snapshot' => '["snapshot"]', 'cantidad' => 1, 'precio_unitario' => 1500, 'subtotal' => 1500, 'created_at' => $now, 'updated_at' => $now]);
    }
    $proforma = $db->table('proforma_detalles')->insertGetId(['proforma_id' => 71, 'hamaca_id' => $parentId, 'hamaca_variante_id' => $variantA, 'receta_hamaca_id' => $recipeIds["{$variantA}:1"], 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => 'Snapshot comercial PROFORMA', 'hamaca_descripcion_snapshot' => 'Snapshot descripción PROFORMA', 'cantidad' => 6, 'precio_unitario' => 1000, 'descuento' => 10, 'subtotal' => 5990, 'created_at' => $now, 'updated_at' => $now]);
    $pedido = $db->table('pedido_detalles')->insertGetId(['pedido_id' => 72, 'proforma_detalle_id' => $proforma, 'hamaca_id' => $parentId, 'hamaca_variante_id' => $variantB, 'receta_hamaca_id' => $recipeIds["{$variantB}:2"], 'receta_version_snapshot' => 2, 'hamaca_nombre_snapshot' => 'Snapshot comercial PEDIDO', 'hamaca_descripcion_snapshot' => 'Snapshot descripción PEDIDO', 'cantidad' => 3, 'precio_unitario' => 1500, 'descuento' => 20, 'subtotal' => 4480, 'created_at' => $now, 'updated_at' => $now]);

    $before = ['variants' => $db->table('hamaca_variantes')->count(), 'inventory' => $db->table('inventario_hamacas')->count(), 'movements' => $db->table('movimientos')->count(), 'invoice_details' => $db->table('detalle_facturas')->count(), 'recipes' => $db->table('recetas_hamaca')->count()];
    foreach ([
        '2026_09_26_000001_prepare_single_hamaca_products.php',
        '2026_09_26_000002_promote_hamaca_variants_to_products.php',
        '2026_09_26_000003_remove_hamaca_variant_structure.php',
    ] as $file) {
        $migration = require dirname(__DIR__, 2).'/database/migrations/'.$file;
        $migration->up();
    }

    $products = $db->table('hamacas')->where('id', '!=', $parentId)->orderBy('id')->get()->keyBy('nombre');
    $nameA = 'Con palo Familiar - Azul / Blanco';
    $nameB = 'Con palo Familiar - Rojo';
    $assert($products->has($nameA) && $products->has($nameB), 'promueve los dos productos con nombres y colores correctos');
    $promotedA = (int) $products[$nameA]->id;
    $promotedB = (int) $products[$nameB]->id;
    $assert($db->table('hamacas')->where('id', $parentId)->whereNotNull('deleted_at')->exists(), 'archiva el modelo padre');
    $assert($db->table('hamaca_color')->where('hamaca_id', $promotedA)->count() === 2 && $db->table('hamaca_color')->where('hamaca_id', $promotedB)->count() === 1, 'copia colores a cada Hamaca');
    $assert($db->table('hamaca_foto')->where('hamaca_id', $promotedA)->count() === 2 && $db->table('hamaca_foto')->where('hamaca_id', $promotedB)->count() === 1, 'copia fotos sin recrear archivos');
    foreach ($recipeIds as $key => $recipeId) {
        $expectedHamaca = str_starts_with($key, "{$variantA}:") ? $promotedA : $promotedB;
        $assert((int) $db->table('recetas_hamaca')->where('id', $recipeId)->value('hamaca_id') === $expectedHamaca, "mantiene ID de receta y remapea Hamaca para {$key}");
        $assert($db->table('receta_materiales')->where('receta_hamaca_id', $recipeId)->exists(), "mantiene materiales de receta {$key}");
        $assert($db->table('receta_mano_obra')->where('receta_hamaca_id', $recipeId)->exists(), "mantiene mano de obra de receta {$key}");
    }
    $assert((int) $db->table('inventario_hamacas')->where('id', $inventoryIds[0])->value('cantidad') === 5, 'suma duplicados en el menor ID');
    $assert(!$db->table('inventario_hamacas')->where('id', $inventoryIds[1])->exists(), 'elimina la fila duplicada después de consolidar');
    $assert((int) $db->table('inventario_hamacas')->where('id', $inventoryIds[2])->value('cantidad') === 4, 'mantiene cantidad no duplicada');
    $assert($db->table('movimientos')->count() === $before['movements'] && !$db->table('movimientos')->where('inventario_hamaca_id', $inventoryIds[1])->exists(), 'mantiene movimientos y reasigna la FK duplicada');
    $invoiceNames = $db->table('detalle_facturas')->orderBy('id')->pluck('hamaca_nombre')->all();
    $assert($invoiceNames === ['Factura snapshot 0', 'Factura snapshot 1', 'Factura snapshot 2'], 'no altera snapshots de factura');
    $assert((int) $db->table('detalle_facturas')->where('id', 1)->value('hamaca_id') === $promotedA, 'alinea el detalle de factura al producto asociado al inventario');
    $proformaAfter = $db->table('proforma_detalles')->where('id', $proforma)->first();
    $pedidoAfter = $db->table('pedido_detalles')->where('id', $pedido)->first();
    $assert($proformaAfter->hamaca_nombre_snapshot === 'Snapshot comercial PROFORMA' && $proformaAfter->hamaca_descripcion_snapshot === 'Snapshot descripción PROFORMA' && (int) $proformaAfter->receta_hamaca_id === $recipeIds["{$variantA}:1"] && (int) $proformaAfter->hamaca_id === $promotedA, 'preserva snapshots/receta y remapea FK de proforma');
    $assert($pedidoAfter->hamaca_nombre_snapshot === 'Snapshot comercial PEDIDO' && $pedidoAfter->hamaca_descripcion_snapshot === 'Snapshot descripción PEDIDO' && (int) $pedidoAfter->receta_hamaca_id === $recipeIds["{$variantB}:2"] && (int) $pedidoAfter->hamaca_id === $promotedB, 'preserva snapshots/receta y remapea FK de pedido');
    foreach (['hamaca_variantes', 'hamaca_variante_color', 'hamaca_variante_foto', 'inventario_hamaca_color', 'hamaca_variant_promotion_map'] as $removedTable) $assert(!$schema->hasTable($removedTable), "elimina {$removedTable}");
    foreach (['recetas_hamaca', 'inventario_hamacas', 'proforma_detalles', 'pedido_detalles'] as $table) $assert(!$schema->hasColumn($table, 'hamaca_variante_id'), "elimina FK/columna variante en {$table}");
    $assert(!$schema->hasColumn('inventario_hamacas', 'composicion_clave'), 'elimina composición del inventario');

    fwrite(STDOUT, json_encode(['database' => $database, 'before' => $before, 'after' => ['products_promoted' => $products->count(), 'active_products' => $db->table('hamacas')->whereIn('id', [$promotedA, $promotedB])->whereNull('deleted_at')->count(), 'inventory_rows' => $db->table('inventario_hamacas')->count(), 'movements' => $db->table('movimientos')->count(), 'invoice_details' => $db->table('detalle_facturas')->count(), 'recipes' => $db->table('recetas_hamaca')->count()], 'assertions' => 'passed']).PHP_EOL);
} finally {
    DB::disconnect($connection);
    $admin->exec("DROP DATABASE `{$database}`");
}
