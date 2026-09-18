<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::transaction(function () use ($now): void {
            DB::table('pantallas')->updateOrInsert(
                ['slug' => 'formulas'],
                [
                    'nombre' => 'Fórmulas',
                    'ruta' => '/formulas',
                    'icono' => 'flask-conical',
                    'orden' => 85,
                    'descripcion' => 'Recetas y costos de producción.',
                    'state' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $permissions = [
                ['nombre' => 'Ver', 'slug' => 'ver', 'descripcion' => 'Permite consultar una pantalla o recurso.'],
                ['nombre' => 'Crear', 'slug' => 'crear', 'descripcion' => 'Permite crear registros.'],
                ['nombre' => 'Editar', 'slug' => 'editar', 'descripcion' => 'Permite actualizar registros.'],
                ['nombre' => 'Eliminar', 'slug' => 'eliminar', 'descripcion' => 'Permite eliminar o desactivar registros.'],
            ];

            foreach ($permissions as $permission) {
                DB::table('permisos')->updateOrInsert(
                    ['slug' => $permission['slug']],
                    [...$permission, 'created_at' => $now, 'updated_at' => $now]
                );
            }

            $screenId = DB::table('pantallas')->where('slug', 'formulas')->value('id');
            $permissionIds = DB::table('permisos')->whereIn('slug', ['ver', 'crear', 'editar', 'eliminar'])->pluck('id', 'slug');
            $access = [
                'admin' => ['ver', 'crear', 'editar', 'eliminar'],
                'socio' => ['ver'],
                'almacenista' => ['ver'],
            ];

            foreach ($access as $role => $slugs) {
                foreach ($slugs as $slug) {
                    DB::table('pantalla_permiso_roles')->updateOrInsert(
                        [
                            'pantalla_id' => $screenId,
                            'permiso_id' => $permissionIds[$slug],
                            'rol' => $role,
                        ],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        });
    }

    public function down(): void
    {
        // Access configuration is intentionally retained on rollback.
    }
};
