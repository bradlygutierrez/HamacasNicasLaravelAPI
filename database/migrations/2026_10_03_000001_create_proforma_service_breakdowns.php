<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_servicio_desgloses', function (Blueprint $table): void {
            $table->integer('id', true);
            $table->integer('proforma_servicio_id')->nullable();
            $table->integer('proforma_detalle_servicio_id')->nullable();
            $table->string('descripcion', 255);
            $table->decimal('monto', 14, 2);
            $table->unsignedInteger('orden');
            $table->timestamps();
            $table->foreign('proforma_servicio_id')->references('id')->on('proforma_servicios')->cascadeOnDelete();
            $table->foreign('proforma_detalle_servicio_id')->references('id')->on('proforma_detalle_servicios')->cascadeOnDelete();
            $table->index(['proforma_servicio_id', 'orden']);
            $table->index(['proforma_detalle_servicio_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_servicio_desgloses');
    }
};
