<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $parents = DB::table('hamacas')->orderBy('id')->get()->keyBy('id');
            $variants = DB::table('hamaca_variantes')->orderBy('id')->get();
            $variantParents = $variants->groupBy('hamaca_id');
            $now = now()->toDateTimeString();

            foreach ($variants as $variant) {
                $parent = $parents->get($variant->hamaca_id);
                if (!$parent) {
                    throw new RuntimeException("La variante {$variant->id} no tiene Hamaca padre {$variant->hamaca_id}.");
                }

                $category = DB::table('categorias')->where('id', $parent->categoria_id)->value('nombre');
                $size = DB::table('tamanos')->where('id', $parent->tamano_id)->value('nombre');
                if ($category === null || $size === null) {
                    throw new RuntimeException("La Hamaca padre {$parent->id} no tiene categoría o tamaño válido.");
                }

                $colorIds = $this->variantColorIds((int) $variant->id);
                $colorNames = $colorIds === []
                    ? []
                    : DB::table('colores')->whereIn('id', $colorIds)->orderBy('nombre')->pluck('nombre')->all();
                $baseName = trim($category . ' ' . $size);
                $variantName = trim((string) $variant->nombre);
                $name = $colorNames !== []
                    ? $baseName . ' - ' . implode(' / ', $colorNames)
                    : ($variantName !== '' ? $baseName . ' - ' . $variantName : $baseName);

                $legacyInventoryIds = DB::table('inventario_hamacas')
                    ->where('hamaca_id', $parent->id)
                    ->whereNull('hamaca_variante_id')
                    ->pluck('id')
                    ->all();
                if ($legacyInventoryIds !== []) {
                    throw new RuntimeException("La Hamaca padre {$parent->id} tiene inventarios legacy sin variante asociada: " . implode(', ', $legacyInventoryIds) . '.');
                }

                $createdAt = $variant->created_at ?: $now;
                $updatedAt = $variant->updated_at ?: $createdAt;
                $promotedId = DB::table('hamacas')->insertGetId([
                    'nombre' => $name,
                    'descripcion' => $parent->descripcion,
                    'categoria_id' => $parent->categoria_id,
                    'tamano_id' => $parent->tamano_id,
                    'precio' => $parent->precio,
                    'deleted_at' => (bool) $variant->state ? null : $now,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]);

                DB::table('hamaca_variant_promotion_map')->insert([
                    'hamaca_variante_id' => $variant->id,
                    'original_hamaca_id' => $parent->id,
                    'promoted_hamaca_id' => $promotedId,
                ]);

                $this->copyPivotRows(
                    'hamaca_color',
                    'hamaca_id',
                    $promotedId,
                    $colorIds,
                    'color_id',
                    $this->variantColorTimestamps((int) $variant->id),
                    $now,
                );

                $photoIds = DB::table('hamaca_variante_foto')
                    ->where('hamaca_variante_id', $variant->id)
                    ->orderBy('foto_id')
                    ->pluck('foto_id')
                    ->all();
                if ($photoIds === []) {
                    $photoIds = DB::table('hamaca_foto')
                        ->where('hamaca_id', $parent->id)
                        ->orderBy('foto_id')
                        ->pluck('foto_id')
                        ->all();
                }
                $this->copyPivotRows('hamaca_foto', 'hamaca_id', $promotedId, $photoIds, 'foto_id', [], $now);
            }

            foreach ($variantParents as $parentId => $parentVariants) {
                DB::table('hamacas')->where('id', $parentId)->whereNull('deleted_at')->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($parents as $parent) {
                if ($variantParents->has($parent->id)) {
                    continue;
                }

                $inventoryRows = DB::table('inventario_hamacas')->where('hamaca_id', $parent->id)->orderBy('id')->get();
                $colorIds = $this->singleInventoryColorComposition($inventoryRows, "Hamaca padre {$parent->id}");
                $this->copyPivotRows('hamaca_color', 'hamaca_id', $parent->id, $colorIds, 'color_id', [], $now);
            }

            $maps = DB::table('hamaca_variant_promotion_map')->orderBy('hamaca_variante_id')->get();
            foreach ($maps as $map) {
                DB::table('recetas_hamaca')->where('hamaca_variante_id', $map->hamaca_variante_id)->update([
                    'hamaca_id' => $map->promoted_hamaca_id,
                ]);
                DB::table('inventario_hamacas')->where('hamaca_variante_id', $map->hamaca_variante_id)->update([
                    'hamaca_id' => $map->promoted_hamaca_id,
                ]);
                DB::table('proforma_detalles')->where('hamaca_variante_id', $map->hamaca_variante_id)->update([
                    'hamaca_id' => $map->promoted_hamaca_id,
                ]);
                DB::table('pedido_detalles')->where('hamaca_variante_id', $map->hamaca_variante_id)->update([
                    'hamaca_id' => $map->promoted_hamaca_id,
                ]);
            }

            $this->consolidateDuplicateInventory();
            $this->alignInvoiceHamacaIds();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La promoción de variantes a hamacas no es reversible automáticamente.');
    }

    /** @return array<int> */
    private function variantColorIds(int $variantId): array
    {
        $colorIds = DB::table('hamaca_variante_color')
            ->where('hamaca_variante_id', $variantId)
            ->orderBy('color_id')
            ->pluck('color_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($colorIds !== []) {
            return $colorIds;
        }

        $inventoryRows = DB::table('inventario_hamacas')
            ->where('hamaca_variante_id', $variantId)
            ->orderBy('id')
            ->get();

        return $this->singleInventoryColorComposition($inventoryRows, "Variante {$variantId}");
    }

    /** @return array<int> */
    private function singleInventoryColorComposition(iterable $inventoryRows, string $owner): array
    {
        $compositions = [];
        $inventoryIds = [];

        foreach ($inventoryRows as $inventory) {
            $inventoryIds[] = (int) $inventory->id;
            $colorIds = DB::table('inventario_hamaca_color')
                ->where('inventario_hamaca_id', $inventory->id)
                ->orderBy('color_id')
                ->pluck('color_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $compositions[json_encode($colorIds)] = $colorIds;
        }

        if (count($compositions) > 1) {
            throw new RuntimeException("{$owner} tiene composiciones de colores incompatibles en inventarios: " . implode(', ', $inventoryIds) . '.');
        }

        return $compositions === [] ? [] : array_values($compositions)[0];
    }

    /** @return array<int, array{created_at: string, updated_at: string}> */
    private function variantColorTimestamps(int $variantId): array
    {
        return DB::table('hamaca_variante_color')
            ->where('hamaca_variante_id', $variantId)
            ->get(['color_id', 'created_at', 'updated_at'])
            ->mapWithKeys(fn ($row): array => [(int) $row->color_id => [
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]])
            ->all();
    }

    /** @param array<int> $relatedIds
     *  @param array<int, array{created_at: ?string, updated_at: ?string}> $sourceTimestamps
     */
    private function copyPivotRows(
        string $table,
        string $ownerColumn,
        int $ownerId,
        array $relatedIds,
        string $relatedColumn,
        array $sourceTimestamps,
        string $now,
    ): void {
        foreach ($relatedIds as $relatedId) {
            $timestamps = $sourceTimestamps[$relatedId] ?? [];
            DB::table($table)->insertOrIgnore([
                $ownerColumn => $ownerId,
                $relatedColumn => $relatedId,
                'created_at' => $timestamps['created_at'] ?? $now,
                'updated_at' => $timestamps['updated_at'] ?? $now,
            ]);
        }
    }

    private function consolidateDuplicateInventory(): void
    {
        $groups = DB::table('inventario_hamacas')
            ->orderBy('id')
            ->get(['id', 'hamaca_id', 'usuario_id', 'ubicacion_id', 'cantidad'])
            ->groupBy(fn ($row): string => implode(':', [$row->hamaca_id, $row->usuario_id, $row->ubicacion_id]));
        $references = $this->inventoryForeignKeyReferences();

        foreach ($groups as $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            $canonical = $rows->first();
            DB::table('inventario_hamacas')->where('id', $canonical->id)->update([
                'cantidad' => $rows->sum('cantidad'),
            ]);

            foreach ($rows->skip(1) as $duplicate) {
                foreach ($references as [$table, $column]) {
                    DB::table($table)->where($column, $duplicate->id)->update([$column => $canonical->id]);
                }
                DB::table('inventario_hamacas')->where('id', $duplicate->id)->delete();
            }
        }
    }

    /** @return array<int, array{0: string, 1: string}> */
    private function inventoryForeignKeyReferences(): array
    {
        $references = [];
        foreach (Schema::getTables() as $tableInfo) {
            $table = $tableInfo['name'] ?? null;
            if (!$table || $table === 'inventario_hamacas') {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if (($foreignKey['foreign_table'] ?? null) !== 'inventario_hamacas'
                    || ($foreignKey['foreign_columns'] ?? []) !== ['id']) {
                    continue;
                }
                foreach ($foreignKey['columns'] ?? [] as $column) {
                    $references[] = [$table, $column];
                }
            }
        }

        return $references;
    }

    private function alignInvoiceHamacaIds(): void
    {
        $details = DB::table('detalle_facturas')
            ->whereNotNull('inventario_hamaca_id')
            ->get(['id', 'inventario_hamaca_id']);

        foreach ($details as $detail) {
            $hamacaId = DB::table('inventario_hamacas')
                ->where('id', $detail->inventario_hamaca_id)
                ->value('hamaca_id');
            if ($hamacaId !== null) {
                DB::table('detalle_facturas')->where('id', $detail->id)->update(['hamaca_id' => $hamacaId]);
            }
        }
    }
};
