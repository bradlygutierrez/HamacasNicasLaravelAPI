<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['vendedor_id']);
            $table->foreign('vendedor_id')->references('id')->on('usuarios')->restrictOnDelete();
        });

        Schema::table('detalle_facturas', function (Blueprint $table) {
            $table->dropForeign(['factura_id']);
            $table->dropForeign(['inventario_hamaca_id']);
            $table->dropForeign(['hamaca_id']);
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['ubicacion_id']);

            $table->foreign('factura_id')->references('id')->on('facturas')->restrictOnDelete();
            $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas')->restrictOnDelete();
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones')->restrictOnDelete();
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['inventario_hamaca_id']);
            $table->dropForeign(['usuario_id']);

            $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
        });

        Schema::table('inventario_hamacas', function (Blueprint $table) {
            $table->dropForeign(['hamaca_id']);
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['ubicacion_id']);

            $table->foreign('hamaca_id')->references('id')->on('hamacas')->restrictOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->restrictOnDelete();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones')->restrictOnDelete();
        });

        Schema::table('hamaca_variantes', function (Blueprint $table) {
            $table->dropForeign(['hamaca_id']);
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hamaca_variantes', function (Blueprint $table) {
            $table->dropForeign(['hamaca_id']);
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->cascadeOnDelete();
        });

        Schema::table('inventario_hamacas', function (Blueprint $table) {
            $table->dropForeign(['hamaca_id']);
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['ubicacion_id']);

            $table->foreign('hamaca_id')->references('id')->on('hamacas')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones')->cascadeOnDelete();
        });

        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['inventario_hamaca_id']);
            $table->dropForeign(['usuario_id']);

            $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });

        Schema::table('detalle_facturas', function (Blueprint $table) {
            $table->dropForeign(['factura_id']);
            $table->dropForeign(['inventario_hamaca_id']);
            $table->dropForeign(['hamaca_id']);
            $table->dropForeign(['usuario_id']);
            $table->dropForeign(['ubicacion_id']);

            $table->foreign('factura_id')->references('id')->on('facturas')->cascadeOnDelete();
            $table->foreign('inventario_hamaca_id')->references('id')->on('inventario_hamacas')->cascadeOnDelete();
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->cascadeOnDelete();
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('ubicacion_id')->references('id')->on('ubicaciones')->cascadeOnDelete();
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['vendedor_id']);
            $table->foreign('vendedor_id')->references('id')->on('usuarios')->cascadeOnDelete();
        });
    }
};
