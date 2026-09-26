<?php

namespace Tests\Feature\Inventory;

use App\Models\Factura;
use App\Models\InventarioHamaca;
use App\Models\Hamaca;
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

    public function test_archived_inventory_relation_remains_loadable_but_pos_preview_is_rejected(): void
    {
        $vendor = $this->userWithRole('vendedor');
        $seed = $this->inventoryFixture(5);
        Hamaca::findOrFail($seed['hamaca_id'])->delete();
        $inventory = InventarioHamaca::with('hamaca')->findOrFail($seed['inventario_id']);
        $this->assertTrue($inventory->hamaca->trashed());
        Sanctum::actingAs($vendor);
        $this->getJson("/api/v1/inventario-hamacas/{$seed['inventario_id']}")
            ->assertOk()->assertJsonPath('data.hamaca.disponible', false);

        $this->postJson('/api/v1/pos/ventas/calcular', [
            'canal' => 'pos',
            'nombre_cliente' => 'Cliente archivado',
            'metodo_pago' => 'efectivo',
            'items' => [['inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'La hamaca está archivada y no puede venderse.');

        $this->assertDatabaseHas('inventario_hamacas', ['id' => $seed['inventario_id'], 'cantidad' => 5]);
    }

    public function test_archived_hamaca_inventory_can_still_be_transferred_and_removed_manually(): void
    {
        $operator = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(5, $operator);
        Hamaca::findOrFail($seed['hamaca_id'])->delete();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/inventario/transferencias', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'cantidad' => 2,
            'ubicacion_destino_id' => $seed['ubicacion_destino_id'],
        ])->assertOk();

        $this->postJson('/api/v1/inventario/salidas', [
            'inventario_hamaca_id' => $seed['inventario_id'], 'cantidad' => 1,
        ])->assertOk();
    }

    public function test_new_inventory_entry_rejects_archived_hamaca(): void
    {
        $operator = $this->userWithRole('almacenista');
        $seed = $this->catalogFixture();
        Hamaca::findOrFail($seed['hamaca_id'])->delete();
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/inventario/entradas', [
            'hamaca_id' => $seed['hamaca_id'], 'usuario_id' => $operator->id,
            'ubicacion_id' => $seed['ubicacion_origen_id'], 'cantidad' => 1,
        ])->assertUnprocessable();
    }
}
