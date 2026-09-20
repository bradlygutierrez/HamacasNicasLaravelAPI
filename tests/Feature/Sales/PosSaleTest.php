<?php

namespace Tests\Feature\Sales;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use BuildsInventoryFixtures;
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
        ])->assertStatus(409)
            ->assertJsonPath('errors.items.0', 'Solo hay 4 unidades disponibles.');

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 4,
        ]);
    }

    public function test_pos_sale_has_only_a_direct_sale_exit(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);

        $response = $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'items' => [['inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 2]],
        ])->assertCreated();

        $invoiceId = $response->json('data.id');
        $this->assertDatabaseHas('inventario_hamacas', ['id' => $seed['inventario_id'], 'cantidad' => 3]);
        $this->assertDatabaseHas('facturas', ['id' => $invoiceId, 'origen' => 'venta_directa', 'pedido_id' => null]);
        $this->assertSame(1, DB::table('movimientos')->where('factura_id', $invoiceId)->where('tipo', 'salida')->count());
        $this->assertSame(0, DB::table('movimientos')->where('factura_id', $invoiceId)->where('tipo', 'entrada')->count());
        $this->assertSame(0, DB::table('movimientos')->where('factura_id', $invoiceId)->whereNotNull('pedido_id')->count());
    }

    public function test_pos_sale_rejects_repeated_inventory_that_exceeds_total_stock(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);
        $facturasBefore = DB::table('facturas')->count();

        $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'items' => [
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 4,
                ],
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 4,
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('items.1.inventario_hamaca_id');

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 5,
        ]);
    }

    public function test_pos_sale_rejects_discount_greater_than_subtotal(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);
        $facturasBefore = DB::table('facturas')->count();

        $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'descuento' => 5000,
            'items' => [
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 1,
                ],
            ],
        ])->assertStatus(409)
            ->assertJsonPath('errors.descuento.0', 'El descuento no puede ser mayor que el subtotal.');

        $this->assertSame($facturasBefore, DB::table('facturas')->count());
    }

    public function test_invoice_number_uses_created_invoice_id(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);

        $response = $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'items' => [
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 1,
                ],
            ],
        ])->assertCreated();

        $id = $response->json('data.id');

        $response->assertJsonPath('data.numero', 'FAC-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT));
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
