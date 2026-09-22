<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_hamaca', function (Blueprint $table): void {
            $table->integer('hamaca_variante_id')->nullable()->after('hamaca_id');
            $table->foreign('hamaca_variante_id')
                ->references('id')
                ->on('hamaca_variantes')
                ->nullOnDelete();
        });

        Schema::table('recetas_hamaca', function (Blueprint $table): void {
            $table->dropUnique(['hamaca_id', 'version']);
        });

        DB::transaction(function (): void {
            $legacyRecipes = DB::table('recetas_hamaca')
                ->whereNull('hamaca_variante_id')
                ->orderBy('id')
                ->get();

            foreach ($legacyRecipes as $legacy) {
                $variantIds = DB::table('hamaca_variantes')
                    ->where('hamaca_id', $legacy->hamaca_id)
                    ->orderBy('id')
                    ->pluck('id');

                foreach ($variantIds as $variantId) {
                    $now = now();
                    $recipeId = DB::table('recetas_hamaca')->insertGetId([
                        'hamaca_id' => $legacy->hamaca_id,
                        'hamaca_variante_id' => $variantId,
                        'version' => $legacy->version,
                        'estado' => $legacy->estado,
                        'observaciones' => $legacy->observaciones,
                        'usuario_id' => $legacy->usuario_id,
                        'activado_por_id' => $legacy->activado_por_id,
                        'activated_at' => $legacy->activated_at,
                        'created_at' => $legacy->created_at ?? $now,
                        'updated_at' => $legacy->updated_at ?? $now,
                    ]);

                    $materials = DB::table('receta_materiales')
                        ->where('receta_hamaca_id', $legacy->id)
                        ->get();
                    foreach ($materials as $material) {
                        DB::table('receta_materiales')->insert([
                            'receta_hamaca_id' => $recipeId,
                            'material_id' => $material->material_id,
                            'cantidad' => $material->cantidad,
                            'porcentaje_merma' => $material->porcentaje_merma,
                            'created_at' => $material->created_at ?? $now,
                            'updated_at' => $material->updated_at ?? $now,
                        ]);
                    }

                    $labor = DB::table('receta_mano_obra')
                        ->where('receta_hamaca_id', $legacy->id)
                        ->get();
                    foreach ($labor as $row) {
                        DB::table('receta_mano_obra')->insert([
                            'receta_hamaca_id' => $recipeId,
                            'proceso_produccion_id' => $row->proceso_produccion_id,
                            'costo_unitario' => $row->costo_unitario,
                            'orden' => $row->orden,
                            'created_at' => $row->created_at ?? $now,
                            'updated_at' => $row->updated_at ?? $now,
                        ]);
                    }
                }
            }
        });

        Schema::table('recetas_hamaca', function (Blueprint $table): void {
            $table->unique(['hamaca_variante_id', 'version']);
            $table->index('hamaca_variante_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La migración de recetas por variante es irreversible para preservar los clones históricos.');
    }
};
