<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuarios', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 100);
            $table->string('correo', 150)->unique();
            $table->string('password');
            $table->string('foto', 255)->nullable();
            $table->enum('rol', ['admin', 'vendedor', 'almacenista', 'socio'])->default('socio');
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        Schema::create('categorias', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('tamanos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 50);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('ubicaciones', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('colores', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 50)->unique();
            $table->timestamps();
        });

        Schema::create('hamacas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->integer('categoria_id');
            $table->integer('tamano_id');
            $table->decimal('precio', 10, 2);
            $table->timestamps();

            $table->foreign('categoria_id')
                ->references('id')
                ->on('categorias')
                ->cascadeOnDelete();

            $table->foreign('tamano_id')
                ->references('id')
                ->on('tamanos')
                ->cascadeOnDelete();
        });

        Schema::create('fotos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('ruta', 255);
            $table->timestamps();
        });

        Schema::create('hamaca_foto', function (Blueprint $table) {
            $table->integer('hamaca_id');
            $table->integer('foto_id');
            $table->timestamps();

            $table->primary(['hamaca_id', 'foto_id']);

            $table->foreign('hamaca_id')
                ->references('id')
                ->on('hamacas')
                ->cascadeOnDelete();

            $table->foreign('foto_id')
                ->references('id')
                ->on('fotos')
                ->cascadeOnDelete();
        });

        Schema::create('inventario_hamacas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('hamaca_id');
            $table->integer('usuario_id');
            $table->integer('ubicacion_id');
            $table->string('composicion_clave', 64);
            $table->integer('cantidad')->default(0);
            $table->timestamps();

            $table->foreign('hamaca_id')
                ->references('id')
                ->on('hamacas')
                ->cascadeOnDelete();

            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')
                ->cascadeOnDelete();

            $table->foreign('ubicacion_id')
                ->references('id')
                ->on('ubicaciones')
                ->cascadeOnDelete();

            $table->unique(['hamaca_id', 'usuario_id', 'ubicacion_id', 'composicion_clave'], 'inventario_hamaca_unico');
        });

        Schema::create('inventario_hamaca_color', function (Blueprint $table) {
            $table->integer('inventario_hamaca_id');
            $table->integer('color_id');
            $table->timestamps();

            $table->primary(['inventario_hamaca_id', 'color_id']);

            $table->foreign('inventario_hamaca_id')
                ->references('id')
                ->on('inventario_hamacas')
                ->cascadeOnDelete();

            $table->foreign('color_id')
                ->references('id')
                ->on('colores')
                ->cascadeOnDelete();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 150);
            $table->string('ruc', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 150)->nullable()->unique();
            $table->string('password')->nullable();
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        Schema::create('facturas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('numero', 50)->unique();
            $table->integer('cliente_id')->nullable();
            $table->integer('vendedor_id');
            $table->enum('canal', ['pos', 'ecommerce'])->default('pos');
            $table->string('nombre_cliente', 150);
            $table->string('ruc', 50)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('metodo_pago', 50)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('tasa_iva', 5, 4)->default(0.1500);
            $table->decimal('monto_iva', 10, 2)->default(0);
            $table->boolean('aplica_ir')->default(false);
            $table->decimal('tasa_ir', 5, 4)->default(0.0200);
            $table->decimal('monto_ir', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamp('fecha')->useCurrent();
            $table->timestamps();

            $table->foreign('cliente_id')
                ->references('id')
                ->on('clientes')
                ->nullOnDelete();

            $table->foreign('vendedor_id')
                ->references('id')
                ->on('usuarios')
                ->cascadeOnDelete();
        });

        Schema::create('detalle_facturas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('factura_id');
            $table->integer('inventario_hamaca_id');
            $table->integer('hamaca_id');
            $table->integer('usuario_id');
            $table->integer('ubicacion_id');
            $table->string('hamaca_nombre', 100);
            $table->text('hamaca_descripcion')->nullable();
            $table->text('colores_snapshot')->nullable();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->timestamps();

            $table->foreign('factura_id')
                ->references('id')
                ->on('facturas')
                ->cascadeOnDelete();

            $table->foreign('inventario_hamaca_id')
                ->references('id')
                ->on('inventario_hamacas')
                ->cascadeOnDelete();

            $table->foreign('hamaca_id')
                ->references('id')
                ->on('hamacas')
                ->cascadeOnDelete();

            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')
                ->cascadeOnDelete();

            $table->foreign('ubicacion_id')
                ->references('id')
                ->on('ubicaciones')
                ->cascadeOnDelete();
        });

        Schema::create('movimientos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('inventario_hamaca_id');
            $table->integer('usuario_id');
            $table->integer('factura_id')->nullable();
            $table->integer('ubicacion_origen_id')->nullable();
            $table->integer('ubicacion_destino_id')->nullable();
            $table->enum('tipo', ['entrada', 'salida', 'transferencia']);
            $table->integer('cantidad');
            $table->timestamp('fecha')->useCurrent();
            $table->timestamps();

            $table->foreign('inventario_hamaca_id')
                ->references('id')
                ->on('inventario_hamacas')
                ->cascadeOnDelete();

            $table->foreign('usuario_id')
                ->references('id')
                ->on('usuarios')
                ->cascadeOnDelete();

            $table->foreign('factura_id')
                ->references('id')
                ->on('facturas')
                ->nullOnDelete();

            $table->foreign('ubicacion_origen_id')
                ->references('id')
                ->on('ubicaciones')
                ->nullOnDelete();

            $table->foreign('ubicacion_destino_id')
                ->references('id')
                ->on('ubicaciones')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos');
        Schema::dropIfExists('detalle_facturas');
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('inventario_hamaca_color');
        Schema::dropIfExists('inventario_hamacas');
        Schema::dropIfExists('hamaca_foto');
        Schema::dropIfExists('fotos');
        Schema::dropIfExists('hamacas');
        Schema::dropIfExists('colores');
        Schema::dropIfExists('ubicaciones');
        Schema::dropIfExists('tamanos');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('usuarios');
    }
};
