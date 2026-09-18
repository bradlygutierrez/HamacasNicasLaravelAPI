<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $pantallas = [
            ['nombre' => 'Materiales', 'slug' => 'materiales', 'ruta' => '/materiales', 'icono' => 'boxes', 'orden' => 70],
            ['nombre' => 'Procesos de producción', 'slug' => 'procesos-produccion', 'ruta' => '/procesos-produccion', 'icono' => 'settings', 'orden' => 80],
            ['nombre' => 'Servicios adicionales', 'slug' => 'servicios-adicionales', 'ruta' => '/servicios-adicionales', 'icono' => 'package', 'orden' => 90],
        ];

        $permisos = [
            ['nombre' => 'Ver', 'slug' => 'ver', 'descripcion' => 'Permite consultar una pantalla o recurso.'],
            ['nombre' => 'Crear', 'slug' => 'crear', 'descripcion' => 'Permite crear registros.'],
            ['nombre' => 'Editar', 'slug' => 'editar', 'descripcion' => 'Permite actualizar registros.'],
            ['nombre' => 'Eliminar', 'slug' => 'eliminar', 'descripcion' => 'Permite eliminar o desactivar registros.'],
        ];

        DB::transaction(function () use ($now, $pantallas, $permisos): void {
            foreach ($pantallas as $pantalla) {
                DB::table('pantallas')->updateOrInsert(
                    ['slug' => $pantalla['slug']],
                    [
                        ...$pantalla,
                        'descripcion' => null,
                        'state' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            foreach ($permisos as $permiso) {
                DB::table('permisos')->updateOrInsert(
                    ['slug' => $permiso['slug']],
                    [
                        ...$permiso,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            $pantallaIds = DB::table('pantallas')
                ->whereIn('slug', array_column($pantallas, 'slug'))
                ->pluck('id', 'slug');
            $permisoIds = DB::table('permisos')
                ->whereIn('slug', array_column($permisos, 'slug'))
                ->pluck('id', 'slug');

            $accesos = [
                'admin' => [
                    'materiales' => ['ver', 'crear', 'editar', 'eliminar'],
                    'procesos-produccion' => ['ver', 'crear', 'editar', 'eliminar'],
                    'servicios-adicionales' => ['ver', 'crear', 'editar', 'eliminar'],
                ],
                'vendedor' => [
                    'servicios-adicionales' => ['ver'],
                ],
                'almacenista' => [
                    'materiales' => ['ver'],
                    'procesos-produccion' => ['ver'],
                    'servicios-adicionales' => ['ver'],
                ],
                'socio' => [
                    'materiales' => ['ver'],
                    'procesos-produccion' => ['ver'],
                ],
            ];

            foreach ($accesos as $rol => $pantallasPorRol) {
                foreach ($pantallasPorRol as $pantallaSlug => $permisoSlugs) {
                    foreach ($permisoSlugs as $permisoSlug) {
                        DB::table('pantalla_permiso_roles')->updateOrInsert(
                            [
                                'pantalla_id' => $pantallaIds[$pantallaSlug],
                                'permiso_id' => $permisoIds[$permisoSlug],
                                'rol' => $rol,
                            ],
                            [
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Configuration data is intentionally retained on rollback.
    }
};
