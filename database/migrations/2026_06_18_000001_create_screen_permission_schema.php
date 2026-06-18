<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pantallas', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->string('ruta', 255)->nullable();
            $table->string('icono', 100)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('state')->default(true);
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('nombre', 100);
            $table->string('slug', 100)->unique();
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('pantalla_permiso_roles', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('pantalla_id');
            $table->integer('permiso_id');
            $table->enum('rol', ['admin', 'vendedor', 'almacenista', 'socio']);
            $table->timestamps();

            $table->foreign('pantalla_id')
                ->references('id')
                ->on('pantallas')
                ->cascadeOnDelete();

            $table->foreign('permiso_id')
                ->references('id')
                ->on('permisos')
                ->cascadeOnDelete();

            $table->unique(['pantalla_id', 'permiso_id', 'rol'], 'pantalla_permiso_rol_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pantalla_permiso_roles');
        Schema::dropIfExists('permisos');
        Schema::dropIfExists('pantallas');
    }
};
