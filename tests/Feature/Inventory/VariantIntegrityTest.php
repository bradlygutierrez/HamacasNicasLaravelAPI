<?php

namespace Tests\Feature\Inventory;

use App\Models\HamacaVariante;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class VariantIntegrityTest extends TestCase
{
    use BuildsInventoryFixtures;
    use DatabaseTransactions;

    public function test_same_composition_in_different_order_reuses_existing_variant(): void
    {
        $operador = $this->userWithRole('almacenista');
        $catalog = $this->catalogFixture(['Rojo', 'Azul', 'Verde']);
        Sanctum::actingAs($operador);

        $this->postJson('/api/v1/hamaca-variantes', [
            'hamaca_id' => $catalog['hamaca_id'],
            'color_ids' => array_reverse($catalog['color_ids']),
            'nombre' => 'Misma composicion',
        ])->assertOk();

        $this->assertSame(
            1,
            HamacaVariante::where('hamaca_id', $catalog['hamaca_id'])
                ->where('composicion_clave', $catalog['composition_key'])
                ->count()
        );
    }

    public function test_variant_with_inventory_cannot_change_color_composition(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        $newColor = $this->catalogFixture(['Dorado'])['color_ids'][0];
        Sanctum::actingAs($operador);

        $this->putJson("/api/v1/hamaca-variantes/{$seed['variante_id']}", [
            'color_ids' => [$newColor],
        ])->assertStatus(409)
            ->assertJsonPath(
                'errors.color_ids.0',
                'La variante ya tiene inventario o historial; crea una nueva variante para otra composición.'
            );
    }

    public function test_inventory_update_cannot_override_variant_colors_with_legacy_payload(): void
    {
        $operador = $this->userWithRole('almacenista');
        $seed = $this->inventoryFixture(10);
        $otherColor = $this->catalogFixture(['Morado'])['color_ids'][0];
        Sanctum::actingAs($operador);

        $this->putJson("/api/v1/inventario-hamacas/{$seed['inventario_id']}", [
            'hamaca_variante_id' => $seed['variante_id'],
            'usuario_id' => $seed['propietario_id'],
            'ubicacion_id' => $seed['ubicacion_origen_id'],
            'cantidad' => 8,
            'color_ids' => [$otherColor],
        ])->assertOk();

        $variante = HamacaVariante::with('colores')->findOrFail($seed['variante_id']);

        $this->assertSame($seed['composition_key'], $variante->composicion_clave);
        $this->assertEqualsCanonicalizing(
            $seed['color_ids'],
            $variante->colores->pluck('id')->all()
        );
    }
}
