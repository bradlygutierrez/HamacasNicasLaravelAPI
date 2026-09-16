<?php

namespace Tests\Feature\Dashboard;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use BuildsInventoryFixtures;
    use DatabaseTransactions;

    public function test_dashboard_summary_uses_real_inventory_and_movements(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        Sanctum::actingAs($operador);
        $before = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json('data');

        $this->postJson('/api/v1/inventario/entradas', [
            'hamaca_variante_id' => $seed['variante_id'],
            'usuario_id' => $seed['propietario_id'],
            'ubicacion_id' => $seed['ubicacion_origen_id'],
            'cantidad' => 2,
        ])->assertCreated();

        $this->postJson('/api/v1/inventario/salidas', [
            'inventario_hamaca_id' => $seed['inventario_id'],
            'cantidad' => 3,
        ])->assertOk();

        $after = $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->json('data');

        $this->assertSame($before['existencia_actual_total'] - 1, $after['existencia_actual_total']);
        $this->assertSame($before['entradas_mes'] + 2, $after['entradas_mes']);
        $this->assertSame($before['salidas_mes'] + 3, $after['salidas_mes']);
        $this->assertSame($before['unidades_totales'] - 1, $after['unidades_totales']);
        $this->assertNotEmpty($after['stock_por_categoria']);
        $this->assertNotEmpty($after['entradas_salidas_por_categoria']);
    }

    public function test_dashboard_summary_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }
}
