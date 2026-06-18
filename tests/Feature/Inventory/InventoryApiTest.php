<?php

namespace Tests\Feature\Inventory;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_groups_by_same_model_owner_location_and_colors(): void
    {
        $admin = $this->seedAdmin();
        $seed = $this->seedCatalogAndColors();
        $initialInventoryCount = DB::table('inventario_hamacas')->count();
        Sanctum::actingAs($admin);

        $payload = [
            'hamaca_id' => $seed['hamaca_id'],
            'usuario_id' => $seed['usuario_id'],
            'ubicacion_id' => $seed['ubicacion_id'],
            'color_ids' => [$seed['color_a'], $seed['color_b'], $seed['color_c'], $seed['color_d']],
            'cantidad' => 2,
        ];

        $this->postJson('/api/v1/inventario-hamacas', $payload)
            ->assertCreated();

        $this->postJson('/api/v1/inventario-hamacas', array_merge($payload, [
            'color_ids' => [$seed['color_d'], $seed['color_c'], $seed['color_b'], $seed['color_a']],
            'cantidad' => 3,
        ]))->assertCreated();

        $this->assertDatabaseCount('inventario_hamacas', $initialInventoryCount + 1);
        $this->assertDatabaseHas('inventario_hamacas', [
            'hamaca_id' => $seed['hamaca_id'],
            'usuario_id' => $seed['usuario_id'],
            'ubicacion_id' => $seed['ubicacion_id'],
            'cantidad' => 5,
        ]);

        $inventarioId = DB::table('inventario_hamacas')
            ->where('hamaca_id', $seed['hamaca_id'])
            ->where('usuario_id', $seed['usuario_id'])
            ->where('ubicacion_id', $seed['ubicacion_id'])
            ->value('id');
        $this->assertSame(4, DB::table('inventario_hamaca_color')->where('inventario_hamaca_id', $inventarioId)->count());
    }

    private function seedAdmin(): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => 'admin@example.com'],
            [
                'nombre' => 'Admin',
                'password' => Hash::make('secret123'),
                'rol' => 'admin',
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return Usuario::where('correo', 'admin@example.com')->firstOrFail();
    }

    private function seedCatalogAndColors(): array
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Familiar',
            'descripcion' => 'Modelo familiar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tamanoId = DB::table('tamanos')->insertGetId([
            'nombre' => 'Grande',
            'descripcion' => 'Grande',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ubicacionId = DB::table('ubicaciones')->insertGetId([
            'nombre' => 'Mercado',
            'descripcion' => 'Sucursal mercado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('usuarios')->updateOrInsert(
            ['correo' => 'jacksa@example.com'],
            [
                'nombre' => 'Jacksa',
                'password' => Hash::make('secret123'),
                'rol' => 'socio',
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $usuarioId = DB::table('usuarios')->where('correo', 'jacksa@example.com')->value('id');

        $colors = [];
        foreach (['Blanco', 'Azul', 'Rojo', 'Verde'] as $color) {
            DB::table('colores')->updateOrInsert(
                ['nombre' => $color],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $colors[] = DB::table('colores')->where('nombre', $color)->value('id');
        }

        $hamacaId = DB::table('hamacas')->insertGetId([
            'nombre' => 'Familiar Blanca',
            'descripcion' => 'Modelo de prueba',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'hamaca_id' => $hamacaId,
            'usuario_id' => $usuarioId,
            'ubicacion_id' => $ubicacionId,
            'color_a' => $colors[0],
            'color_b' => $colors[1],
            'color_c' => $colors[2],
            'color_d' => $colors[3],
        ];
    }
}
