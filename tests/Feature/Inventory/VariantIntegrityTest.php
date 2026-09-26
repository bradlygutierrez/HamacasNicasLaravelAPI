<?php

namespace Tests\Feature\Inventory;

use App\Models\RecetaHamaca;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Support\BuildsInventoryFixtures;
use Tests\TestCase;

class VariantIntegrityTest extends TestCase
{
    use BuildsInventoryFixtures;
    use DatabaseTransactions;

    public function test_product_identity_cannot_change_after_recipe_history_exists(): void
    {
        $operator = $this->userWithRole('admin');
        $product = $this->catalogFixture();
        $other = $this->catalogFixture(['Dorado']);
        RecetaHamaca::create([
            'hamaca_id' => $product['hamaca_id'], 'version' => 1,
            'estado' => 'borrador', 'usuario_id' => $operator->id,
        ]);
        Sanctum::actingAs($operator);

        $this->putJson("/api/v1/hamacas/{$product['hamaca_id']}", [
            'color_ids' => $other['color_ids'],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'La hamaca ya tiene historial productivo o comercial. Creá una nueva hamaca para otra combinación de colores.');

        $this->putJson("/api/v1/hamacas/{$product['hamaca_id']}", [
            'categoria_id' => $other['categoria_id'],
            'tamano_id' => $other['tamano_id'],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'La clasificación de una hamaca con historial no puede cambiarse. Creá una nueva hamaca.');
    }

    public function test_name_and_price_remain_editable_after_recipe_history_exists(): void
    {
        $operator = $this->userWithRole('admin');
        $product = $this->catalogFixture();
        RecetaHamaca::create([
            'hamaca_id' => $product['hamaca_id'], 'version' => 1,
            'estado' => 'borrador', 'usuario_id' => $operator->id,
        ]);
        Sanctum::actingAs($operator);

        $this->putJson("/api/v1/hamacas/{$product['hamaca_id']}", [
            'nombre' => 'Nombre personalizado', 'precio' => 1900,
        ])->assertOk();
    }
}
