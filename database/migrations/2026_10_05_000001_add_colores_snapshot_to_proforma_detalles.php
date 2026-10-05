<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_detalles', function (Blueprint $table): void {
            $table->text('colores_snapshot')->nullable()->after('hamaca_descripcion_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_detalles', function (Blueprint $table): void {
            $table->dropColumn('colores_snapshot');
        });
    }
};
