<?php

namespace Tests\Feature\Inventory;

use App\Models\Movimiento;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class InventoryOperationsTest extends TestCase
{
    use BuildsInventoryFixtures;
    use DatabaseTransactions;

    public function test_entrada_creates_stock_and_movement_atomically(): void
    {
        $operador = $this->userWithRole('almacenista');
        $propietario = $this->userWithRole('socio');
        $catalog = $this->catalogFixture();
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/inventario/entradas', [
            'hamaca_id' => $catalog['hamaca_id'],
            'usuario_id' => $propietario->id,
            'ubicacion_id' => $catalog['ubicacion_origen_id'],
            'cantidad' => 5,
            'fecha' => '2026-09-15',
        ])->assertCreated();

        $inventarioId = DB::table('inventario_hamacas')
            ->where('hamaca_id', $catalog['hamaca_id'])
            ->where('usuario_id', $propietario->id)
            ->where('ubicacion_id', $catalog['ubicacion_origen_id'])
            ->value('id');

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $inventarioId,
            'cantidad' => 5,
        ]);

        $this->assertDatabaseHas('movimientos', [
            'inventario_hamaca_id' => $inventarioId,
            'usuario_id' => $operador->id,
            'ubicacion_destino_id' => $catalog['ubicacion_origen_id'],
            'tipo' => 'entrada',
            'cantidad' => 5,
        ]);
    }

    public function test_salida_reduces_stock_and_creates_operator_movement_atomically(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/inventario/salidas', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'cantidad' => 4,
            'fecha' => '2026-09-15',
        ])->assertOk();

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 6,
        ]);

        $this->assertDatabaseHas('movimientos', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'usuario_id' => $operador->id,
            'ubicacion_origen_id' => $seed['ubicacion_origen_id'],
            'tipo' => 'salida',
            'cantidad' => 4,
        ]);
    }

    public function test_salida_with_insufficient_stock_returns_conflict_and_keeps_stock(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(3);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/inventario/salidas', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'cantidad' => 4,
        ])->assertStatus(409)
            ->assertJsonPath('errors.cantidad.0', 'Solo hay 3 unidades disponibles.');

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 3,
        ]);
    }

    public function test_transferencia_parcial_creates_or_increments_destination_without_moving_source_row(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/inventario/transferencias', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'ubicacion_destino_id' => $seed['ubicacion_destino_id'],
            'cantidad' => 3,
        ])->assertOk();

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'ubicacion_id' => $seed['ubicacion_origen_id'],
            'cantidad' => 7,
        ]);

        $this->assertDatabaseHas('inventario_hamacas', [
            'hamaca_id' => $seed['hamaca_id'],
            'usuario_id' => $seed['propietario_id'],
            'ubicacion_id' => $seed['ubicacion_destino_id'],
            'cantidad' => 3,
        ]);

        $this->assertDatabaseHas('movimientos', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'usuario_id' => $operador->id,
            'ubicacion_origen_id' => $seed['ubicacion_origen_id'],
            'ubicacion_destino_id' => $seed['ubicacion_destino_id'],
            'tipo' => 'transferencia',
            'cantidad' => 3,
        ]);
    }

    public function test_transferencia_total_leaves_source_at_zero_and_destination_with_quantity(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(5);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/inventario/transferencias', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'ubicacion_destino_id' => $seed['ubicacion_destino_id'],
            'cantidad' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 0,
        ]);

        $this->assertDatabaseHas('inventario_hamacas', [
            'hamaca_id' => $seed['hamaca_id'],
            'usuario_id' => $seed['propietario_id'],
            'ubicacion_id' => $seed['ubicacion_destino_id'],
            'cantidad' => 5,
        ]);
    }

    public function test_transferencia_increments_existing_destination_inventory(): void
    {
        $operador = $this->userWithRole('almacenista');
        $catalog = $this->catalogFixture();
        $propietario = $this->userWithRole('socio');
        $source = $this->inventoryFixture(10, $propietario, $catalog, $catalog['ubicacion_origen_id']);
        $destination = $this->inventoryFixture(2, $propietario, $catalog, $catalog['ubicacion_destino_id']);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/inventario/transferencias', [
            'inventario_hamaca_id' => $source['inventario_id'],
            'ubicacion_destino_id' => $catalog['ubicacion_destino_id'],
            'cantidad' => 3,
        ])->assertOk();

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $source['inventario_id'],
            'cantidad' => 7,
        ]);

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $destination['inventario_id'],
            'cantidad' => 5,
        ]);
    }

    public function test_inventory_operation_rolls_back_if_movement_creation_fails(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        Sanctum::actingAs($operador);

        Movimiento::creating(function (): void {
            throw new RuntimeException('Movimiento fallido');
        });

        try {
            $this->postJson('/api/v1/inventario/salidas', [
                'inventario_hamaca_id' => $seed['inventario_id'],
                'cantidad' => 4,
            ])->assertStatus(500);
        } finally {
            Movimiento::flushEventListeners();
            Movimiento::boot();
        }

        $this->assertDatabaseHas('inventario_hamacas', [
            'id' => $seed['inventario_id'],
            'cantidad' => 10,
        ]);
    }

    public function test_legacy_transfer_endpoint_is_unavailable(): void
    {
        $operator = $this->userWithRole('almacenista');
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/inventario-hamacas/transfer', [
            'inventario_hamaca_id' => 1,
            'cantidad' => 1,
            'ubicacion_destino_id' => 2,
        ])->assertStatus(405);
    }

    public function test_public_movimiento_mutation_routes_are_not_available(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/movimientos', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'tipo' => 'entrada',
            'cantidad' => 1,
        ])->assertStatus(405);
    }
}
