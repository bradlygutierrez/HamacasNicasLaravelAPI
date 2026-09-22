<?php

namespace Tests\Feature\Production;

use App\Models\Hamaca;
use App\Models\HamacaVariante;
use App\Models\Material;
use App\Models\RecetaMaterial;
use App\Models\RecetaHamaca;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VariantRecipeApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_variant_can_create_and_list_its_independent_recipe(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $variant = HamacaVariante::create([
            'hamaca_id' => $hamaca->id,
            'nombre' => 'Blanco',
            'composicion_clave' => 'variant-' . uniqid(),
            'state' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/hamaca-variantes/{$variant->id}/recetas")
            ->assertCreated()
            ->assertJsonPath('data.hamaca_variante_id', $variant->id)
            ->assertJsonPath('data.version', 1);

        $this->getJson("/api/v1/hamaca-variantes/{$variant->id}/recetas")
            ->assertOk()
            ->assertJsonPath('data.0.hamaca_variante_id', $variant->id);
    }

    public function test_variants_have_independent_versions_and_activation(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $white = $this->variant($hamaca, 'Blanco');
        $blue = $this->variant($hamaca, 'Azul');
        $material = Material::create(['nombre' => 'Hilo ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 100, 'porcentaje_merma' => 0, 'state' => true]);
        Sanctum::actingAs($admin);

        $whiteDraft = $this->postJson("/api/v1/hamaca-variantes/{$white->id}/recetas")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$whiteDraft}", ['materiales' => [['material_id' => $material->id, 'cantidad' => 2]], 'mano_obra' => []])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$whiteDraft}/activar")->assertOk();
        $secondWhiteDraft = $this->postJson("/api/v1/hamaca-variantes/{$white->id}/recetas")->assertCreated()->assertJsonPath('data.version', 2)->json('data.id');
        $this->postJson("/api/v1/hamaca-variantes/{$blue->id}/recetas")->assertCreated()->assertJsonPath('data.version', 1);

        $this->assertDatabaseHas('recetas_hamaca', ['id' => $whiteDraft, 'hamaca_variante_id' => $white->id, 'estado' => 'activa']);
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $secondWhiteDraft, 'hamaca_variante_id' => $white->id, 'estado' => 'borrador']);
        $this->assertDatabaseHas('recetas_hamaca', ['hamaca_variante_id' => $blue->id, 'version' => 1, 'estado' => 'borrador']);

        $this->postJson("/api/v1/recetas-hamaca/{$secondWhiteDraft}/activar")->assertOk();
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $whiteDraft, 'estado' => 'archivada']);
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $secondWhiteDraft, 'estado' => 'activa']);
    }

    public function test_new_variant_can_copy_only_an_active_formula_from_same_model(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $source = $this->variant($hamaca, 'Origen');
        $target = $this->variant($hamaca, 'Destino');
        $otherModel = $this->hamaca();
        $foreign = $this->variant($otherModel, 'Extranjera');
        Sanctum::actingAs($admin);

        $sourceDraft = $this->postJson("/api/v1/hamaca-variantes/{$source->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$sourceDraft}", ['materiales' => [], 'mano_obra' => []]);
        // An empty recipe cannot activate; create a valid material row through the API path.
        $material = Material::create(['nombre' => 'Hilo copia ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 100, 'porcentaje_merma' => 0, 'state' => true]);
        $this->putJson("/api/v1/recetas-hamaca/{$sourceDraft}", ['materiales' => [['material_id' => $material->id, 'cantidad' => 1]], 'mano_obra' => []])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$sourceDraft}/activar")->assertOk();

        $copy = $this->postJson("/api/v1/hamaca-variantes/{$target->id}/recetas", ['source_variant_id' => $source->id])->assertCreated();
        $this->assertDatabaseHas('receta_materiales', ['receta_hamaca_id' => $copy->json('data.id'), 'material_id' => $material->id]);
        $this->postJson("/api/v1/hamaca-variantes/{$foreign->id}/recetas", ['source_variant_id' => $source->id])->assertStatus(422);

        $this->postJson("/api/v1/recetas-hamaca/{$copy->json('data.id')}/activar")->assertOk();
        // source_variant_id must not override an active formula on the destination variant.
        $before = RecetaHamaca::where('hamaca_variante_id', $target->id)->count();
        $this->postJson("/api/v1/hamaca-variantes/{$target->id}/recetas", ['source_variant_id' => $source->id])->assertStatus(422);
        $this->assertSame($before, RecetaHamaca::where('hamaca_variante_id', $target->id)->count());
    }

    public function test_formula_summary_lists_variants_and_excludes_legacy_recipes_as_rows(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $variant = $this->variant($hamaca, 'Visible');
        RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'version' => 99, 'estado' => 'activa', 'usuario_id' => $admin->id]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/formulas?search=' . urlencode($hamaca->nombre));
        $response->assertOk()->assertJsonPath('data.0.id', $variant->id)->assertJsonPath('data.0.variante.nombre', 'Visible');
        $this->assertCount(1, $response->json('data'));
    }

    private function variant(Hamaca $hamaca, string $name): HamacaVariante
    {
        return HamacaVariante::create(['hamaca_id' => $hamaca->id, 'nombre' => $name, 'composicion_clave' => strtolower($name) . '-' . uniqid(), 'state' => true]);
    }

    private function user(string $role): Usuario
    {
        return Usuario::create([
            'nombre' => ucfirst($role) . uniqid(),
            'correo' => $role . uniqid() . '@example.com',
            'password' => Hash::make('secret123'),
            'rol' => $role,
            'state' => true,
        ]);
    }

    private function hamaca(): Hamaca
    {
        return Hamaca::create([
            'nombre' => 'Variante recipe ' . uniqid(),
            'descripcion' => 'Modelo de prueba',
            'categoria_id' => DB::table('categorias')->value('id'),
            'tamano_id' => DB::table('tamanos')->value('id'),
            'precio' => 1800,
        ]);
    }
}
