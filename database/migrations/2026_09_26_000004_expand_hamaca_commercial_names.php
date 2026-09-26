<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->expandIfNeeded('hamacas', 'nombre');
        $this->expandIfNeeded('detalle_facturas', 'hamaca_nombre');
    }

    public function down(): void
    {
        throw new RuntimeException('Ampliar nombres comerciales puede permitir datos que no caben en 100 caracteres; el rollback es irreversible automáticamente.');
    }

    private function expandIfNeeded(string $tableName, string $columnName): void
    {
        $column = collect(Schema::getColumns($tableName))->firstWhere('name', $columnName);
        if (!$column || (int) ($column['length'] ?? 0) >= 150) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
            $table->string($columnName, 150)->change();
        });
    }
};
