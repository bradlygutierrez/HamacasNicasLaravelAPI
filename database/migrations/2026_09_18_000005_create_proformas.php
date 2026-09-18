<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proformas', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->string('numero', 50)->nullable()->unique();
            $table->integer('cliente_id')->nullable();
            $table->integer('vendedor_id');
            $table->string('estado', 20)->default('borrador')->index();
            $table->string('nombre_cliente', 150);
            $table->string('ruc', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 150)->nullable();
            $table->date('fecha')->index();
            $table->date('valida_hasta')->nullable();
            $table->text('observaciones')->nullable();
            foreach (['subtotal_productos', 'subtotal_servicios', 'subtotal_bruto', 'descuento', 'base_neta', 'monto_iva', 'monto_ir', 'monto_comision_vendedor', 'costo_materiales_estimado', 'costo_mano_de_obra_estimado', 'costo_servicios_base_estimado', 'costo_total_estimado', 'costo_compra_estimado', 'utilidad_estimada', 'total'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->boolean('aplica_iva')->default(false);
            $table->decimal('tasa_iva', 5, 2)->default(0);
            $table->boolean('aplica_ir')->default(false);
            $table->decimal('tasa_ir', 5, 2)->default(0);
            $table->decimal('tasa_comision_vendedor', 5, 2)->default(0);
            $table->timestamp('emitida_at')->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'vendedor_id', 'fecha']);
            $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete();
            $table->foreign('vendedor_id')->references('id')->on('usuarios')->restrictOnDelete();
        });

        Schema::create('proforma_detalles', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->integer('proforma_id');
            $table->integer('hamaca_id')->nullable();
            $table->integer('hamaca_variante_id')->nullable();
            $table->integer('receta_hamaca_id')->nullable();
            $table->unsignedInteger('receta_version_snapshot');
            $table->string('hamaca_nombre_snapshot', 150);
            $table->text('hamaca_descripcion_snapshot')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('costo_unitario_estimado', 14, 2)->default(0);
            $table->decimal('costo_total_estimado', 14, 2)->default(0);
            $table->timestamps();
            $table->foreign('proforma_id')->references('id')->on('proformas')->cascadeOnDelete();
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->nullOnDelete();
            $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes')->nullOnDelete();
            $table->foreign('receta_hamaca_id')->references('id')->on('recetas_hamaca')->nullOnDelete();
        });

        foreach (['proforma_detalle_servicios', 'proforma_servicios'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->integer('id', true);
                if ($tableName === 'proforma_detalle_servicios') {
                    $table->integer('proforma_detalle_id');
                } else {
                    $table->integer('proforma_id');
                }
                $table->integer('servicio_adicional_id')->nullable();
                $table->string('servicio_nombre_snapshot', 150);
                $table->string('alcance_snapshot', 20);
                $table->string('metodo_calculo_snapshot', 30);
                $table->string('unidad_snapshot', 50)->nullable();
                $table->text('detalle')->nullable();
                $table->decimal('cantidad', 12, 4);
                $table->decimal('precio_unitario', 14, 2);
                $table->decimal('descuento', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('costo_base_unitario_snapshot', 14, 2)->default(0);
                $table->decimal('costo_unitario_estimado', 14, 2)->default(0);
                $table->decimal('costo_total_estimado', 14, 2)->default(0);
                $table->timestamps();
                if ($tableName === 'proforma_detalle_servicios') {
                    $table->foreign('proforma_detalle_id')->references('id')->on('proforma_detalles')->cascadeOnDelete();
                } else {
                    $table->foreign('proforma_id')->references('id')->on('proformas')->cascadeOnDelete();
                }
                $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->nullOnDelete();
            });
        }

        foreach (['proforma_materiales_snapshot', 'proforma_mano_obra_snapshot'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
                $table->integer('id', true);
                $table->integer('proforma_id');
                $table->string('origen_tipo', 30);
                $table->integer('origen_id');
                if ($tableName === 'proforma_materiales_snapshot') {
                    $table->integer('material_id')->nullable();
                    $table->string('material_nombre_snapshot', 150);
                    $table->string('unidad_consumo_snapshot', 50);
                    $table->string('unidad_compra_snapshot', 50);
                    $table->decimal('cantidad_base_unitaria', 12, 4);
                    $table->decimal('factor_cantidad', 12, 4);
                    $table->decimal('porcentaje_merma', 5, 2);
                    $table->decimal('cantidad_total_con_merma', 14, 4);
                    $table->decimal('contenido_por_compra_snapshot', 14, 4);
                    $table->decimal('precio_compra_snapshot', 14, 2);
                    $table->decimal('costo_unidad_consumo_snapshot', 14, 6);
                    $table->decimal('costo_consumo_total', 14, 2);
                    $table->foreign('material_id')->references('id')->on('materiales')->nullOnDelete();
                } else {
                    $table->integer('proceso_produccion_id')->nullable();
                    $table->string('proceso_nombre_snapshot', 150);
                    $table->decimal('costo_unitario_snapshot', 14, 2);
                    $table->decimal('factor_cantidad', 12, 4);
                    $table->decimal('costo_total', 14, 2);
                    $table->unsignedInteger('orden')->nullable();
                    $table->foreign('proceso_produccion_id')->references('id')->on('procesos_produccion')->nullOnDelete();
                }
                $table->timestamps();
                $table->foreign('proforma_id')->references('id')->on('proformas')->cascadeOnDelete();
                $table->index(['proforma_id', 'origen_tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_mano_obra_snapshot');
        Schema::dropIfExists('proforma_materiales_snapshot');
        Schema::dropIfExists('proforma_servicios');
        Schema::dropIfExists('proforma_detalle_servicios');
        Schema::dropIfExists('proforma_detalles');
        Schema::dropIfExists('proformas');
    }
};
