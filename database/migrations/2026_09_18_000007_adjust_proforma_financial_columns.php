<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proformas')) Schema::table('proformas', function (Blueprint $table): void {
            foreach (['descuento_global', 'descuento_lineas', 'descuento_total'] as $column) if (!Schema::hasColumn('proformas', $column)) $table->decimal($column, 14, 2)->default(0);
        });
        foreach (['proforma_detalle_servicios', 'proforma_servicios'] as $name) if (Schema::hasTable($name)) Schema::table($name, function (Blueprint $table) use ($name): void {
            if (!Schema::hasColumn($name, 'costo_base_unitario_override')) $table->decimal('costo_base_unitario_override', 14, 2)->nullable()->after('descuento');
        });
    }

    public function down(): void {}
};
