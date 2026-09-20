<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        DB::transaction(function () use ($now): void {
            DB::table('pantallas')->updateOrInsert(['slug' => 'pedidos'], ['nombre' => 'Pedidos', 'ruta' => '/pedidos', 'icono' => 'clipboard-list', 'orden' => 96, 'descripcion' => 'Seguimiento de pedidos.', 'state' => true, 'created_at' => $now, 'updated_at' => $now]);
            foreach ([['nombre' => 'Ver', 'slug' => 'ver'], ['nombre' => 'Crear', 'slug' => 'crear'], ['nombre' => 'Editar', 'slug' => 'editar']] as $permission) DB::table('permisos')->updateOrInsert(['slug' => $permission['slug']], [...$permission, 'descripcion' => $permission['nombre'], 'created_at' => $now, 'updated_at' => $now]);
            $screen = DB::table('pantallas')->where('slug', 'pedidos')->value('id'); $permissions = DB::table('permisos')->whereIn('slug', ['ver', 'crear', 'editar'])->pluck('id', 'slug');
            foreach (['admin' => ['ver', 'crear', 'editar'], 'vendedor' => ['ver', 'crear'], 'socio' => ['ver'], 'almacenista' => ['ver', 'editar']] as $role => $slugs) foreach ($slugs as $slug) DB::table('pantalla_permiso_roles')->updateOrInsert(['pantalla_id' => $screen, 'permiso_id' => $permissions[$slug], 'rol' => $role], ['created_at' => $now, 'updated_at' => $now]);
        });
    }
    public function down(): void {}
};
