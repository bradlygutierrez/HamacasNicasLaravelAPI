<?php

namespace Tests\Feature\Sales;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pos_sale_creates_invoice_and_reduces_stock(): void
    {
        $vendedor = $this->seedVendedor();
        $seed = $this->seedInventory();
        Sanctum::actingAs($vendedor);

        $response = $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'ruc' => null,
            'direccion' => 'Mercado',
            'telefono' => '8888-8888',
            'correo' => 'cliente@example.com',
            'metodo_pago' => 'efectivo',
            'descuento' => 100,
            'aplica_ir' => true,
            'items' => [
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 2,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', 3000)
            ->assertJsonPath('data.descuento', 100)
            ->assertJsonPath('data.monto_iva', 435)
            ->assertJsonPath('data.monto_ir', 58)
            ->assertJsonPath('data.total', 3277)
            ->assertJsonPath('data.detalles.0.cantidad', 2);

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 2,
        ]);

        $this->assertDatabaseHas('movimientos', [
            'cantidad' => 2,
            'tipo' => 'salida',
        ]);
    }

    public function test_pos_sale_rolls_back_on_insufficient_stock(): void
    {
        $vendedor = $this->seedVendedor();
        $seed = $this->seedInventory();
        Sanctum::actingAs($vendedor);

        $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'items' => [
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 999,
                ],
            ],
        ])->assertStatus(500);

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 4,
        ]);
    }

    private function seedVendedor(): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => 'vendedor@example.com'],
            [
                'nombre' => 'Vendedor',
                'password' => Hash::make('secret123'),
                'rol' => 'vendedor',
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return Usuario::where('correo', 'vendedor@example.com')->firstOrFail();
    }

    private function seedInventory(): array
    {
        $categoriaId = DB::table('categorias')->insertGetId([
            'nombre' => 'Familiar',
            'descripcion' => 'Familiar',
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
            'descripcion' => 'Mercado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $socioId = DB::table('usuarios')->insertGetId([
            'nombre' => 'Bradly',
            'correo' => 'bradly@example.com',
            'password' => Hash::make('secret123'),
            'rol' => 'socio',
            'state' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $colorIds = [];
        foreach (['Blanco', 'Azul', 'Rojo', 'Verde'] as $color) {
            DB::table('colores')->updateOrInsert(
                ['nombre' => $color],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $colorIds[] = DB::table('colores')->where('nombre', $color)->value('id');
        }

        $hamacaId = DB::table('hamacas')->insertGetId([
            'nombre' => 'Familiar Blanca',
            'descripcion' => 'Modelo',
            'categoria_id' => $categoriaId,
            'tamano_id' => $tamanoId,
            'precio' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventario_hamacas')->insertGetId([
            'hamaca_id' => $hamacaId,
            'usuario_id' => $socioId,
            'ubicacion_id' => $ubicacionId,
            'composicion_clave' => hash('sha256', implode(',', collect($colorIds)->sort()->values()->all())),
            'cantidad' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($colorIds as $colorId) {
            DB::table('inventario_hamaca_color')->insert([
                'inventario_hamaca_id' => $inventarioId,
                'color_id' => $colorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'inventario_id' => $inventarioId,
        ];
    }
}
