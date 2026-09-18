<?php

namespace Tests\Feature\Sales;

use App\Models\Hamaca;
use App\Models\Material;
use App\Models\RecetaHamaca;
use App\Models\RecetaMaterial;
use App\Models\ServicioAdicional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProformaApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vendor_is_forced_to_own_draft_and_no_inventory_side_effects_occur(): void
    {
        $vendor = $this->user('vendedor'); $other = $this->user('vendedor2'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($vendor);
        $before = [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count()];
        $response = $this->postJson('/api/v1/proformas', $this->payload($hamaca, $other->id))->assertCreated();
        $id = $response->json('data.id'); $response->assertJsonPath('data.numero', null); $this->assertDatabaseHas('proformas', ['id' => $id, 'vendedor_id' => $vendor->id, 'estado' => 'borrador']);
        $this->assertSame($before, [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count()]);
    }

    public function test_preview_calculates_price_tax_ir_commission_and_recipe_cost(): void
    {
        $vendor = $this->user('vendedor'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($vendor);
        $response = $this->postJson('/api/v1/proformas/calcular', $this->payload($hamaca, null) + ['aplica_iva' => true, 'aplica_ir' => true])->assertOk();
        $this->assertEquals(1000, $response->json('data.values.base_neta')); $this->assertEquals(150, $response->json('data.values.monto_iva')); $this->assertEquals(20, $response->json('data.values.monto_ir')); $this->assertEquals(1130, $response->json('data.values.total')); $this->assertArrayNotHasKey('analisis_interno', $response->json('data'));
    }

    public function test_emit_freezes_snapshots_and_retry_does_not_change_number(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($admin);
        $id = $this->postJson('/api/v1/proformas', $this->payload($hamaca, $admin->id))->json('data.id'); $emitted = $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk(); $number = $emitted->json('data.numero'); $this->assertNotNull($number); $this->assertSame(1, DB::table('proforma_materiales_snapshot')->where('proforma_id', $id)->count());
        $material = Material::firstOrFail(); $snapshot = DB::table('proforma_materiales_snapshot')->where('proforma_id', $id)->value('precio_compra_snapshot'); $material->update(['precio_actual' => 999]);
        $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk()->assertJsonPath('data.numero', $number); $this->assertSame((string) $snapshot, (string) DB::table('proforma_materiales_snapshot')->where('proforma_id', $id)->value('precio_compra_snapshot')); $this->putJson("/api/v1/proformas/{$id}", $this->payload($hamaca, $admin->id))->assertStatus(409);
    }

    public function test_vendor_cannot_see_internal_analysis_and_other_vendor_is_forbidden(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $other = $this->user('vendedor2'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($admin); $id = $this->postJson('/api/v1/proformas', $this->payload($hamaca, $vendor->id))->json('data.id');
        Sanctum::actingAs($vendor); $this->getJson("/api/v1/proformas/{$id}")->assertOk()->assertJsonPath('data.analisis_interno', null); Sanctum::actingAs($other); $this->getJson("/api/v1/proformas/{$id}")->assertForbidden();
    }

    public function test_status_transition_is_validated(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($admin); $id = $this->postJson('/api/v1/proformas', $this->payload($hamaca, $admin->id))->json('data.id'); $this->postJson("/api/v1/proformas/{$id}/estado", ['estado' => 'aceptada'])->assertStatus(409); $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk(); $this->postJson("/api/v1/proformas/{$id}/estado", ['estado' => 'enviada'])->assertOk(); $this->postJson("/api/v1/proformas/{$id}/estado", ['estado' => 'aceptada'])->assertOk();
    }

    private function payload(Hamaca $hamaca, ?int $sellerId): array { return ['cliente_id' => null, 'nombre_cliente' => 'Cliente Proforma', 'vendedor_id' => $sellerId, 'detalles' => [['hamaca_id' => $hamaca->id, 'cantidad' => 1, 'precio_unitario' => 1000, 'descuento' => 0]], 'servicios_pedido' => []]; }
    private function user(string $role): Usuario { DB::table('usuarios')->updateOrInsert(['correo' => "proforma-{$role}@example.com"], ['nombre' => ucfirst($role), 'password' => Hash::make('secret123'), 'rol' => $role === 'vendedor2' ? 'vendedor' : $role, 'state' => true, 'created_at' => now(), 'updated_at' => now()]); return Usuario::where('correo', "proforma-{$role}@example.com")->firstOrFail(); }
    private function hamacaWithRecipe(): Hamaca { $hamaca = Hamaca::create(['nombre' => 'Proforma ' . uniqid(), 'categoria_id' => DB::table('categorias')->value('id'), 'tamano_id' => DB::table('tamanos')->value('id'), 'precio' => 1000]); $material = Material::create(['nombre' => 'Material Proforma ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 600, 'porcentaje_merma' => 0, 'state' => true]); $recipe = RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'version' => 1, 'estado' => 'activa', 'usuario_id' => $this->user('admin')->id]); RecetaMaterial::create(['receta_hamaca_id' => $recipe->id, 'material_id' => $material->id, 'cantidad' => 10]); return $hamaca; }
}
