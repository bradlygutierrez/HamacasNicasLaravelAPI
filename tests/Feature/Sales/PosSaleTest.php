<?php

namespace Tests\Feature\Sales;

use App\Models\Usuario;
use App\Models\Hamaca;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
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
            'aplica_iva' => true,
            'aplica_ir' => true,
            'items' => [
                [
                    'inventario_hamaca_id' => $seed['inventario_id'],
                    'cantidad' => 2,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', '3000.00')
            ->assertJsonPath('data.descuento', '100.00')
            ->assertJsonPath('data.monto_iva', '435.00')
            ->assertJsonPath('data.monto_ir', '58.00')
            ->assertJsonPath('data.total', '3277.00')
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

    public function test_pos_invoice_keeps_hamaca_name_between_101_and_150_characters(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(2, $vendor);
        $name = str_repeat('H', 120);
        Hamaca::findOrFail($seed['hamaca_id'])->update(['nombre' => $name]);
        Sanctum::actingAs($vendor);

        $response = $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos', 'nombre_cliente' => 'Cliente nombre largo', 'metodo_pago' => 'efectivo',
            'items' => [['inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 1]],
        ])->assertCreated();

        $detailId = $response->json('data.detalles.0.id');
        $this->assertSame($name, DB::table('detalle_facturas')->where('id', $detailId)->value('hamaca_nombre'));
    }

    public function test_preview_calculates_without_mutating_invoice_stock_or_movements(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);
        $facturasBefore = DB::table('facturas')->count();
        $movimientosBefore = DB::table('movimientos')->count();

        $this->postJson('/api/v1/pos/ventas/calcular', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'aplica_iva' => false,
            'aplica_ir' => true,
            'items' => [['inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 2]],
        ])->assertOk()
            ->assertJsonPath('data.subtotal', '2000.00')
            ->assertJsonPath('data.monto_iva', '0.00')
            ->assertJsonPath('data.monto_ir', '40.00')
            ->assertJsonPath('data.total', '1960.00');

        $this->assertSame($facturasBefore, DB::table('facturas')->count());
        $this->assertSame($movimientosBefore, DB::table('movimientos')->count());
        $this->assertDatabaseHas('inventario_hamacas', ['id' => $seed['inventario_id'], 'cantidad' => 5]);
    }

    public function test_pos_sale_uses_configured_iva_rate_and_can_disable_iva(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Config::set('comercial.iva_rate', '20');
        Sanctum::actingAs($vendedor);

        $response = $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Consumidor final',
            'metodo_pago' => 'efectivo',
            'aplica_iva' => true,
            'aplica_ir' => false,
            'items' => [['inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 1]],
        ])->assertCreated();

        $response->assertJsonPath('data.tasa_iva', '0.2000')
            ->assertJsonPath('data.monto_iva', '200.00')
            ->assertJsonPath('data.monto_ir', '0.00');
    }

    public function test_socio_and_almacenista_cannot_use_direct_sales(): void
    {
        foreach (['socio', 'almacenista'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->postJson('/api/v1/pos/ventas', [
                'canal' => 'pos',
                'nombre_cliente' => 'Consumidor final',
                'metodo_pago' => 'efectivo',
                'items' => [['inventario_hamaca_id' => 1, 'cantidad' => 1]],
            ])->assertForbidden();
        }
    }

    public function test_socio_can_list_invoices_but_almacenista_cannot(): void
    {
        Sanctum::actingAs($this->userWithRole('socio'));
        $this->getJson('/api/v1/facturas')->assertOk();

        Sanctum::actingAs($this->userWithRole('almacenista'));
        $this->getJson('/api/v1/facturas')->assertForbidden();
    }

    public function test_invoice_and_inventory_per_page_are_clamped_between_one_and_one_hundred(): void
    {
        Sanctum::actingAs($this->userWithRole('socio'));
        $this->getJson('/api/v1/facturas?per_page=0')->assertOk()->assertJsonPath('meta.per_page', 1);

        $this->inventoryFixture(2);
        $response = $this->getJson('/api/v1/inventario-hamacas?per_page=1000')->assertOk();
        $this->assertContains(100, (array) $response->json('meta.per_page'));
    }

    public function test_invoice_index_filters_paginates_and_show_loads_details(): void
    {
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);
        $sale = $this->postJson('/api/v1/pos/ventas', [
            'canal' => 'pos',
            'nombre_cliente' => 'Cliente filtrable',
            'metodo_pago' => 'efectivo',
            'items' => [['inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 1]],
        ])->assertCreated();

        $invoiceId = $sale->json('data.id');
        $this->getJson('/api/v1/facturas?search=filtrable&origen=venta_directa&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoiceId)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.origen', 'venta_directa');
        $this->getJson('/api/v1/facturas/'.$invoiceId)
            ->assertOk()
            ->assertJsonStructure(['data' => ['detalles', 'servicios', 'numero', 'canal', 'metodo_pago']]);
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
            'cantidad' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($colorIds as $colorId) {
            DB::table('hamaca_color')->insert([
                'hamaca_id' => $hamacaId,
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
