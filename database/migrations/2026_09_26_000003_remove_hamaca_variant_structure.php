<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertAllVariantReferencesPromoted();
        $this->assertInventoryIdentityIsUnique();
        $expectedReferenceTables = [
            'hamaca_variant_promotion_map',
            'hamaca_variante_color',
            'hamaca_variante_foto',
            'inventario_hamacas',
            'recetas_hamaca',
            'proforma_detalles',
            'pedido_detalles',
        ];
        $unexpectedReferences = array_values(array_filter(
            $this->foreignKeysTo('hamaca_variantes'),
            fn (string $reference): bool => !in_array(strtok($reference, '('), $expectedReferenceTables, true),
        ));
        if ($unexpectedReferences !== []) {
            throw new RuntimeException('No se puede eliminar hamaca_variantes; referencias no contempladas: ' . implode(', ', $unexpectedReferences) . '.');
        }

        if (Schema::hasColumn('recetas_hamaca', 'hamaca_variante_id')) {
            $this->addUniqueIfMissing('recetas_hamaca', ['hamaca_id', 'version']);
            $this->dropForeignIfPresent('recetas_hamaca', 'hamaca_variante_id');
            $this->dropUniqueIfPresent('recetas_hamaca', ['hamaca_variante_id', 'version']);
            $this->dropIndexIfPresent('recetas_hamaca', ['hamaca_variante_id']);
            Schema::table('recetas_hamaca', fn (Blueprint $table) => $table->dropColumn('hamaca_variante_id'));
        }

        if (Schema::hasColumn('inventario_hamacas', 'hamaca_variante_id')) {
            $this->dropForeignIfPresent('inventario_hamacas', 'hamaca_variante_id');
        }
        if (Schema::hasColumn('inventario_hamacas', 'hamaca_id')) {
            // Keep a valid left-prefix index for the hamaca_id FK while replacing the old unique.
            $this->addUniqueIfMissing('inventario_hamacas', ['hamaca_id', 'usuario_id', 'ubicacion_id'], 'inventario_hamaca_producto_unico');
            $this->dropUniqueIfPresent('inventario_hamacas', ['hamaca_id', 'usuario_id', 'ubicacion_id', 'composicion_clave']);
        }
        $inventoryColumns = array_values(array_filter(['hamaca_variante_id', 'composicion_clave'], fn (string $column): bool => Schema::hasColumn('inventario_hamacas', $column)));
        if ($inventoryColumns !== []) Schema::table('inventario_hamacas', fn (Blueprint $table) => $table->dropColumn($inventoryColumns));

        foreach (['proforma_detalles', 'pedido_detalles'] as $tableName) {
            if (!Schema::hasColumn($tableName, 'hamaca_variante_id')) continue;
            $this->dropForeignIfPresent($tableName, 'hamaca_variante_id');
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('hamaca_variante_id'));
        }

        Schema::dropIfExists('inventario_hamaca_color');
        Schema::dropIfExists('hamaca_variante_foto');
        Schema::dropIfExists('hamaca_variante_color');
        Schema::dropIfExists('hamaca_variant_promotion_map');

        $remainingReferences = $this->foreignKeysTo('hamaca_variantes');
        if ($remainingReferences !== []) {
            throw new RuntimeException('No se puede eliminar hamaca_variantes; quedan claves foráneas: ' . implode(', ', $remainingReferences) . '.');
        }

        Schema::drop('hamaca_variantes');
    }

    public function down(): void
    {
        throw new RuntimeException('La consolidación de variantes en hamacas es irreversible automáticamente.');
    }

    private function assertAllVariantReferencesPromoted(): void
    {
        $unpromotedVariants = DB::table('hamaca_variantes as variant')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('hamaca_variant_promotion_map as promotion_map')
                    ->whereColumn('promotion_map.hamaca_variante_id', 'variant.id');
            })
            ->pluck('variant.id')
            ->all();
        if ($unpromotedVariants !== []) {
            throw new RuntimeException('No se pueden limpiar variantes sin producto promovido: ' . implode(', ', $unpromotedVariants) . '.');
        }

        $checks = [
            'recetas_hamaca' => 'hamaca_variante_id',
            'inventario_hamacas' => 'hamaca_variante_id',
            'proforma_detalles' => 'hamaca_variante_id',
            'pedido_detalles' => 'hamaca_variante_id',
        ];

        foreach ($checks as $table => $column) {
            if (!Schema::hasColumn($table, $column)) {
                continue;
            }
            $unmappedIds = DB::table($table . ' as source')
                ->whereNotNull('source.' . $column)
                ->whereNotExists(function ($query) use ($column): void {
                    $query->selectRaw('1')
                        ->from('hamaca_variant_promotion_map as promotion_map')
                        ->whereColumn('promotion_map.hamaca_variante_id', 'source.' . $column);
                })
                ->pluck('source.id')
                ->all();

            if ($unmappedIds !== []) {
                throw new RuntimeException("No se pueden limpiar variantes; {$table} sin mapeo en IDs: " . implode(', ', $unmappedIds) . '.');
            }
        }

        $remainingParents = DB::table('hamacas')
            ->whereIn('id', DB::table('hamaca_variant_promotion_map')->select('original_hamaca_id'))
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();
        if ($remainingParents !== []) {
            throw new RuntimeException('Hay Hamacas padre con variantes que no fueron archivadas: ' . implode(', ', $remainingParents) . '.');
        }
    }

    private function assertInventoryIdentityIsUnique(): void
    {
        if (!Schema::hasColumn('inventario_hamacas', 'hamaca_variante_id')) {
            return;
        }
        $duplicateGroups = DB::table('inventario_hamacas')
            ->select('hamaca_id', 'usuario_id', 'ubicacion_id', DB::raw('COUNT(*) as total'))
            ->groupBy('hamaca_id', 'usuario_id', 'ubicacion_id')
            ->having('total', '>', 1)
            ->get();

        if ($duplicateGroups->isNotEmpty()) {
            $ids = $duplicateGroups->map(fn ($row): string => "{$row->hamaca_id}/{$row->usuario_id}/{$row->ubicacion_id}")->implode(', ');
            throw new RuntimeException('No se puede cambiar la unicidad del inventario; hay grupos duplicados: ' . $ids . '.');
        }
    }

    /** @return array<int, string> */
    private function foreignKeysTo(string $targetTable): array
    {
        $references = [];
        foreach (Schema::getTables() as $tableInfo) {
            $table = $tableInfo['name'] ?? null;
            if (!$table) {
                continue;
            }
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (($foreignKey['foreign_table'] ?? null) === $targetTable) {
                    $references[] = $table . '(' . implode(',', $foreignKey['columns'] ?? []) . ')';
                }
            }
        }

        return $references;
    }

    private function dropForeignIfPresent(string $tableName, string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys($tableName))->first(fn (array $key): bool => in_array($column, $key['columns'] ?? [], true));
        if ($foreignKey) Schema::table($tableName, fn (Blueprint $table) => $table->dropForeign($foreignKey['name']));
    }

    private function dropUniqueIfPresent(string $tableName, array $columns): void
    {
        $index = collect(Schema::getIndexes($tableName))->first(fn (array $item): bool => ($item['unique'] ?? false) && ($item['columns'] ?? []) === $columns);
        if ($index) Schema::table($tableName, fn (Blueprint $table) => $table->dropUnique($index['name']));
    }

    private function dropIndexIfPresent(string $tableName, array $columns): void
    {
        $index = collect(Schema::getIndexes($tableName))->first(fn (array $item): bool => !($item['unique'] ?? false) && ($item['columns'] ?? []) === $columns);
        if ($index) Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($index['name']));
    }

    private function addUniqueIfMissing(string $tableName, array $columns, ?string $name = null): void
    {
        $exists = collect(Schema::getIndexes($tableName))->contains(fn (array $item): bool => ($item['unique'] ?? false) && ($item['columns'] ?? []) === $columns);
        if (!$exists) Schema::table($tableName, fn (Blueprint $table) => $table->unique($columns, $name));
    }
};
