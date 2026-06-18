<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $usuarios = [
            'admin' => DB::table('usuarios')->insertGetId([
                'nombre' => 'Admin',
                'correo' => 'admin@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'admin',
                'state' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'vendedor' => DB::table('usuarios')->insertGetId([
                'nombre' => 'Vendedor',
                'correo' => 'vendedor@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'vendedor',
                'state' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'almacenista' => DB::table('usuarios')->insertGetId([
                'nombre' => 'Almacenista',
                'correo' => 'almacenista@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'almacenista',
                'state' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            'socio' => DB::table('usuarios')->insertGetId([
                'nombre' => 'Socio Demo',
                'correo' => 'socio@example.com',
                'password' => Hash::make('secret123'),
                'rol' => 'socio',
                'state' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]),
        ];

        DB::table('categorias')->insert([
            [
                'nombre' => 'Familiar',
                'descripcion' => 'Modelo familiar',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Silla',
                'descripcion' => 'Modelo silla',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('tamanos')->insert([
            [
                'nombre' => 'Grande',
                'descripcion' => 'Tamaño grande',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Mediana',
                'descripcion' => 'Tamaño mediano',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('ubicaciones')->insert([
            [
                'nombre' => 'Mercado',
                'descripcion' => 'Sucursal mercado',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'Bodega',
                'descripcion' => 'Bodega principal',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('colores')->insert([
            ['nombre' => 'Blanco', 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Azul', 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Rojo', 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Verde', 'created_at' => $now, 'updated_at' => $now],
            ['nombre' => 'Amarillo', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $categoriaId = DB::table('categorias')->where('nombre', 'Familiar')->value('id');
        $tamanoId = DB::table('tamanos')->where('nombre', 'Grande')->value('id');
        $ubicacionId = DB::table('ubicaciones')->where('nombre', 'Mercado')->value('id');
        $colorIds = DB::table('colores')->whereIn('nombre', ['Blanco', 'Azul', 'Rojo', 'Verde'])->pluck('id')->all();

        $hamacaId = DB::table('hamacas')->insertGetId([
            'nombre' => 'Familiar Base',
            'descripcion' => 'Modelo base familiar',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $inventarioId = DB::table('inventario_hamacas')->insertGetId([
            'hamaca_id' => $hamacaId,
            'usuario_id' => $usuarios['socio'],
            'ubicacion_id' => $ubicacionId,
            'composicion_clave' => hash('sha256', implode(',', $colorIds)),
            'cantidad' => 5,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('inventario_hamaca_color')->insert(collect($colorIds)->map(fn (int $colorId) => [
            'inventario_hamaca_id' => $inventarioId,
            'color_id' => $colorId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        DB::table('clientes')->insert([
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

        DB::table('facturas')->insert([
            'numero' => 'FAC-000001',
            'cliente_id' => null,
            'vendedor_id' => $usuarios['vendedor'],
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'ruc' => null,
            'direccion' => null,
            'telefono' => null,
            'correo' => null,
            'metodo_pago' => 'efectivo',
            'subtotal' => 1500,
            'descuento' => 0,
            'tasa_iva' => 0.15,
            'monto_iva' => 225,
            'aplica_ir' => false,
            'tasa_ir' => 0.02,
            'monto_ir' => 0,
            'total' => 1725,
            'fecha' => $now,
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
            ],
            'vendedor' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver'],
                'ventas-pos' => ['ver', 'crear'],
                'facturas' => ['ver'],
            ],
            'almacenista' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver', 'crear', 'editar'],
            ],
            'socio' => [
                'dashboard' => ['ver'],
                'inventario' => ['ver'],
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
}
