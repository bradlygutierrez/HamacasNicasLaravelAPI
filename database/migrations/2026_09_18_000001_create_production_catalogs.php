<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materiales', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('codigo', 50)->nullable()->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('unidad_consumo', 50);
            $table->string('unidad_compra', 50);
            $table->decimal('contenido_por_compra', 12, 4)->nullable();
            $table->decimal('precio_actual', 12, 2);
            $table->decimal('porcentaje_merma', 5, 2)->default(0);
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        Schema::create('procesos_produccion', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('codigo', 50)->nullable()->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        Schema::create('servicios_adicionales', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('codigo', 50)->nullable()->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->string('alcance', 20);
            $table->string('metodo_calculo', 30);
            $table->string('unidad', 50)->nullable();
            $table->decimal('precio_venta_actual', 12, 2);
            $table->decimal('costo_actual', 12, 2);
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        Schema::create('material_precios', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('material_id');
            $table->decimal('precio', 12, 2);
            $table->timestamp('fecha');
            $table->integer('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('material_id')
                ->references('id')
                ->on('materiales')
                ->cascadeOnDelete();

            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')
                ->nullOnDelete();
        });

        Schema::create('servicio_precios', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('servicio_adicional_id');
            $table->decimal('precio_venta', 12, 2);
            $table->decimal('costo', 12, 2);
            $table->timestamp('fecha');
            $table->integer('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('servicio_adicional_id')
                ->references('id')
                ->on('servicios_adicionales')
                ->cascadeOnDelete();

            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_precios');
        Schema::dropIfExists('material_precios');
        Schema::dropIfExists('servicios_adicionales');
        Schema::dropIfExists('procesos_produccion');
        Schema::dropIfExists('materiales');
    }
};
