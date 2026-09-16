<?php

namespace Tests\Feature\Inventory;

use App\Models\Factura;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class HistoryProtectionTest extends TestCase
{
    use BuildsInventoryFixtures;
    use DatabaseTransactions;

    public function test_inventory_with_invoice_history_cannot_be_deleted_and_history_remains(): void
    {
        $admin = $this->userWithRole('admin');
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);

        $sale = $this->postJson('/api/v1/pos/ventas', [
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

        $facturaId = $sale->json('data.id');

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/inventario-hamacas/{$seed['inventario_id']}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'El inventario tiene historial y no puede eliminarse físicamente.');

        $this->assertDatabaseHas('detalle_facturas', [
            'factura_id' => $facturaId,
            'inventario_hamaca_id' => $seed['inventario_id'],
        ]);

        $this->assertDatabaseHas('movimientos', [
            'factura_id' => $facturaId,
            'inventario_hamaca_id' => $seed['inventario_id'],
        ]);
    }

    public function test_soft_deleted_hamaca_does_not_break_invoice_detail_snapshot(): void
    {
        $admin = $this->userWithRole('admin');
        $vendedor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($vendedor);

        $sale = $this->postJson('/api/v1/pos/ventas', [
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

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/hamacas/{$seed['hamaca_id']}")->assertOk();

        $factura = Factura::with('detalles.hamaca')->findOrFail($sale->json('data.id'));

        $this->assertSame('Hamaca', substr($factura->detalles->first()->hamaca_nombre, 0, 6));
        $this->assertNotNull($factura->detalles->first()->hamaca);
    }
}
