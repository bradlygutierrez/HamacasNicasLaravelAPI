<?php

namespace Tests\Feature\Production;

use App\Models\Hamaca;
use App\Models\HamacaVariante;
use App\Models\Material;
use App\Models\ProcesoProduccion;
use App\Models\ServicioAdicional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecetaHamacaApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_first_recipe_is_version_one_draft_and_second_draft_is_rejected(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $variant = $this->variant($hamaca);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/hamaca-variantes/{$variant->id}/recetas")
            ->assertCreated()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.estado', 'borrador');

        $this->postJson("/api/v1/hamaca-variantes/{$variant->id}/recetas")
            ->assertStatus(409)
            ->assertJsonPath('message', 'La variante ya tiene un borrador de receta.');
    }

    public function test_draft_replaces_details_and_rejects_duplicates_or_inactive_components(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $variant = $this->variant($hamaca);
        $material = $this->material();
        $process = $this->process();
        Sanctum::actingAs($admin);

        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$variant->id}/recetas")->json('data.id');

        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", [
            'materiales' => [
                ['material_id' => $material->id, 'cantidad' => 25, 'porcentaje_merma' => null],
                ['material_id' => $material->id, 'cantidad' => 2],
            ],
            'mano_obra' => [
                ['proceso_produccion_id' => $process->id, 'costo_unitario' => 100, 'orden' => 1],
            ],
        ])->assertUnprocessable();

        $material->update(['state' => false]);
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 25]],
            'mano_obra' => [],
        ])->assertUnprocessable();
    }

    public function test_inactive_process_is_rejected(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $process = $this->process();
        $process->update(['state' => false]);
        Sanctum::actingAs($admin);

        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", [
            'materiales' => [],
            'mano_obra' => [['proceso_produccion_id' => $process->id, 'costo_unitario' => 7]],
        ])->assertUnprocessable();
    }

    public function test_activation_archives_previous_recipe_and_cloning_creates_next_version(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $material = $this->material();
        $process = $this->process();
        Sanctum::actingAs($admin);

        $first = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$first}", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 25]],
            'mano_obra' => [['proceso_produccion_id' => $process->id, 'costo_unitario' => 100, 'orden' => 1]],
        ])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$first}/activar")->assertOk();
        $this->assertDatabaseHas('recetas_hamaca', [
            'id' => $first,
            'hamaca_id' => $hamaca->id,
            'version' => 1,
            'estado' => 'activa',
        ]);

        $secondResponse = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")
            ->assertCreated();
        $second = $secondResponse->json('data.id');
        $this->assertSame(2, $secondResponse->json('data.version'));
        $this->assertDatabaseHas('receta_materiales', ['receta_hamaca_id' => $second, 'material_id' => $material->id, 'cantidad' => 25]);
        $this->assertDatabaseHas('receta_mano_obra', ['receta_hamaca_id' => $second, 'proceso_produccion_id' => $process->id, 'costo_unitario' => 100, 'orden' => 1]);
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $second, 'hamaca_id' => $hamaca->id, 'version' => 2, 'estado' => 'borrador']);

        $this->postJson("/api/v1/recetas-hamaca/{$second}/activar")->assertOk();
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $first, 'estado' => 'archivada']);
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $second, 'estado' => 'activa']);
    }

    public function test_update_without_observaciones_preserves_cloned_observaciones(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $material = $this->material();
        Sanctum::actingAs($admin);

        $first = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$first}", [
            'observaciones' => 'Usar hilo reforzado.',
            'materiales' => [['material_id' => $material->id, 'cantidad' => 1]],
            'mano_obra' => [],
        ])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$first}/activar")->assertOk();

        $second = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$second}", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 2]],
            'mano_obra' => [],
        ])->assertOk();

        $this->assertDatabaseHas('recetas_hamaca', ['id' => $second, 'observaciones' => 'Usar hilo reforzado.']);
    }

    public function test_only_drafts_can_be_edited_and_discarded(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $variant = $this->variant($hamaca);
        Sanctum::actingAs($admin);

        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->postJson("/api/v1/recetas-hamaca/{$recipe}/descartar")->assertOk();
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", ['materiales' => [], 'mano_obra' => []])->assertStatus(409);
        $this->postJson("/api/v1/recetas-hamaca/{$recipe}/activar")->assertStatus(409);
    }

    public function test_discard_rechecks_state_after_recipe_is_no_longer_a_draft(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $material = $this->material();
        Sanctum::actingAs($admin);

        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 1]],
            'mano_obra' => [],
        ])->assertOk();
        $this->postJson("/api/v1/recetas-hamaca/{$recipe}/activar")->assertOk();

        $this->postJson("/api/v1/recetas-hamaca/{$recipe}/descartar")->assertStatus(409);
        $this->assertDatabaseHas('recetas_hamaca', ['id' => $recipe, 'estado' => 'activa']);
    }

    public function test_formula_summary_is_paginated_and_searchable(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $variant = $this->variant($hamaca);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/formulas?search=' . urlencode($hamaca->nombre) . '&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $variant->id)
            ->assertJsonPath('data.0.hamaca.id', $hamaca->id)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_recipe_costs_include_material_merma_and_labor(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $material = $this->material();
        $process = $this->process();
        Sanctum::actingAs($admin);

        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 25, 'porcentaje_merma' => null]],
            'mano_obra' => [['proceso_produccion_id' => $process->id, 'costo_unitario' => 100]],
        ])->assertOk();

        $response = $this->getJson("/api/v1/recetas-hamaca/{$recipe}/costos")->assertOk();
        $this->assertSame('157.50', $response->json('data.resumen.costo_materiales'));
        $this->assertSame('100.00', $response->json('data.resumen.costo_mano_obra'));
        $this->assertSame('257.50', $response->json('data.resumen.costo_produccion'));
    }

    public function test_activation_rechecks_components_that_were_deactivated_after_save(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamaca();
        $material = $this->material();
        $process = $this->process();
        Sanctum::actingAs($admin);

        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 1]],
            'mano_obra' => [['proceso_produccion_id' => $process->id, 'costo_unitario' => 7]],
        ])->assertOk();

        $material->update(['state' => false]);
        $this->postJson("/api/v1/recetas-hamaca/{$recipe}/activar")->assertStatus(409);

        $material->update(['state' => true]);
        $process->update(['state' => false]);
        $this->postJson("/api/v1/recetas-hamaca/{$recipe}/activar")->assertStatus(409);
    }

    public function test_service_formula_and_costs_are_hidden_from_vendors(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $service = $this->service();
        $material = $this->material();
        $process = $this->process();
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/servicios-adicionales/{$service->id}/formula", [
            'materiales' => [['material_id' => $material->id, 'cantidad' => 0.5]],
            'mano_obra' => [['proceso_produccion_id' => $process->id, 'costo_unitario' => 7]],
        ])->assertOk();

        $costs = $this->getJson("/api/v1/servicios-adicionales/{$service->id}/costos")->assertOk();
        $this->assertSame('10.15', $costs->json('data.resumen.costo_total'));

        Sanctum::actingAs($vendor);
        $this->getJson("/api/v1/servicios-adicionales/{$service->id}/formula")->assertForbidden();
        $this->getJson("/api/v1/servicios-adicionales/{$service->id}/costos")->assertForbidden();
    }

    public function test_read_roles_can_consult_recipes_but_cannot_modify_them(): void
    {
        $admin = $this->user('admin');
        $partner = $this->user('socio');
        $hamaca = $this->hamaca();
        Sanctum::actingAs($admin);
        $recipe = $this->postJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->json('data.id');

        Sanctum::actingAs($partner);
        $this->getJson("/api/v1/hamaca-variantes/{$this->variant($hamaca)->id}/recetas")->assertOk();
        $this->putJson("/api/v1/recetas-hamaca/{$recipe}", ['materiales' => [], 'mano_obra' => []])->assertForbidden();
    }

    private function user(string $role): Usuario
    {
        DB::table('usuarios')->updateOrInsert(
            ['correo' => "phase2-{$role}@example.com"],
            [
                'nombre' => ucfirst($role),
                'password' => Hash::make('secret123'),
                'rol' => $role,
                'state' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return Usuario::where('correo', "phase2-{$role}@example.com")->firstOrFail();
    }

    private function hamaca(): Hamaca
    {
        return Hamaca::create([
            'nombre' => 'Hamaca Fase 2 ' . uniqid(),
            'descripcion' => 'Modelo de prueba',
            'categoria_id' => DB::table('categorias')->value('id'),
            'tamano_id' => DB::table('tamanos')->value('id'),
            'precio' => 1800,
        ]);
    }

    private function variant(Hamaca $hamaca): HamacaVariante
    {
        return HamacaVariante::firstOrCreate(
            ['hamaca_id' => $hamaca->id, 'composicion_clave' => 'phase2-default'],
            ['nombre' => 'Variante principal', 'state' => true]
        );
    }

    private function material(): Material
    {
        return Material::create([
            'nombre' => 'Manila Fase 2 ' . uniqid(),
            'unidad_consumo' => 'metro',
            'unidad_compra' => 'rollo',
            'contenido_por_compra' => 100,
            'precio_actual' => 600,
            'porcentaje_merma' => 5,
            'state' => true,
        ]);
    }

    private function process(): ProcesoProduccion
    {
        return ProcesoProduccion::create([
            'nombre' => 'Tejido Fase 2 ' . uniqid(),
            'state' => true,
        ]);
    }

    private function service(): ServicioAdicional
    {
        return ServicioAdicional::create([
            'nombre' => 'Letras Fase 2 ' . uniqid(),
            'alcance' => 'producto',
            'metodo_calculo' => 'por_caracter',
            'precio_venta_actual' => 15,
            'costo_actual' => 0,
            'state' => true,
        ]);
    }
}
