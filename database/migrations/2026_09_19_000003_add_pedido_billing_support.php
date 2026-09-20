<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void { $table->dateTime('facturado_at')->nullable()->after('fecha_terminado'); });
        Schema::table('facturas', function (Blueprint $table): void {
            $table->integer('pedido_id')->nullable()->unique()->after('id');
            $table->string('origen', 30)->default('venta_directa')->after('pedido_id');
            $table->boolean('aplica_iva')->default(true)->after('tasa_iva');
            $table->foreign('pedido_id')->references('id')->on('pedidos')->restrictOnDelete();
        });
        Schema::table('detalle_facturas', function (Blueprint $table): void {
            $table->integer('pedido_detalle_id')->nullable()->after('factura_id');
            $table->decimal('descuento', 14, 2)->default(0)->after('precio_unitario');
            $table->foreign('pedido_detalle_id')->references('id')->on('pedido_detalles')->nullOnDelete();
        });
        Schema::table('movimientos', function (Blueprint $table): void { $table->integer('pedido_id')->nullable()->after('factura_id'); $table->foreign('pedido_id')->references('id')->on('pedidos')->nullOnDelete(); });
        Schema::create('detalle_factura_servicios', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('detalle_factura_id'); $table->integer('pedido_detalle_servicio_id')->nullable(); $table->integer('servicio_adicional_id')->nullable(); $table->string('servicio_nombre_snapshot'); $table->text('detalle')->nullable(); $table->decimal('cantidad', 12, 4); $table->decimal('precio_unitario', 14, 2); $table->decimal('descuento', 14, 2)->default(0); $table->decimal('subtotal', 14, 2); $table->timestamps();
            $table->foreign('detalle_factura_id')->references('id')->on('detalle_facturas')->cascadeOnDelete(); $table->foreign('pedido_detalle_servicio_id')->references('id')->on('pedido_detalle_servicios')->nullOnDelete(); $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->nullOnDelete();
        });
        Schema::create('factura_servicios', function (Blueprint $table): void {
            $table->integer('id', true); $table->integer('factura_id'); $table->integer('pedido_servicio_id')->nullable(); $table->integer('servicio_adicional_id')->nullable(); $table->string('servicio_nombre_snapshot'); $table->text('detalle')->nullable(); $table->decimal('cantidad', 12, 4); $table->decimal('precio_unitario', 14, 2); $table->decimal('descuento', 14, 2)->default(0); $table->decimal('subtotal', 14, 2); $table->timestamps();
            $table->foreign('factura_id')->references('id')->on('facturas')->cascadeOnDelete(); $table->foreign('pedido_servicio_id')->references('id')->on('pedido_servicios')->nullOnDelete(); $table->foreign('servicio_adicional_id')->references('id')->on('servicios_adicionales')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('factura_servicios'); Schema::dropIfExists('detalle_factura_servicios');
        Schema::table('movimientos', function (Blueprint $table): void { $table->dropForeign(['pedido_id']); $table->dropColumn('pedido_id'); });
        Schema::table('detalle_facturas', function (Blueprint $table): void { $table->dropForeign(['pedido_detalle_id']); $table->dropColumn(['pedido_detalle_id', 'descuento']); });
        Schema::table('facturas', function (Blueprint $table): void { $table->dropForeign(['pedido_id']); $table->dropUnique(['pedido_id']); $table->dropColumn(['pedido_id', 'origen', 'aplica_iva']); });
        Schema::table('pedidos', function (Blueprint $table): void { $table->dropColumn('facturado_at'); });
    }
};
