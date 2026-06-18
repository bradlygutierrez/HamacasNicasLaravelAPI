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

        $this->assertDatabaseCount('inventario_hamacas', 1);
        $this->assertDatabaseHas('inventario_hamacas', [
            'hamaca_id' => $seed['hamaca_id'],
            'usuario_id' => $seed['usuario_id'],
            'ubicacion_id' => $seed['ubicacion_id'],
            'cantidad' => 5,
        ]);

        $inventarioId = DB::table('inventario_hamacas')->value('id');
        $this->assertSame(4, DB::table('inventario_hamaca_color')->where('inventario_hamaca_id', $inventarioId)->count());
    }

    private function seedAdmin(): Usuario
    {
        DB::table('usuarios')->insert([
            'nombre' => 'Admin',
            'correo' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'rol' => 'admin',
            'state' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $usuarioId = DB::table('usuarios')->insertGetId([
            'nombre' => 'Jacksa',
            'correo' => 'jacksa@example.com',
            'password' => Hash::make('secret123'),
            'rol' => 'socio',
            'state' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $colors = [];
        foreach (['Blanco', 'Azul', 'Rojo', 'Verde'] as $color) {
            $colors[] = DB::table('colores')->insertGetId([
                'nombre' => $color,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
