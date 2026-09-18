<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas_hamaca', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('hamaca_id');
            $table->unsignedInteger('version');
            $table->string('estado', 20);
            $table->text('observaciones')->nullable();
            $table->integer('usuario_id')->nullable();
            $table->integer('activado_por_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['hamaca_id', 'version']);
            $table->index('hamaca_id');
            $table->index('estado');
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
            $table->foreign('activado_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });

        Schema::create('receta_materiales', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('receta_hamaca_id');
            $table->integer('material_id');
            $table->decimal('cantidad', 12, 4);
            $table->decimal('porcentaje_merma', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['receta_hamaca_id', 'material_id']);
            $table->foreign('receta_hamaca_id')->references('id')->on('recetas_hamaca')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materiales')->restrictOnDelete();
        });

        Schema::create('receta_mano_obra', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('receta_hamaca_id');
            $table->integer('proceso_produccion_id');
            $table->decimal('costo_unitario', 12, 2);
            $table->integer('orden')->nullable();
            $table->timestamps();

            $table->unique(['receta_hamaca_id', 'proceso_produccion_id']);
            $table->foreign('receta_hamaca_id')->references('id')->on('recetas_hamaca')->cascadeOnDelete();
            $table->foreign('proceso_produccion_id')->references('id')->on('procesos_produccion')->restrictOnDelete();
        });

        Schema::create('servicio_materiales', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('servicio_adicional_id');
            $table->integer('material_id');
            $table->decimal('cantidad', 12, 4);
            $table->decimal('porcentaje_merma', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['servicio_adicional_id', 'material_id']);
            $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->cascadeOnDelete();
            $table->foreign('material_id')->references('id')->on('materiales')->restrictOnDelete();
        });

        Schema::create('servicio_mano_obra', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('servicio_adicional_id');
            $table->integer('proceso_produccion_id');
            $table->decimal('costo_unitario', 12, 2);
            $table->integer('orden')->nullable();
            $table->timestamps();

            $table->unique(['servicio_adicional_id', 'proceso_produccion_id']);
            $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->cascadeOnDelete();
            $table->foreign('proceso_produccion_id')->references('id')->on('procesos_produccion')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_mano_obra');
        Schema::dropIfExists('servicio_materiales');
        Schema::dropIfExists('receta_mano_obra');
        Schema::dropIfExists('receta_materiales');
        Schema::dropIfExists('recetas_hamaca');
    }
};
