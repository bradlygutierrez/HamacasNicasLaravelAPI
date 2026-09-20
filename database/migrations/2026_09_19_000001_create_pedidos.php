<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->integer('proforma_id')->unique();
            $table->string('numero')->nullable()->unique();
            $table->string('proforma_numero_snapshot')->nullable();
            $table->integer('cliente_id')->nullable();
            $table->integer('vendedor_id');
            $table->integer('creado_por_id')->nullable();
            $table->string('estado')->default('pendiente')->index();
            $table->string('nombre_cliente'); $table->string('ruc')->nullable(); $table->string('direccion')->nullable(); $table->string('telefono')->nullable(); $table->string('correo')->nullable();
            $table->date('fecha_pedido'); $table->date('fecha_entrega_estimada')->nullable(); $table->dateTime('fecha_inicio_produccion')->nullable(); $table->dateTime('fecha_terminado')->nullable();
            $table->text('observaciones_cliente')->nullable(); $table->text('observaciones_internas')->nullable();
            foreach (['subtotal_productos','subtotal_servicios','subtotal_bruto','descuento_global','descuento_lineas','descuento_total','base_neta','monto_iva','monto_ir','monto_comision_vendedor','costo_materiales_estimado','costo_mano_obra_estimado','costo_servicios_base_estimado','costo_total_estimado','costo_compra_estimado','utilidad_estimada','total'] as $column) $table->decimal($column, 14, 2)->default(0);
            $table->boolean('aplica_iva')->default(false); $table->decimal('tasa_iva', 5, 2)->default(0); $table->boolean('aplica_ir')->default(false); $table->decimal('tasa_ir', 5, 2)->default(0); $table->decimal('tasa_comision_vendedor', 5, 2)->default(0);
            $table->dateTime('cancelado_at')->nullable(); $table->integer('cancelado_por_id')->nullable(); $table->text('motivo_cancelacion')->nullable(); $table->timestamps(); $table->index(['fecha_pedido','estado']);
            $table->foreign('proforma_id')->references('id')->on('proformas')->restrictOnDelete(); $table->foreign('cliente_id')->references('id')->on('clientes')->nullOnDelete(); $table->foreign('vendedor_id')->references('id')->on('usuarios')->restrictOnDelete(); $table->foreign('creado_por_id')->references('id')->on('usuarios')->nullOnDelete(); $table->foreign('cancelado_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });
        Schema::create('pedido_detalles', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('pedido_id'); $table->integer('proforma_detalle_id')->nullable(); $table->integer('hamaca_id')->nullable(); $table->integer('hamaca_variante_id')->nullable(); $table->integer('receta_hamaca_id')->nullable(); $table->unsignedInteger('receta_version_snapshot'); $table->string('hamaca_nombre_snapshot'); $table->text('hamaca_descripcion_snapshot')->nullable(); $table->unsignedInteger('cantidad'); $table->decimal('precio_unitario',14,2); $table->decimal('descuento',14,2)->default(0); $table->decimal('subtotal',14,2); $table->decimal('costo_unitario_estimado',14,2)->default(0); $table->decimal('costo_total_estimado',14,2)->default(0); $table->timestamps();
            $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete(); $table->foreign('proforma_detalle_id')->references('id')->on('proforma_detalles')->nullOnDelete(); $table->foreign('hamaca_id')->references('id')->on('hamacas')->nullOnDelete(); $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes')->nullOnDelete(); $table->foreign('receta_hamaca_id')->references('id')->on('recetas_hamaca')->nullOnDelete();
        });
        Schema::create('pedido_detalle_servicios', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('pedido_detalle_id'); $table->integer('proforma_detalle_servicio_id')->nullable(); $table->integer('servicio_adicional_id')->nullable(); $table->string('servicio_nombre_snapshot'); $table->string('alcance_snapshot'); $table->string('metodo_calculo_snapshot'); $table->string('unidad_snapshot')->nullable(); $table->text('detalle')->nullable(); $table->decimal('cantidad',12,4); $table->decimal('precio_unitario',14,2); $table->decimal('descuento',14,2)->default(0); $table->decimal('subtotal',14,2); $table->decimal('costo_base_unitario_override',14,2)->nullable(); $table->decimal('costo_base_unitario_snapshot',14,2)->default(0); $table->decimal('costo_unitario_estimado',14,2)->default(0); $table->decimal('costo_total_estimado',14,2)->default(0); $table->timestamps();
            $table->foreign('pedido_detalle_id')->references('id')->on('pedido_detalles')->cascadeOnDelete(); $table->foreign('proforma_detalle_servicio_id')->references('id')->on('proforma_detalle_servicios')->nullOnDelete(); $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->nullOnDelete();
        });
        Schema::create('pedido_servicios', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('pedido_id'); $table->integer('proforma_servicio_id')->nullable(); $table->integer('servicio_adicional_id')->nullable(); $table->string('servicio_nombre_snapshot'); $table->string('alcance_snapshot'); $table->string('metodo_calculo_snapshot'); $table->string('unidad_snapshot')->nullable(); $table->text('detalle')->nullable(); $table->decimal('cantidad',12,4); $table->decimal('precio_unitario',14,2); $table->decimal('descuento',14,2)->default(0); $table->decimal('subtotal',14,2); $table->decimal('costo_base_unitario_override',14,2)->nullable(); $table->decimal('costo_base_unitario_snapshot',14,2)->default(0); $table->decimal('costo_unitario_estimado',14,2)->default(0); $table->decimal('costo_total_estimado',14,2)->default(0); $table->timestamps();
            $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete(); $table->foreign('proforma_servicio_id')->references('id')->on('proforma_servicios')->nullOnDelete(); $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->nullOnDelete();
        });
        Schema::create('pedido_materiales', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('pedido_id'); $table->integer('material_id')->nullable(); $table->string('material_nombre_snapshot'); $table->string('unidad_consumo_snapshot'); $table->string('unidad_compra_snapshot'); $table->decimal('cantidad_requerida',14,4); $table->decimal('contenido_por_compra_snapshot',14,4); $table->unsignedInteger('cantidad_compra_plan'); $table->decimal('precio_compra_snapshot',14,2); $table->decimal('costo_consumo_estimado',14,2); $table->decimal('costo_compra_estimado',14,2); $table->string('estado')->default('pendiente'); $table->decimal('cantidad_compra_real',14,4)->default(0); $table->decimal('costo_compra_real',14,2)->default(0); $table->text('observaciones')->nullable(); $table->dateTime('listo_at')->nullable(); $table->integer('actualizado_por_id')->nullable(); $table->timestamps(); $table->index(['pedido_id','estado']);
            $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete(); $table->foreign('material_id')->references('id')->on('materiales')->nullOnDelete(); $table->foreign('actualizado_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });
        Schema::create('pedido_procesos', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('pedido_id'); $table->integer('proceso_produccion_id')->nullable(); $table->string('proceso_nombre_snapshot'); $table->unsignedInteger('orden')->nullable(); $table->decimal('costo_estimado',14,2)->default(0); $table->string('estado')->default('pendiente'); $table->dateTime('iniciado_at')->nullable(); $table->dateTime('completado_at')->nullable(); $table->text('observaciones')->nullable(); $table->integer('actualizado_por_id')->nullable(); $table->timestamps(); $table->index(['pedido_id','estado']);
            $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete(); $table->foreign('proceso_produccion_id')->references('id')->on('procesos_produccion')->nullOnDelete(); $table->foreign('actualizado_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });
        Schema::create('pedido_historial_estados', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('pedido_id'); $table->string('estado_anterior')->nullable(); $table->string('estado_nuevo'); $table->integer('usuario_id')->nullable(); $table->text('comentario')->nullable(); $table->timestamp('created_at')->useCurrent(); $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete(); $table->foreign('usuario_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('pedido_historial_estados'); Schema::dropIfExists('pedido_procesos'); Schema::dropIfExists('pedido_materiales'); Schema::dropIfExists('pedido_servicios'); Schema::dropIfExists('pedido_detalle_servicios'); Schema::dropIfExists('pedido_detalles'); Schema::dropIfExists('pedidos'); }
};
