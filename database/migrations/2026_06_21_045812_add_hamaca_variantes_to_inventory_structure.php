<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hamaca_variantes', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('hamaca_id');
            $table->string('nombre', 150)->nullable();
            $table->string('composicion_clave', 64);
            $table->boolean('state')->default(true);
            $table->timestamps();

            $table->foreign('hamaca_id')
                ->references('id')
                ->on('hamacas')
                ->cascadeOnDelete();

            $table->unique(
                ['hamaca_id', 'composicion_clave'],
                'hamaca_variante_unica'
            );
        });

        Schema::create('hamaca_variante_color', function (Blueprint $table) {
            $table->integer('hamaca_variante_id');
            $table->integer('color_id');
            $table->timestamps();

            $table->primary(['hamaca_variante_id', 'color_id']);

            $table->foreign('hamaca_variante_id')
                ->references('id')
                ->on('hamaca_variantes')
                ->cascadeOnDelete();

            $table->foreign('color_id')
                ->references('id')
                ->on('colores')
                ->cascadeOnDelete();
        });

        Schema::create('hamaca_variante_foto', function (Blueprint $table) {
            $table->integer('hamaca_variante_id');
            $table->integer('foto_id');
            $table->timestamps();

            $table->primary(['hamaca_variante_id', 'foto_id']);

            $table->foreign('hamaca_variante_id')
                ->references('id')
                ->on('hamaca_variantes')
                ->cascadeOnDelete();

            $table->foreign('foto_id')
                ->references('id')
                ->on('fotos')
                ->cascadeOnDelete();
        });

        Schema::table('inventario_hamacas', function (Blueprint $table) {
            $table->integer('hamaca_variante_id')->nullable()->after('hamaca_id');

            $table->foreign('hamaca_variante_id')
                ->references('id')
                ->on('hamaca_variantes')
                ->nullOnDelete();
        });

        /*
         * BACKFILL:
         * Crea variantes a partir del inventario actual.
         * Si ya existe una variante para la misma hamaca y composicion_clave,
         * la reutiliza para no duplicar.
         */
        $inventarios = DB::table('inventario_hamacas')->get();

        foreach ($inventarios as $inventario) {
            $existingVariant = DB::table('hamaca_variantes')
                ->where('hamaca_id', $inventario->hamaca_id)
                ->where('composicion_clave', $inventario->composicion_clave)
                ->first();

            if ($existingVariant) {
                $varianteId = $existingVariant->id;
            } else {
                $varianteId = DB::table('hamaca_variantes')->insertGetId([
                    'hamaca_id' => $inventario->hamaca_id,
                    'nombre' => null,
                    'composicion_clave' => $inventario->composicion_clave,
                    'state' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $colores = DB::table('inventario_hamaca_color')
                ->where('inventario_hamaca_id', $inventario->id)
                ->pluck('color_id');

            foreach ($colores as $colorId) {
                DB::table('hamaca_variante_color')->updateOrInsert(
                    [
                        'hamaca_variante_id' => $varianteId,
                        'color_id' => $colorId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }

            DB::table('inventario_hamacas')
                ->where('id', $inventario->id)
                ->update([
                    'hamaca_variante_id' => $varianteId,
                    'updated_at' => now(),
                ]);
        }

        /*
         * BACKFILL DE FOTOS:
         * Las fotos actuales están asociadas a hamacas.
         * Para no perderlas, se copian a todas las variantes de esa hamaca.
         */
        $variantes = DB::table('hamaca_variantes')->get();

        foreach ($variantes as $variante) {
            $fotoIds = DB::table('hamaca_foto')
                ->where('hamaca_id', $variante->hamaca_id)
                ->pluck('foto_id');

            foreach ($fotoIds as $fotoId) {
                DB::table('hamaca_variante_foto')->updateOrInsert(
                    [
                        'hamaca_variante_id' => $variante->id,
                        'foto_id' => $fotoId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('inventario_hamacas', function (Blueprint $table) {
            $table->dropForeign(['hamaca_variante_id']);
            $table->dropColumn('hamaca_variante_id');
        });

        Schema::dropIfExists('hamaca_variante_foto');
        Schema::dropIfExists('hamaca_variante_color');
        Schema::dropIfExists('hamaca_variantes');
    }
};