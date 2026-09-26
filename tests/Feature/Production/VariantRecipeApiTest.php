<?php

namespace Tests\Feature\Production;

use App\Models\Hamaca;
use App\Models\Material;
use App\Models\RecetaHamaca;
use App\Models\RecetaMaterial;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VariantRecipeApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hamaca_owns_independent_formula_versions_and_clones_its_active_formula(): void
    {
        $admin = $this->user(); $hamaca = $this->hamaca(); $material = $this->material(); Sanctum::actingAs($admin);
        $v1 = $this->postJson("/api/v1/hamacas/{$hamaca->id}/recetas")->assertCreated()->assertJsonPath('data.hamaca_id', $hamaca->id)->assertJsonPath('data.version', 1)->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$v1}", ['materiales' => [['material_id' => $material->id, 'cantidad' => 2]], 'mano_obra' => []])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$v1}/activar")->assertOk();
        $v2 = $this->postJson("/api/v1/hamacas/{$hamaca->id}/recetas")->assertCreated()->assertJsonPath('data.version', 2)->json('data.id');
        $this->assertDatabaseHas('receta_materiales', ['receta_hamaca_id' => $v2, 'material_id' => $material->id, 'cantidad' => 2]);
        $this->postJson("/api/v1/recetas-hamaca/{$v2}/activar")->assertOk();
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $v1, 'estado' => 'archivada']);
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $v2, 'estado' => 'activa']);
        $this->getJson("/api/v1/hamacas/{$hamaca->id}/recetas/activa")->assertOk()->assertJsonPath('data.id', $v2);
    }

    public function test_first_formula_can_copy_only_from_same_category_and_size_hamaca(): void
    {
        $admin = $this->user(); $source = $this->hamaca(); $target = $this->hamaca($source->categoria_id, $source->tamano_id); $wrongCategory = DB::table('categorias')->insertGetId(['nombre' => 'Otra categoría '.uniqid(), 'created_at' => now(), 'updated_at' => now()]); $wrong = $this->hamaca($wrongCategory, $source->tamano_id); $material = $this->material(); Sanctum::actingAs($admin);
        $sourceId = $this->postJson("/api/v1/hamacas/{$source->id}/recetas")->assertCreated()->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$sourceId}", ['observaciones' => 'Nota copiada', 'materiales' => [['material_id' => $material->id, 'cantidad' => 3]], 'mano_obra' => []])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$sourceId}/activar")->assertOk();
        $copyId = $this->postJson("/api/v1/hamacas/{$target->id}/recetas", ['source_hamaca_id' => $source->id])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $copyId, 'observaciones' => 'Nota copiada']);
        $this->assertDatabaseHas('receta_materiales', ['receta_hamaca_id' => $copyId, 'material_id' => $material->id, 'cantidad' => 3]);
        $this->postJson("/api/v1/hamacas/{$wrong->id}/recetas", ['source_hamaca_id' => $source->id])->assertUnprocessable();
        $this->postJson("/api/v1/hamacas/{$target->id}/recetas", ['source_hamaca_id' => $source->id])->assertUnprocessable();
    }

    public function test_formula_summary_has_one_row_per_hamaca_and_searches_its_colors(): void
    {
        $admin = $this->user(); $hamaca = $this->hamaca(); $colorId = DB::table('colores')->insertGetId(['nombre' => 'Único ' . uniqid(), 'created_at' => now(), 'updated_at' => now()]); $hamaca->colores()->attach($colorId); Sanctum::actingAs($admin);
        $response = $this->getJson('/api/v1/formulas?search=' . urlencode('Único'))->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.hamaca.id', $hamaca->id)->assertJsonPath('data.0.hamaca.colores.0.id', $colorId);
    }

    private function user(): Usuario { return Usuario::create(['nombre' => 'Admin ' . uniqid(), 'correo' => uniqid() . '@example.com', 'password' => Hash::make('secret123'), 'rol' => 'admin', 'state' => true]); }
    private function hamaca(?int $categoriaId = null, ?int $tamanoId = null): Hamaca { return Hamaca::create(['nombre' => 'Producto ' . uniqid(), 'categoria_id' => $categoriaId ?? DB::table('categorias')->value('id'), 'tamano_id' => $tamanoId ?? DB::table('tamanos')->value('id'), 'precio' => 1800]); }
    private function material(): Material { return Material::create(['nombre' => 'Material ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 100, 'porcentaje_merma' => 0, 'state' => true]); }
}
