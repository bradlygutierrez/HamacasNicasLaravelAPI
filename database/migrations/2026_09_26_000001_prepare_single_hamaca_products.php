<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hamaca_color', function (Blueprint $table): void {
            $table->integer('hamaca_id');
            $table->integer('color_id');
            $table->timestamps();
            $table->primary(['hamaca_id', 'color_id']);
            $table->foreign('hamaca_id')->references('id')->on('hamacas')->cascadeOnDelete();
            $table->foreign('color_id')->references('id')->on('colores')->cascadeOnDelete();
        });

        Schema::create('hamaca_variant_promotion_map', function (Blueprint $table): void {
            $table->integer('hamaca_variante_id')->primary();
            $table->integer('original_hamaca_id');
            $table->integer('promoted_hamaca_id')->unique();
            $table->foreign('hamaca_variante_id')->references('id')->on('hamaca_variantes')->restrictOnDelete();
            $table->foreign('original_hamaca_id')->references('id')->on('hamacas')->restrictOnDelete();
            $table->foreign('promoted_hamaca_id')->references('id')->on('hamacas')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hamaca_variant_promotion_map');
        Schema::dropIfExists('hamaca_color');
    }
};
