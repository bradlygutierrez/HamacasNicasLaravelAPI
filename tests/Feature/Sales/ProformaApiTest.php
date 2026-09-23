<?php

namespace Tests\Feature\Sales;

use App\Models\Hamaca;
use App\Models\HamacaVariante;
use App\Models\Color;
use App\Models\Material;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\RecetaHamaca;
use App\Models\RecetaMaterial;
use App\Models\ServicioAdicional;
use App\Models\ServicioMaterial;
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

    public function test_vendor_values_do_not_expose_internal_cost_keys_but_include_commission(): void
    {
        $vendor = $this->user('vendedor'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($vendor);
        $values = $this->postJson('/api/v1/proformas/calcular', $this->payload($hamaca, null))->json('data.values');
        foreach (['costo_materiales_estimado', 'costo_mano_de_obra_estimado', 'costo_total_estimado', 'costo_compra_estimado', 'utilidad_estimada'] as $key) $this->assertArrayNotHasKey($key, $values);
        $this->assertArrayHasKey('tasa_comision_vendedor', $values); $this->assertArrayHasKey('monto_comision_vendedor', $values);
    }

    public function test_line_service_and_global_discounts_are_not_applied_twice(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); $service = ServicioAdicional::create(['nombre' => 'Servicio descuento', 'alcance' => 'producto', 'metodo_calculo' => 'fijo', 'precio_venta_actual' => 100, 'costo_actual' => 0, 'state' => true]); Sanctum::actingAs($admin);
        $payload = $this->payload($hamaca, $admin->id); $payload['detalles'][0]['descuento'] = 100; $payload['detalles'][0]['servicios'] = [['servicio_adicional_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 100, 'descuento' => 10]]; $payload['descuento_global'] = 50;
        $preview = $this->postJson('/api/v1/proformas/calcular', $payload)->json('data.values'); $id = $this->postJson('/api/v1/proformas', $payload)->json('data.id'); $emitted = $this->postJson("/api/v1/proformas/{$id}/emitir")->json('data');
        $this->assertEquals((float) $preview['base_neta'], (float) $emitted['base_neta']); $this->assertEquals((float) $preview['total'], (float) $emitted['total']);
    }

    public function test_variant_must_belong_to_selected_hamaca(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); $other = $this->hamacaWithRecipe(); $variant = $other->variantes()->create(['nombre' => 'Otra', 'composicion_clave' => 'otra', 'state' => true]); Sanctum::actingAs($admin);
        $payload = $this->payload($hamaca, $admin->id); $payload['detalles'][0]['hamaca_variante_id'] = $variant->id; $this->postJson('/api/v1/proformas', $payload)->assertStatus(422);
    }

    public function test_each_variant_uses_its_own_active_recipe_and_missing_recipe_does_not_fallback(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamacaWithRecipe();
        $first = $hamaca->variantes()->firstOrFail();
        $second = HamacaVariante::create(['hamaca_id' => $hamaca->id, 'nombre' => 'Azul', 'composicion_clave' => 'azul-' . uniqid(), 'state' => true]);
        $material = Material::create(['nombre' => 'Material variante ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 600, 'porcentaje_merma' => 0, 'state' => true]);
        $recipe = RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'hamaca_variante_id' => $second->id, 'version' => 1, 'estado' => 'activa', 'usuario_id' => $admin->id]);
        RecetaMaterial::create(['receta_hamaca_id' => $recipe->id, 'material_id' => $material->id, 'cantidad' => 20]);
        Sanctum::actingAs($admin);

        $firstPayload = $this->payload($hamaca, $admin->id);
        $secondPayload = $firstPayload;
        $secondPayload['detalles'][0]['hamaca_variante_id'] = $second->id;
        $firstId = $this->postJson('/api/v1/proformas', $firstPayload)->assertCreated()->json('data.id');
        $secondId = $this->postJson('/api/v1/proformas', $secondPayload)->assertCreated()->json('data.id');
        $this->assertNotSame(DB::table('proforma_detalles')->where('proforma_id', $firstId)->value('receta_hamaca_id'), DB::table('proforma_detalles')->where('proforma_id', $secondId)->value('receta_hamaca_id'));
        $this->getJson('/api/v1/proformas/productos?search=Azul')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);

        $third = HamacaVariante::create(['hamaca_id' => $hamaca->id, 'nombre' => 'Sin fórmula', 'composicion_clave' => 'sin-formula-' . uniqid(), 'state' => true]);
        $missingPayload = $firstPayload;
        $missingPayload['detalles'][0]['hamaca_variante_id'] = $third->id;
        $this->postJson('/api/v1/proformas', $missingPayload)->assertStatus(422)->assertJsonPath('message', 'La variante seleccionada no tiene una fórmula activa.');
    }

    public function test_legacy_draft_proforma_is_rejected_with_a_clear_variant_message_when_emitted(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamacaWithRecipe();
        $proforma = Proforma::create(['vendedor_id' => $admin->id, 'nombre_cliente' => 'Cliente legacy', 'fecha' => now()->toDateString(), 'estado' => 'borrador']);
        ProformaDetalle::create(['proforma_id' => $proforma->id, 'hamaca_id' => $hamaca->id, 'receta_version_snapshot' => 1, 'hamaca_nombre_snapshot' => $hamaca->nombre, 'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000]);
        Sanctum::actingAs($admin);

        $legacyPayload = $this->payload($hamaca, $admin->id);
        $legacyPayload['detalles'][0]['hamaca_variante_id'] = null;
        $this->postJson('/api/v1/proformas/calcular', $legacyPayload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta proforma contiene productos sin variante. Seleccioná una variante antes de continuar.');

        $this->postJson("/api/v1/proformas/{$proforma->id}/emitir")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta proforma contiene productos sin variante. Seleccioná una variante antes de continuar.');
    }

    public function test_existing_proforma_detail_includes_variant_metadata(): void
    {
        $admin = $this->user('admin');
        $hamaca = $this->hamacaWithRecipe();
        $variant = $hamaca->variantes()->firstOrFail();
        $variant->update(['nombre' => 'Azul / Blanco']);
        $variant->colores()->sync(collect(['Azul', 'Blanco'])->map(fn ($name) => Color::create(['nombre' => $name . ' ' . uniqid()])->id)->all());
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/v1/proformas', $this->payload($hamaca, $admin->id))
            ->assertCreated()
            ->json('data.id');

        $this->getJson("/api/v1/proformas/{$id}")
            ->assertOk()
            ->assertJsonPath('data.detalles.0.variante.id', $variant->id)
            ->assertJsonPath('data.detalles.0.variante.nombre', 'Azul / Blanco')
            ->assertJsonStructure(['data' => ['detalles' => [['variante' => ['id', 'nombre', 'colores']]]]]);
    }

    public function test_inactive_client_is_rejected_and_vendor_override_is_not_persisted(): void
    {
        $vendor = $this->user('vendedor'); $hamaca = $this->hamacaWithRecipe(); $client = \App\Models\Cliente::create(['nombre' => 'Inactivo', 'state' => false]); Sanctum::actingAs($vendor);
        $this->postJson('/api/v1/proformas', array_merge($this->payload($hamaca, null), ['cliente_id' => $client->id, 'nombre_cliente' => null]))->assertStatus(422);
        $service = ServicioAdicional::create(['nombre' => 'Envío override', 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 500, 'costo_actual' => 350, 'state' => true]); $payload = $this->payload($hamaca, null); $payload['servicios_pedido'] = [['servicio_adicional_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 500, 'costo_base_unitario_override' => 1]]; $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id'); $this->assertDatabaseHas('proforma_servicios', ['proforma_id' => $id, 'costo_base_unitario_override' => null]);
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

    private function payload(Hamaca $hamaca, ?int $sellerId): array { $variant = $hamaca->variantes()->firstOrFail(); return ['cliente_id' => null, 'nombre_cliente' => 'Cliente Proforma', 'vendedor_id' => $sellerId, 'detalles' => [['hamaca_id' => $hamaca->id, 'hamaca_variante_id' => $variant->id, 'cantidad' => 1, 'precio_unitario' => 1000, 'descuento' => 0]], 'servicios_pedido' => []]; }
    private function user(string $role): Usuario { DB::table('usuarios')->updateOrInsert(['correo' => "proforma-{$role}@example.com"], ['nombre' => ucfirst($role), 'password' => Hash::make('secret123'), 'rol' => $role === 'vendedor2' ? 'vendedor' : $role, 'state' => true, 'created_at' => now(), 'updated_at' => now()]); return Usuario::where('correo', "proforma-{$role}@example.com")->firstOrFail(); }
    private function hamacaWithRecipe(): Hamaca { $hamaca = Hamaca::create(['nombre' => 'Proforma ' . uniqid(), 'categoria_id' => DB::table('categorias')->value('id'), 'tamano_id' => DB::table('tamanos')->value('id'), 'precio' => 1000]); $variant = HamacaVariante::create(['hamaca_id' => $hamaca->id, 'nombre' => 'Variante principal', 'composicion_clave' => 'proforma-' . uniqid(), 'state' => true]); $material = Material::create(['nombre' => 'Material Proforma ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 600, 'porcentaje_merma' => 0, 'state' => true]); $recipe = RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'hamaca_variante_id' => $variant->id, 'version' => 1, 'estado' => 'activa', 'usuario_id' => $this->user('admin')->id]); RecetaMaterial::create(['receta_hamaca_id' => $recipe->id, 'material_id' => $material->id, 'cantidad' => 10]); return $hamaca; }
    public function test_existing_client_can_keep_custom_proforma_snapshot(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); $client = \App\Models\Cliente::create(['nombre' => 'Empresa ABC', 'direccion' => 'Managua', 'state' => true]); Sanctum::actingAs($admin);
        $payload = array_merge($this->payload($hamaca, $admin->id), ['cliente_id' => $client->id, 'nombre_cliente' => 'Empresa ABC - Sucursal León', 'direccion' => 'León']);
        $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('proformas', ['id' => $id, 'cliente_id' => $client->id, 'nombre_cliente' => 'Empresa ABC - Sucursal León', 'direccion' => 'León']);
    }

    public function test_admin_service_override_and_custom_commission_survive_emit(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); $service = ServicioAdicional::create(['nombre' => 'Envío override final', 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 500, 'costo_actual' => 400, 'state' => true]); Sanctum::actingAs($admin);
        $payload = array_merge($this->payload($hamaca, $admin->id), ['tasa_comision_vendedor' => 7, 'servicios_pedido' => [['servicio_adicional_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 500, 'costo_base_unitario_override' => 350]]]);
        $preview = $this->postJson('/api/v1/proformas/calcular', $payload)->assertOk()->json('data.values'); $this->assertEquals(7, $preview['tasa_comision_vendedor']); $this->assertSame('105.00', (string) $preview['monto_comision_vendedor']);
        $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id'); $this->getJson("/api/v1/proformas/{$id}")->assertOk()->assertJsonPath('data.servicios_pedido.0.costo_base_unitario_override', '350.00');
        $payload['observaciones'] = 'Actualizado'; $this->putJson("/api/v1/proformas/{$id}", $payload)->assertOk(); $this->assertDatabaseHas('proforma_servicios', ['proforma_id' => $id, 'costo_base_unitario_override' => '350.00']);
        $emitted = $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk()->json('data'); $this->assertEquals(7, $emitted['tasa_comision_vendedor']); $this->assertSame('350.00', (string) DB::table('proforma_servicios')->where('proforma_id', $id)->value('costo_base_unitario_snapshot'));
    }

    public function test_emit_uses_recipe_version_active_at_emit_time(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); Sanctum::actingAs($admin); $payload = $this->payload($hamaca, $admin->id); $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id'); $variant = $hamaca->variantes()->firstOrFail(); $v1 = $variant->recetaActiva()->firstOrFail(); $v1->update(['estado' => 'archivada']); $v2 = RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'hamaca_variante_id' => $variant->id, 'version' => 2, 'estado' => 'activa', 'usuario_id' => $admin->id]); RecetaMaterial::create(['receta_hamaca_id' => $v2->id, 'material_id' => Material::firstOrFail()->id, 'cantidad' => 20]);
        $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk(); $this->assertDatabaseHas('proforma_detalles', ['proforma_id' => $id, 'receta_hamaca_id' => $v2->id, 'receta_version_snapshot' => 2]);
    }

    public function test_snapshot_origins_reference_real_aggregate_rows(): void
    {
        $admin = $this->user('admin'); $hamaca = $this->hamacaWithRecipe(); $material = Material::firstOrFail(); $productService = ServicioAdicional::create(['nombre' => 'Grabado origin', 'alcance' => 'producto', 'metodo_calculo' => 'por_producto', 'precio_venta_actual' => 50, 'costo_actual' => 0, 'state' => true]); $orderService = ServicioAdicional::create(['nombre' => 'Envío origin', 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 100, 'costo_actual' => 0, 'state' => true]); ServicioMaterial::create(['servicio_adicional_id' => $productService->id, 'material_id' => $material->id, 'cantidad' => 1]); ServicioMaterial::create(['servicio_adicional_id' => $orderService->id, 'material_id' => $material->id, 'cantidad' => 1]); Sanctum::actingAs($admin);
        $payload = $this->payload($hamaca, $admin->id); $payload['detalles'][0]['servicios'] = [['servicio_adicional_id' => $productService->id, 'cantidad' => 1]]; $payload['servicios_pedido'] = [['servicio_adicional_id' => $orderService->id, 'cantidad' => 1]]; $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id'); $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk();
        $detailId = DB::table('proforma_detalles')->where('proforma_id', $id)->value('id'); $productServiceId = DB::table('proforma_detalle_servicios')->where('proforma_detalle_id', $detailId)->value('id'); $orderServiceId = DB::table('proforma_servicios')->where('proforma_id', $id)->value('id'); $this->assertDatabaseHas('proforma_materiales_snapshot', ['proforma_id' => $id, 'origen_tipo' => 'receta', 'origen_id' => $detailId]); $this->assertDatabaseHas('proforma_materiales_snapshot', ['proforma_id' => $id, 'origen_tipo' => 'servicio_producto', 'origen_id' => $productServiceId]); $this->assertDatabaseHas('proforma_materiales_snapshot', ['proforma_id' => $id, 'origen_tipo' => 'servicio_pedido', 'origen_id' => $orderServiceId]);
    }

    public function test_vendor_preserves_admin_rates_and_service_override_when_updating_and_emitting(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $hamaca = $this->hamacaWithRecipe(); $service = ServicioAdicional::create(['nombre' => 'Envío autorizado', 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 500, 'costo_actual' => 400, 'state' => true]); Sanctum::actingAs($admin);
        $payload = array_merge($this->payload($hamaca, $vendor->id), ['aplica_iva' => true, 'tasa_iva' => 12, 'aplica_ir' => true, 'tasa_ir' => 3, 'tasa_comision_vendedor' => 7, 'servicios_pedido' => [['servicio_adicional_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 500, 'costo_base_unitario_override' => 350]]]);
        $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id'); Sanctum::actingAs($vendor);
        $vendorPayload = array_merge($this->payload($hamaca, $admin->id), ['aplica_iva' => true, 'tasa_iva' => 99, 'aplica_ir' => true, 'tasa_ir' => 99, 'tasa_comision_vendedor' => 99, 'observaciones' => 'Cambio', 'servicios_pedido' => [['servicio_adicional_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 500]]]);
        $this->putJson("/api/v1/proformas/{$id}", $vendorPayload)->assertOk(); $this->assertDatabaseHas('proformas', ['id' => $id, 'tasa_iva' => '12.00', 'tasa_ir' => '3.00', 'tasa_comision_vendedor' => '7.00']); $this->assertDatabaseHas('proforma_servicios', ['proforma_id' => $id, 'costo_base_unitario_override' => '350.00']);
        $this->postJson("/api/v1/proformas/{$id}/emitir")->assertOk()->assertJsonPath('data.tasa_iva', '12.00')->assertJsonPath('data.tasa_ir', '3.00')->assertJsonPath('data.tasa_comision_vendedor', '7.00'); $this->assertDatabaseHas('proforma_servicios', ['proforma_id' => $id, 'costo_base_unitario_snapshot' => '350.00']);
    }

    public function test_vendor_cannot_set_administrative_rates_or_override_when_creating(): void
    {
        $vendor = $this->user('vendedor'); $hamaca = $this->hamacaWithRecipe(); $service = ServicioAdicional::create(['nombre' => 'Envío default', 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 500, 'costo_actual' => 400, 'state' => true]); Sanctum::actingAs($vendor);
        $payload = array_merge($this->payload($hamaca, null), ['aplica_iva' => true, 'tasa_iva' => 99, 'aplica_ir' => true, 'tasa_ir' => 99, 'tasa_comision_vendedor' => 99, 'servicios_pedido' => [['servicio_adicional_id' => $service->id, 'cantidad' => 1, 'precio_unitario' => 500, 'costo_base_unitario_override' => 1]]]);
        $id = $this->postJson('/api/v1/proformas', $payload)->assertCreated()->json('data.id'); $this->assertDatabaseHas('proformas', ['id' => $id, 'tasa_iva' => config('comercial.iva_rate'), 'tasa_ir' => config('comercial.ir_rate'), 'tasa_comision_vendedor' => config('proformas.commission_rate')]); $this->assertDatabaseHas('proforma_servicios', ['proforma_id' => $id, 'costo_base_unitario_override' => null]);
    }
}
