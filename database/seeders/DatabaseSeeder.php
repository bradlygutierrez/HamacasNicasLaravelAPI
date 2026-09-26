<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('Seeding is only allowed in local or testing environments.');
            return;
        }

        $now = now();

        $usuarios = [
            'admin' => $this->seedUsuario([
                'nombre' => 'Admin',
                'correo' => 'admin@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'admin',
                'state' => true,
            ], $now),
            'vendedor' => $this->seedUsuario([
                'nombre' => 'Vendedor',
                'correo' => 'vendedor@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'vendedor',
                'state' => true,
            ], $now),
            'almacenista' => $this->seedUsuario([
                'nombre' => 'Almacenista',
                'correo' => 'almacenista@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'almacenista',
                'state' => true,
            ], $now),
            'socio' => $this->seedUsuario([
                'nombre' => 'Socio Demo',
                'correo' => 'socio@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'socio',
                'state' => true,
            ], $now),
        ];

        foreach ([
            [
                'nombre' => 'Familiar',
                'descripcion' => 'Modelo familiar',
            ],
            [
                'nombre' => 'Silla',
                'descripcion' => 'Modelo silla',
            ],
        ] as $categoria) {
            DB::table('categorias')->updateOrInsert(['nombre' => $categoria['nombre']], [
                ...$categoria, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            [
                'nombre' => 'Grande',
                'descripcion' => 'Tamaño grande',
            ],
            [
                'nombre' => 'Mediana',
                'descripcion' => 'Tamaño mediano',
            ],
        ] as $tamano) {
            DB::table('tamanos')->updateOrInsert(['nombre' => $tamano['nombre']], [
                ...$tamano, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ([
            [
                'nombre' => 'Mercado',
                'descripcion' => 'Sucursal mercado',
            ],
            [
                'nombre' => 'Bodega',
                'descripcion' => 'Bodega principal',
            ],
        ] as $ubicacion) {
            DB::table('ubicaciones')->updateOrInsert(['nombre' => $ubicacion['nombre']], [
                ...$ubicacion, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach (['Blanco', 'Azul', 'Rojo', 'Verde', 'Amarillo'] as $color) {
            DB::table('colores')->updateOrInsert(['nombre' => $color], [
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $categoriaId = DB::table('categorias')->where('nombre', 'Familiar')->value('id');
        $tamanoId = DB::table('tamanos')->where('nombre', 'Grande')->value('id');
        $ubicacionId = DB::table('ubicaciones')->where('nombre', 'Mercado')->value('id');
        $colorIds = DB::table('colores')->whereIn('nombre', ['Blanco', 'Azul', 'Rojo', 'Verde'])->pluck('id')->all();
        $colorIds = array_map('intval', $colorIds);
        sort($colorIds);

        $hamacaNombre = 'Familiar Grande - Blanco / Azul / Rojo / Verde';
        $hamacaData = [
            'descripcion' => 'Modelo base familiar',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $hamacaId = DB::table('hamacas')->where('nombre', $hamacaNombre)->whereNull('deleted_at')->value('id');
        if ($hamacaId) {
            DB::table('hamacas')->where('id', $hamacaId)->update($hamacaData);
        } else {
            $hamacaId = DB::table('hamacas')->insertGetId(['nombre' => $hamacaNombre, ...$hamacaData]);
        }
        $hamacaId = (int) $hamacaId;

        DB::table('hamaca_color')->insertOrIgnore(
            collect($colorIds)->map(fn (int $colorId) => [
                'hamaca_id' => $hamacaId,
                'color_id' => $colorId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        DB::table('inventario_hamacas')->updateOrInsert([
            'hamaca_id' => $hamacaId,
            'usuario_id' => $usuarios['socio'],
            'ubicacion_id' => $ubicacionId,
        ], [
            'cantidad' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('clientes')->updateOrInsert(['correo' => 'cliente@example.com'], [
            'nombre' => 'Cliente Demo',
            'ruc' => 'J0310000000001',
            'direccion' => 'Managua',
            'telefono' => '8888-8888',
            'correo' => 'cliente@example.com',
            'password' => null,
            'state' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $pantallas = [
            ['nombre' => 'Dashboard', 'slug' => 'dashboard', 'ruta' => '/dashboard', 'icono' => 'layout-dashboard', 'orden' => 10],
            ['nombre' => 'Inventario', 'slug' => 'inventario', 'ruta' => '/inventario', 'icono' => 'boxes', 'orden' => 20],
            ['nombre' => 'Ventas POS', 'slug' => 'ventas-pos', 'ruta' => '/ventas', 'icono' => 'shopping-cart', 'orden' => 30],
            ['nombre' => 'Facturas', 'slug' => 'facturas', 'ruta' => '/facturas', 'icono' => 'receipt', 'orden' => 40],
            ['nombre' => 'Usuarios', 'slug' => 'usuarios', 'ruta' => '/usuarios', 'icono' => 'users', 'orden' => 50],
            ['nombre' => 'Permisos', 'slug' => 'permisos', 'ruta' => '/permisos', 'icono' => 'shield-check', 'orden' => 60],
            ['nombre' => 'Materiales', 'slug' => 'materiales', 'ruta' => '/materiales', 'icono' => 'boxes', 'orden' => 70],
            ['nombre' => 'Procesos de producción', 'slug' => 'procesos-produccion', 'ruta' => '/procesos-produccion', 'icono' => 'settings', 'orden' => 80],
            ['nombre' => 'Servicios adicionales', 'slug' => 'servicios-adicionales', 'ruta' => '/servicios-adicionales', 'icono' => 'package', 'orden' => 90],
            ['nombre' => 'Fórmulas', 'slug' => 'formulas', 'ruta' => '/formulas', 'icono' => 'flask-conical', 'orden' => 85],
            ['nombre' => 'Proformas', 'slug' => 'proformas', 'ruta' => '/proformas', 'icono' => 'file-text', 'orden' => 95],
            ['nombre' => 'Pedidos', 'slug' => 'pedidos', 'ruta' => '/pedidos', 'icono' => 'clipboard-list', 'orden' => 96],
        ];

        foreach ($pantallas as $pantalla) {
            DB::table('pantallas')->updateOrInsert(
                ['slug' => $pantalla['slug']],
                [
                    ...$pantalla,
                    'descripcion' => null,
                    'state' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $permisos = [
            ['nombre' => 'Ver', 'slug' => 'ver', 'descripcion' => 'Permite consultar una pantalla o recurso.'],
            ['nombre' => 'Crear', 'slug' => 'crear', 'descripcion' => 'Permite crear registros.'],
            ['nombre' => 'Editar', 'slug' => 'editar', 'descripcion' => 'Permite actualizar registros.'],
            ['nombre' => 'Eliminar', 'slug' => 'eliminar', 'descripcion' => 'Permite eliminar o desactivar registros.'],
        ];

        foreach ($permisos as $permiso) {
            DB::table('permisos')->updateOrInsert(
                ['slug' => $permiso['slug']],
                [
                    ...$permiso,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $pantallaIds = DB::table('pantallas')->pluck('id', 'slug');
        $permisoIds = DB::table('permisos')->pluck('id', 'slug');

        $accesos = [
            'admin' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver', 'crear', 'editar', 'eliminar'],
                'ventas-pos' => ['ver', 'crear'],
                'facturas' => ['ver'],
                'usuarios' => ['ver', 'crear', 'editar', 'eliminar'],
                'permisos' => ['ver', 'crear', 'editar', 'eliminar'],
                'materiales' => ['ver', 'crear', 'editar', 'eliminar'],
                'procesos-produccion' => ['ver', 'crear', 'editar', 'eliminar'],
                'servicios-adicionales' => ['ver', 'crear', 'editar', 'eliminar'],
                'formulas' => ['ver', 'crear', 'editar', 'eliminar'],
                'proformas' => ['ver', 'crear', 'editar'],
                'pedidos' => ['ver', 'crear', 'editar'],
            ],
            'vendedor' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver'],
                'ventas-pos' => ['ver', 'crear'],
                'facturas' => ['ver'],
                'servicios-adicionales' => ['ver'],
                'proformas' => ['ver', 'crear', 'editar'],
                'pedidos' => ['ver', 'crear'],
            ],
            'almacenista' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver', 'crear', 'editar'],
                'materiales' => ['ver'],
                'procesos-produccion' => ['ver'],
                'servicios-adicionales' => ['ver'],
                'formulas' => ['ver'],
                'pedidos' => ['ver', 'editar'],
            ],
            'socio' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver'],
                'materiales' => ['ver'],
                'pedidos' => ['ver'],
                'procesos-produccion' => ['ver'],
                'formulas' => ['ver'],
                'proformas' => ['ver'],
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
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }

        $this->command?->info('Credenciales de desarrollo: admin@example.com / secret123');
    }

    /** @param array<string, mixed> $attributes */
    private function seedUsuario(array $attributes, mixed $now): int
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => $attributes['correo']],
            [...$attributes, 'created_at' => $now, 'updated_at' => $now],
        );

        return (int) DB::table('usuarios')->where('correo', $attributes['correo'])->value('id');
    }
}
