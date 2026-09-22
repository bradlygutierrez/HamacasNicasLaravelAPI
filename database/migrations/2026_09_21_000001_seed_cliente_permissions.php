<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::transaction(function () use ($now): void {
            DB::table('pantallas')->updateOrInsert(
                ['slug' => 'clientes'],
                ['nombre' => 'Clientes', 'ruta' => '/clientes', 'icono' => 'users', 'orden' => 94, 'descripcion' => 'Clientes comerciales.', 'state' => true, 'created_at' => $now, 'updated_at' => $now]
            );

            foreach ([
                ['nombre' => 'Ver', 'slug' => 'ver'],
                ['nombre' => 'Crear', 'slug' => 'crear'],
                ['nombre' => 'Editar', 'slug' => 'editar'],
                ['nombre' => 'Eliminar', 'slug' => 'eliminar'],
            ] as $permission) {
                DB::table('permisos')->updateOrInsert(
                    ['slug' => $permission['slug']],
                    [...$permission, 'descripcion' => $permission['nombre'], 'created_at' => $now, 'updated_at' => $now]
                );
            }

            $screenId = DB::table('pantallas')->where('slug', 'clientes')->value('id');
            $permissionIds = DB::table('permisos')->whereIn('slug', ['ver', 'crear', 'editar', 'eliminar'])->pluck('id', 'slug');
            foreach (['admin' => ['ver', 'crear', 'editar', 'eliminar'], 'vendedor' => ['ver', 'crear', 'editar'], 'socio' => ['ver']] as $role => $slugs) {
                foreach ($slugs as $slug) {
                    DB::table('pantalla_permiso_roles')->updateOrInsert(
                        ['pantalla_id' => $screenId, 'permiso_id' => $permissionIds[$slug], 'rol' => $role],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        });
    }

    public function down(): void {}
};
