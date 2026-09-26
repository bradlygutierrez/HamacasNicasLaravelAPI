<?php

namespace Tests\Feature\Sales;

use App\Models\Hamaca;
use App\Models\Color;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\PedidoDetalleServicio;
use App\Models\PedidoServicio;
use App\Services\PedidoFacturacionService;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\ProformaMaterialSnapshot;
use App\Models\ProformaManoObraSnapshot;
use App\Models\ProformaServicio;
use App\Models\ProcesoProduccion;
use App\Models\RecetaHamaca;
use App\Models\ServicioAdicional;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedidoApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_accepted_proforma_converts_once_and_preserves_snapshots_without_side_effects(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $before = [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count()];
        $first = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data');
        $second = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertOk()->json('data');
        $this->assertSame($first['id'], $second['id']);
        $this->assertDatabaseHas('pedido_detalles', ['pedido_id' => $first['id'], 'proforma_detalle_id' => DB::table('proforma_detalles')->where('proforma_id', $proforma->id)->value('id')]);
        $this->assertDatabaseHas('proformas', ['id' => $proforma->id, 'estado' => 'convertida']);
        $this->assertDatabaseHas('pedidos', ['id' => $first['id'], 'total' => '2000.00']);
        $this->assertSame($before, [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count()]);
    }

    public function test_only_accepted_proforma_can_convert_and_vendor_ownership_is_enforced(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $other = $this->user('vendedor2');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertForbidden();
        Sanctum::actingAs($vendor);
        $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated();
        $otherProforma = $this->acceptedProforma($admin, $other);
        $otherProforma->update(['estado' => 'emitida']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/proformas/{$otherProforma->id}/pedido")->assertStatus(409);
    }

    public function test_material_plan_uses_snapshot_price_and_blocks_ready_transition_until_all_ready(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $this->assertDatabaseHas('pedido_materiales', ['pedido_id' => $id, 'cantidad_requerida' => '250.0000', 'cantidad_compra_plan' => 3, 'precio_compra_snapshot' => '600.00']);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_pendientes'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_listos'])->assertStatus(409);
        Pedido::whereKey($id)->update(['estado' => 'terminado']);
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $id)->value('id');
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo'])->assertStatus(409);
        $this->putJson("/api/v1/pedidos/{$id}", ['observaciones_internas' => 'No cambiar'])->assertStatus(409);
    }

    public function test_cancel_requires_real_reason_and_records_history(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');

        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'cancelado', 'comentario' => '   '])->assertStatus(422);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'cancelado', 'comentario' => "  Cliente canceló  "])->assertOk();
        $this->assertDatabaseHas('pedidos', ['id' => $id, 'estado' => 'cancelado', 'cancelado_por_id' => $admin->id, 'motivo_cancelacion' => 'Cliente canceló']);
        $this->assertDatabaseHas('pedido_historial_estados', ['pedido_id' => $id, 'estado_anterior' => 'pendiente', 'estado_nuevo' => 'cancelado', 'comentario' => 'Cliente canceló']);
    }

    public function test_ready_timestamp_is_not_reset_when_material_stays_ready(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $id)->value('id');
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_pendientes'])->assertOk();
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo'])->assertOk();
        $readyAt = DB::table('pedido_materiales')->where('id', $materialId)->value('listo_at');
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo', 'observaciones' => 'Verificado'])->assertOk();
        $this->assertSame($readyAt, DB::table('pedido_materiales')->where('id', $materialId)->value('listo_at'));
    }

    public function test_process_transitions_and_completion_are_terminal(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $id)->value('id');
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_pendientes'])->assertOk();
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_listos'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'en_produccion'])->assertOk();
        $processId = DB::table('pedido_procesos')->insertGetId(['pedido_id' => $id, 'proceso_nombre_snapshot' => 'Tejido', 'costo_estimado' => 50, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'en_proceso'])->assertOk();
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'completado'])->assertOk();
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'completado', 'observaciones' => 'No cambiar'])->assertStatus(409);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'terminado'])->assertOk();
        $this->assertNotNull(DB::table('pedidos')->where('id', $id)->value('fecha_terminado'));
    }

    public function test_copy_header_maps_proforma_labor_cost_to_pedido_column_and_resource(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        $proforma->update(['costo_mano_de_obra_estimado' => '875.50']);

        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido", ['fecha_entrega_estimada' => '2026-10-15'])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('pedidos', ['id' => $id, 'costo_mano_obra_estimado' => '875.50']);
        $response = $this->getJson("/api/v1/pedidos/{$id}")->assertOk()->assertJsonPath('data.analisis_interno.costo_mano_obra_estimado', '875.50');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response->json('data.fecha_pedido'));
        $this->assertSame('2026-10-15', $response->json('data.fecha_entrega_estimada'));
    }

    public function test_conversion_builds_grouped_process_plan_from_proforma_snapshots(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        $detailId = DB::table('proforma_detalles')->where('proforma_id', $proforma->id)->value('id');
        $process = ProcesoProduccion::create(['nombre' => 'Tejido ' . uniqid(), 'state' => true]);
        ProformaManoObraSnapshot::create(['proforma_id' => $proforma->id, 'origen_tipo' => 'receta', 'origen_id' => $detailId, 'proceso_produccion_id' => $process->id, 'proceso_nombre_snapshot' => $process->nombre, 'costo_unitario_snapshot' => '100.00', 'factor_cantidad' => '1.0000', 'costo_total' => '100.00', 'orden' => 2]);
        ProformaManoObraSnapshot::create(['proforma_id' => $proforma->id, 'origen_tipo' => 'receta', 'origen_id' => $detailId, 'proceso_produccion_id' => $process->id, 'proceso_nombre_snapshot' => $process->nombre, 'costo_unitario_snapshot' => '75.00', 'factor_cantidad' => '1.0000', 'costo_total' => '75.00', 'orden' => 1]);

        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');

        $this->assertSame(1, DB::table('pedido_procesos')->where('pedido_id', $id)->count());
        $this->assertDatabaseHas('pedido_procesos', ['pedido_id' => $id, 'proceso_produccion_id' => $process->id, 'proceso_nombre_snapshot' => $process->nombre, 'orden' => 1, 'costo_estimado' => '175.00']);
    }

    public function test_conversion_uses_only_proforma_snapshots_after_catalog_changes(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        $detailId = DB::table('proforma_detalles')->where('proforma_id', $proforma->id)->value('id');
        $materialId = DB::table('proforma_materiales_snapshot')->where('proforma_id', $proforma->id)->value('material_id');
        $hamaca = Hamaca::findOrFail(DB::table('proforma_detalles')->where('id', $detailId)->value('hamaca_id'));
        $recipeId = DB::table('proforma_detalles')->where('id', $detailId)->value('receta_hamaca_id');
        $service = ServicioAdicional::create(['nombre' => 'Envío snapshot ' . uniqid(), 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 150, 'costo_actual' => 80, 'state' => true]);
        ProformaServicio::create(['proforma_id' => $proforma->id, 'servicio_adicional_id' => $service->id, 'servicio_nombre_snapshot' => 'Envío original', 'alcance_snapshot' => 'pedido', 'metodo_calculo_snapshot' => 'manual', 'detalle' => 'Original', 'cantidad' => 1, 'precio_unitario' => 150, 'subtotal' => 150, 'costo_base_unitario_snapshot' => 80, 'costo_unitario_estimado' => 80, 'costo_total_estimado' => 80]);
        $proforma->update(['subtotal_servicios' => 150, 'subtotal_bruto' => 2150, 'base_neta' => 2150, 'aplica_iva' => true, 'tasa_iva' => 12, 'monto_iva' => 258, 'aplica_ir' => true, 'tasa_ir' => 3, 'monto_ir' => 64.5, 'tasa_comision_vendedor' => 7, 'monto_comision_vendedor' => 150.5, 'costo_materiales_estimado' => 1500, 'costo_mano_de_obra_estimado' => 530, 'costo_servicios_base_estimado' => 80, 'costo_total_estimado' => 2110, 'costo_compra_estimado' => 1800, 'utilidad_estimada' => 389.5, 'total' => 2343.5]);
        Material::findOrFail($materialId)->update(['precio_actual' => 900]);
        RecetaHamaca::findOrFail($recipeId)->update(['estado' => 'archivada']);
        RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'version' => 4, 'estado' => 'activa', 'usuario_id' => $admin->id]);
        $hamaca->update(['precio' => 2500]);
        $service->update(['precio_venta_actual' => 999, 'costo_actual' => 999]);

        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');

        $this->assertDatabaseHas('pedido_detalles', ['pedido_id' => $id, 'precio_unitario' => '1000.00', 'receta_version_snapshot' => 3]);
        $this->assertDatabaseHas('pedido_materiales', ['pedido_id' => $id, 'precio_compra_snapshot' => '600.00', 'cantidad_requerida' => '250.0000']);
        $this->assertDatabaseHas('pedido_servicios', ['pedido_id' => $id, 'precio_unitario' => '150.00', 'costo_base_unitario_snapshot' => '80.00']);
        $this->assertDatabaseHas('pedidos', ['id' => $id, 'aplica_iva' => 1, 'tasa_iva' => '12.00', 'monto_iva' => '258.00', 'aplica_ir' => 1, 'tasa_ir' => '3.00', 'monto_ir' => '64.50', 'tasa_comision_vendedor' => '7.00', 'monto_comision_vendedor' => '150.50', 'costo_materiales_estimado' => '1500.00', 'costo_mano_obra_estimado' => '530.00', 'costo_servicios_base_estimado' => '80.00', 'costo_total_estimado' => '2110.00', 'costo_compra_estimado' => '1800.00', 'utilidad_estimada' => '389.50', 'total' => '2343.50']);
    }

    public function test_only_admin_can_cancel_and_terminated_orders_are_terminal(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $warehouse = $this->user('almacenista');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');

        Sanctum::actingAs($warehouse);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'cancelado', 'comentario' => 'No'])->assertForbidden();
        Sanctum::actingAs($vendor);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'cancelado', 'comentario' => 'No'])->assertForbidden();
        Pedido::whereKey($id)->update(['estado' => 'terminado']);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'cancelado', 'comentario' => 'No'])->assertStatus(409);
    }

    public function test_order_without_materials_can_skip_preparation(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        DB::table('pedido_materiales')->where('pedido_id', $id)->delete();

        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_listos'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'en_produccion'])->assertOk();
        $this->assertNotNull(DB::table('pedidos')->where('id', $id)->value('fecha_inicio_produccion'));
    }

    public function test_pending_process_cannot_be_updated_before_production_and_blocks_completion(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $id)->value('id');
        $this->putJson("/api/v1/pedidos/{$id}/procesos/999999", ['estado' => 'en_proceso'])->assertNotFound();
        $processId = DB::table('pedido_procesos')->insertGetId(['pedido_id' => $id, 'proceso_nombre_snapshot' => 'Tejido', 'costo_estimado' => 50, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'en_proceso'])->assertStatus(409);
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_pendientes'])->assertOk();
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_listos'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'en_produccion'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'terminado'])->assertStatus(409);
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'completado'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'terminado'])->assertOk();
        $this->assertNotNull(DB::table('pedidos')->where('id', $id)->value('fecha_terminado'));
    }

    public function test_order_resources_are_filtered_by_role(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $warehouse = $this->user('almacenista');
        $partner = $this->user('socio');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $id)->value('id');
        $processId = DB::table('pedido_procesos')->insertGetId(['pedido_id' => $id, 'proceso_nombre_snapshot' => 'Tejido', 'costo_estimado' => 50, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);

        Sanctum::actingAs($vendor);
        $this->getJson("/api/v1/pedidos/{$id}")->assertOk()->assertJsonPath('data.total', '2000.00')->assertJsonPath('data.resumen_comercial.total', '2000.00')->assertJsonMissingPath('data.materiales')->assertJsonMissingPath('data.procesos')->assertJsonMissingPath('data.analisis_interno');

        Sanctum::actingAs($warehouse);
        $this->getJson("/api/v1/pedidos/{$id}")->assertOk()->assertJsonPath('data.materiales.0.id', $materialId)->assertJsonPath('data.procesos.0.id', $processId)->assertJsonMissingPath('data.analisis_interno')->assertJsonMissingPath('data.detalles.0.precio_unitario')->assertJsonMissingPath('data.detalles.0.descuento')->assertJsonMissingPath('data.detalles.0.subtotal')->assertJsonMissingPath('data.detalles.0.costo_unitario_estimado')->assertJsonMissingPath('data.materiales.0.precio_compra_snapshot')->assertJsonMissingPath('data.materiales.0.costo_consumo_estimado')->assertJsonMissingPath('data.materiales.0.costo_compra_estimado')->assertJsonMissingPath('data.materiales.0.costo_compra_real');

        Sanctum::actingAs($partner);
        $this->getJson("/api/v1/pedidos/{$id}")->assertOk()->assertJsonPath('data.analisis_interno.costo_materiales_estimado', '1500.00')->assertJsonPath('data.materiales.0.costo_consumo_estimado', '1500.00');
        $this->putJson("/api/v1/pedidos/{$id}", ['observaciones_internas' => 'No'])->assertForbidden();
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo'])->assertForbidden();
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'en_proceso'])->assertForbidden();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_pendientes'])->assertForbidden();
    }

    public function test_full_lifecycle_has_no_inventory_sales_or_invoice_side_effects(): void
    {
        $admin = $this->user('admin');
        $vendor = $this->user('vendedor');
        $proforma = $this->acceptedProforma($admin, $vendor);
        Sanctum::actingAs($admin);
        $before = [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count(), DB::table('detalle_facturas')->count()];
        $id = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $id)->value('id');
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_pendientes'])->assertOk();
        $this->putJson("/api/v1/pedidos/{$id}/materiales/{$materialId}", ['estado' => 'listo'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'materiales_listos'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'en_produccion'])->assertOk();
        $processId = DB::table('pedido_procesos')->insertGetId(['pedido_id' => $id, 'proceso_nombre_snapshot' => 'Tejido', 'costo_estimado' => 50, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
        $this->putJson("/api/v1/pedidos/{$id}/procesos/{$processId}", ['estado' => 'completado'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$id}/estado", ['estado' => 'terminado'])->assertOk();
        $this->assertSame($before, [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count(), DB::table('detalle_facturas')->count()]);
        $second = $this->acceptedProforma($admin, $vendor);
        $secondId = $this->postJson("/api/v1/proformas/{$second->id}/pedido")->assertCreated()->json('data.id');
        $this->postJson("/api/v1/pedidos/{$secondId}/estado", ['estado' => 'cancelado', 'comentario' => 'Cancelado en prueba'])->assertOk();
        $this->assertSame($before, [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count(), DB::table('detalle_facturas')->count()]);
    }

    public function test_terminated_order_is_invoiced_once_with_production_entry_and_sale_exit(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $proforma = $this->acceptedProforma($admin, $vendor);
        $detail = ProformaDetalle::where('proforma_id', $proforma->id)->firstOrFail();
        $longSnapshot = str_repeat('P', 120);
        $detail->update(['hamaca_nombre_snapshot' => $longSnapshot]);
        $locationId = DB::table('ubicaciones')->insertGetId(['nombre' => 'Bodega fase 5 ' . uniqid(), 'descripcion' => 'Managua', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($admin);
        $pedidoId = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $this->finishOrder($pedidoId);
        $pedidoDetailId = DB::table('pedido_detalles')->where('pedido_id', $pedidoId)->value('id');
        $payload = ['canal' => 'pos', 'metodo_pago' => 'efectivo', 'ubicacion_id' => $locationId, 'lineas' => [['pedido_detalle_id' => $pedidoDetailId]]];
        $first = $this->postJson("/api/v1/pedidos/{$pedidoId}/facturar", $payload)->assertCreated()->json('data');
        $this->assertSame($longSnapshot, DB::table('detalle_facturas')->where('factura_id', $first['id'])->value('hamaca_nombre'));
        $stock = DB::table('inventario_hamacas')->where('hamaca_id', $detail->hamaca_id)->where('ubicacion_id', $locationId)->first();
        $this->assertSame(0, (int) $stock->cantidad);
        $this->assertDatabaseHas('movimientos', ['pedido_id' => $pedidoId, 'factura_id' => null, 'tipo' => 'entrada', 'cantidad' => 2]);
        $this->assertDatabaseHas('movimientos', ['pedido_id' => $pedidoId, 'factura_id' => $first['id'], 'tipo' => 'salida', 'cantidad' => 2]);
        $second = $this->postJson("/api/v1/pedidos/{$pedidoId}/facturar", $payload)->assertOk()->json('data');
        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, DB::table('facturas')->where('pedido_id', $pedidoId)->count());
        $this->assertSame(2, DB::table('movimientos')->where('pedido_id', $pedidoId)->count());
        $this->assertNotNull(DB::table('pedidos')->where('id', $pedidoId)->value('facturado_at'));
    }

    public function test_order_billing_rejects_unfinished_orders_and_bills_hamaca_detail(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $proforma = $this->acceptedProforma($admin, $vendor);
        $detail = ProformaDetalle::where('proforma_id', $proforma->id)->firstOrFail();
        $locationId = DB::table('ubicaciones')->insertGetId(['nombre' => 'Bodega inválida ' . uniqid(), 'descripcion' => 'Managua', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($admin);
        $pedidoId = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $pedidoDetailId = DB::table('pedido_detalles')->where('pedido_id', $pedidoId)->value('id');
        $payload = ['canal' => 'pos', 'ubicacion_id' => $locationId, 'lineas' => [['pedido_detalle_id' => $pedidoDetailId]]];
        $this->postJson("/api/v1/pedidos/{$pedidoId}/facturar", $payload)->assertStatus(409);
        $this->finishOrder($pedidoId);
        $this->postJson("/api/v1/pedidos/{$pedidoId}/facturar", $payload)->assertCreated();
    }

    public function test_billing_copies_detail_ids_colors_and_services_and_factura_index_serializes_them(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $proforma = $this->acceptedProforma($admin, $vendor);
        $proformaDetail = ProformaDetalle::where('proforma_id', $proforma->id)->firstOrFail();
        $color = Color::create(['nombre' => 'Azul snapshot ' . uniqid(), 'codigo_hex' => '#123456', 'state' => true]);
        $proformaDetail->hamaca->colores()->attach($color->id);
        Sanctum::actingAs($admin);
        $pedidoId = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $pedidoDetail = DB::table('pedido_detalles')->where('pedido_id', $pedidoId)->first();
        $detailService = PedidoDetalleServicio::create(['pedido_detalle_id' => $pedidoDetail->id, 'servicio_nombre_snapshot' => 'Grabado snapshot', 'alcance_snapshot' => 'producto', 'metodo_calculo_snapshot' => 'fijo', 'detalle' => 'Logo', 'cantidad' => 1, 'precio_unitario' => 25, 'descuento' => 0, 'subtotal' => 25]);
        $pedidoService = PedidoServicio::create(['pedido_id' => $pedidoId, 'servicio_nombre_snapshot' => 'Envío snapshot', 'alcance_snapshot' => 'pedido', 'metodo_calculo_snapshot' => 'fijo', 'detalle' => 'León', 'cantidad' => 1, 'precio_unitario' => 50, 'descuento' => 0, 'subtotal' => 50]);
        $this->finishOrder($pedidoId);
        $locationId = DB::table('ubicaciones')->insertGetId(['nombre' => 'Bodega servicios ' . uniqid(), 'descripcion' => 'Managua', 'created_at' => now(), 'updated_at' => now()]);
        $invoice = $this->postJson("/api/v1/pedidos/{$pedidoId}/facturar", ['canal' => 'pos', 'ubicacion_id' => $locationId, 'lineas' => [['pedido_detalle_id' => $pedidoDetail->id]]])->assertCreated()->json('data');
        $invoiceDetailId = DB::table('detalle_facturas')->where('factura_id', $invoice['id'])->value('id');
        $this->assertDatabaseHas('detalle_facturas', ['id' => $invoiceDetailId, 'pedido_detalle_id' => $pedidoDetail->id, 'colores_snapshot' => json_encode([$color->nombre])]);
        $this->assertDatabaseHas('detalle_factura_servicios', ['pedido_detalle_servicio_id' => $detailService->id, 'detalle_factura_id' => $invoiceDetailId]);
        $this->assertDatabaseHas('factura_servicios', ['pedido_servicio_id' => $pedidoService->id, 'factura_id' => $invoice['id']]);
        $this->getJson('/api/v1/pedidos/' . $pedidoId)->assertOk()->assertJsonPath('data.detalles.0.hamaca_id', $proformaDetail->hamaca_id)->assertJsonMissingPath('data.detalles.0.hamaca_variante_id');
        $this->getJson('/api/v1/facturas')->assertOk()->assertJsonPath('data.0.pedido_numero', $invoice['pedido_numero'])->assertJsonPath('data.0.origen', 'pedido')->assertJsonStructure(['data' => [['detalles', 'servicios']]]);
        $detailInvoiceId = $invoiceDetailId;
        Sanctum::actingAs($vendor);
        $this->getJson('/api/v1/detalle_facturas')->assertOk();
        $this->getJson('/api/v1/detalle_facturas/' . $detailInvoiceId)->assertOk()->assertJsonPath('data.hamaca_nombre', 'Pedido snapshot');
        $other = $this->user('vendedor2');
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/detalle_facturas/' . $detailInvoiceId)->assertForbidden();
        Sanctum::actingAs($this->user('almacenista'));
        $this->getJson('/api/v1/detalle_facturas')->assertForbidden();
    }

    public function test_billing_existing_stock_keeps_net_stock_unchanged(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $proforma = $this->acceptedProforma($admin, $vendor);
        $detail = ProformaDetalle::where('proforma_id', $proforma->id)->firstOrFail();
        $locationId = DB::table('ubicaciones')->insertGetId(['nombre' => 'Bodega stock ' . uniqid(), 'descripcion' => 'Managua', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventario_hamacas')->insert(['hamaca_id' => $detail->hamaca_id, 'usuario_id' => $admin->id, 'ubicacion_id' => $locationId, 'cantidad' => 5, 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($admin);
        $pedidoId = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $this->finishOrder($pedidoId);
        $this->postJson("/api/v1/pedidos/{$pedidoId}/facturar", ['canal' => 'pos', 'ubicacion_id' => $locationId, 'lineas' => [['pedido_detalle_id' => DB::table('pedido_detalles')->where('pedido_id', $pedidoId)->value('id')]]])->assertCreated();
        $inventory = DB::table('inventario_hamacas')->where('hamaca_id', $detail->hamaca_id)->where('usuario_id', $admin->id)->where('ubicacion_id', $locationId)->first();
        $this->assertSame(5, (int) $inventory->cantidad);
        $this->assertSame(1, DB::table('movimientos')->where('pedido_id', $pedidoId)->where('tipo', 'entrada')->count());
        $this->assertSame(1, DB::table('movimientos')->where('pedido_id', $pedidoId)->where('tipo', 'salida')->count());
    }

    public function test_billing_rolls_back_after_production_entry_when_invoice_creation_fails(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $proforma = $this->acceptedProforma($admin, $vendor);
        $detail = ProformaDetalle::where('proforma_id', $proforma->id)->firstOrFail();
        $locationId = DB::table('ubicaciones')->insertGetId(['nombre' => 'Bodega rollback ' . uniqid(), 'descripcion' => 'Managua', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($admin);
        $pedidoId = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $this->finishOrder($pedidoId);
        $before = [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count(), DB::table('detalle_facturas')->count()];
        DB::listen(function ($query): void { if (str_contains(strtolower($query->sql), 'insert into `facturas`')) throw new \RuntimeException('forced invoice failure'); });
        try {
            app(PedidoFacturacionService::class)->facturar(Pedido::findOrFail($pedidoId), $admin, ['canal' => 'pos', 'ubicacion_id' => $locationId, 'lineas' => [['pedido_detalle_id' => DB::table('pedido_detalles')->where('pedido_id', $pedidoId)->value('id')]]]);
            $this->fail('La facturación debía fallar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced invoice failure', $exception->getMessage());
        }
        $this->assertSame($before, [DB::table('inventario_hamacas')->count(), DB::table('movimientos')->count(), DB::table('facturas')->count(), DB::table('detalle_facturas')->count()]);
        $this->assertNull(DB::table('pedidos')->where('id', $pedidoId)->value('facturado_at'));
    }

    public function test_billing_roles_and_product_identity_from_order_detail(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $other = $this->user('vendedor2'); $socio = $this->user('socio'); $warehouse = $this->user('almacenista');
        $own = $this->billableOrder($admin, $vendor);
        Sanctum::actingAs($vendor);
        $this->postJson("/api/v1/pedidos/{$own['pedido_id']}/facturar", $this->billingPayload($own))->assertCreated();

        $foreign = $this->billableOrder($admin, $vendor);
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/pedidos/{$foreign['pedido_id']}/facturar", $this->billingPayload($foreign))->assertForbidden();
        Sanctum::actingAs($socio);
        $this->postJson("/api/v1/pedidos/{$foreign['pedido_id']}/facturar", $this->billingPayload($foreign))->assertForbidden();
        Sanctum::actingAs($warehouse);
        $this->postJson("/api/v1/pedidos/{$foreign['pedido_id']}/facturar", $this->billingPayload($foreign))->assertForbidden();

        $fixed = $this->billableOrder($admin, $vendor);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/pedidos/{$fixed['pedido_id']}/facturar", $this->billingPayload($fixed))->assertCreated();
        $this->assertDatabaseHas('inventario_hamacas', ['hamaca_id' => $fixed['hamaca_id'], 'ubicacion_id' => $fixed['location_id'], 'cantidad' => 0]);
    }

    public function test_billing_uses_exact_pedido_financial_and_detail_snapshots(): void
    {
        $admin = $this->user('admin'); $vendor = $this->user('vendedor'); $order = $this->billableOrder($admin, $vendor);
        $service = ServicioAdicional::create(['nombre' => 'Servicio histórico ' . uniqid(), 'alcance' => 'pedido', 'metodo_calculo' => 'manual', 'precio_venta_actual' => 150, 'costo_actual' => 80, 'state' => true]);
        PedidoServicio::create(['pedido_id' => $order['pedido_id'], 'servicio_adicional_id' => $service->id, 'servicio_nombre_snapshot' => 'Envío original', 'alcance_snapshot' => 'pedido', 'metodo_calculo_snapshot' => 'manual', 'detalle' => 'León', 'cantidad' => 1, 'precio_unitario' => 150, 'descuento' => 10, 'subtotal' => 140, 'costo_base_unitario_snapshot' => 80, 'costo_unitario_estimado' => 80, 'costo_total_estimado' => 80]);
        $pedido = Pedido::with('detalles')->findOrFail($order['pedido_id']);
        $snapshot = ['subtotal' => $pedido->subtotal_bruto, 'descuento' => $pedido->descuento_total, 'iva' => $pedido->monto_iva, 'ir' => $pedido->monto_ir, 'total' => $pedido->total, 'nombre' => $pedido->detalles->first()->hamaca_nombre_snapshot, 'descripcion' => $pedido->detalles->first()->hamaca_descripcion_snapshot, 'precio' => $pedido->detalles->first()->precio_unitario, 'detalle_descuento' => $pedido->detalles->first()->descuento, 'detalle_subtotal' => $pedido->detalles->first()->subtotal];
        Hamaca::findOrFail($order['hamaca_id'])->update(['precio' => 9999]);
        $service->update(['precio_venta_actual' => 9999, 'costo_actual' => 9999]);
        Sanctum::actingAs($admin);
        $invoice = $this->postJson("/api/v1/pedidos/{$order['pedido_id']}/facturar", $this->billingPayload($order))->assertCreated()->json('data');
        $this->assertSame((string) $snapshot['subtotal'], (string) $invoice['subtotal']);
        $this->assertSame((string) $snapshot['descuento'], (string) $invoice['descuento']);
        $this->assertSame((string) $snapshot['iva'], (string) $invoice['monto_iva']);
        $this->assertSame((string) $snapshot['ir'], (string) $invoice['monto_ir']);
        $this->assertSame((string) $snapshot['total'], (string) $invoice['total']);
        $this->assertSame($snapshot['nombre'], $invoice['detalles'][0]['hamaca_nombre']);
        $this->assertSame($snapshot['descripcion'], $invoice['detalles'][0]['descripcion']);
        $this->assertSame((string) $snapshot['precio'], (string) $invoice['detalles'][0]['precio_unitario']);
        $this->assertSame((string) $snapshot['detalle_descuento'], (string) $invoice['detalles'][0]['descuento']);
        $this->assertSame((string) $snapshot['detalle_subtotal'], (string) $invoice['detalles'][0]['subtotal']);
        $this->assertSame('Envío original', $invoice['servicios'][0]['nombre']);
        $this->assertSame('150.00', (string) $invoice['servicios'][0]['precio_unitario']);
    }

    private function billableOrder(Usuario $admin, Usuario $vendor): array
    {
        $proforma = $this->acceptedProforma($admin, $vendor);
        $detail = ProformaDetalle::where('proforma_id', $proforma->id)->firstOrFail();
        $locationId = DB::table('ubicaciones')->insertGetId(['nombre' => 'Ubicación role ' . uniqid(), 'descripcion' => 'Managua', 'created_at' => now(), 'updated_at' => now()]);
        Sanctum::actingAs($admin);
        $pedidoId = $this->postJson("/api/v1/proformas/{$proforma->id}/pedido")->assertCreated()->json('data.id');
        $detailId = DB::table('pedido_detalles')->where('pedido_id', $pedidoId)->value('id');
        $this->finishOrder($pedidoId);
        return ['pedido_id' => $pedidoId, 'detail_id' => $detailId, 'location_id' => $locationId, 'hamaca_id' => $detail->hamaca_id];
    }

    private function billingPayload(array $order): array
    {
        return ['canal' => 'pos', 'ubicacion_id' => $order['location_id'], 'lineas' => [['pedido_detalle_id' => $order['detail_id']]]];
    }

    private function finishOrder(int $pedidoId): void
    {
        $this->postJson("/api/v1/pedidos/{$pedidoId}/estado", ['estado' => 'materiales_pendientes'])->assertOk();
        $materialId = DB::table('pedido_materiales')->where('pedido_id', $pedidoId)->value('id');
        $this->putJson("/api/v1/pedidos/{$pedidoId}/materiales/{$materialId}", ['estado' => 'listo'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$pedidoId}/estado", ['estado' => 'materiales_listos'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$pedidoId}/estado", ['estado' => 'en_produccion'])->assertOk();
        $this->postJson("/api/v1/pedidos/{$pedidoId}/estado", ['estado' => 'terminado'])->assertOk();
    }

    private function acceptedProforma(Usuario $admin, Usuario $vendor): Proforma
    {
        $hamaca = Hamaca::create(['nombre' => 'Pedido ' . uniqid(), 'categoria_id' => DB::table('categorias')->value('id'), 'tamano_id' => DB::table('tamanos')->value('id'), 'precio' => 1000]);
        $material = Material::create(['nombre' => 'Manila pedido ' . uniqid(), 'unidad_consumo' => 'metro', 'unidad_compra' => 'rollo', 'contenido_por_compra' => 100, 'precio_actual' => 600, 'porcentaje_merma' => 0, 'state' => true]);
        $recipe = RecetaHamaca::create(['hamaca_id' => $hamaca->id, 'version' => 3, 'estado' => 'activa', 'usuario_id' => $admin->id]);
        $proforma = Proforma::create(['numero' => 'PRO-' . uniqid(), 'vendedor_id' => $vendor->id, 'estado' => 'aceptada', 'nombre_cliente' => 'Cliente snapshot', 'fecha' => now()->toDateString(), 'subtotal_productos' => 2000, 'subtotal_bruto' => 2000, 'base_neta' => 2000, 'tasa_iva' => 15, 'tasa_ir' => 2, 'tasa_comision_vendedor' => 5, 'monto_comision_vendedor' => 100, 'costo_materiales_estimado' => 1500, 'costo_total_estimado' => 1500, 'costo_compra_estimado' => 1800, 'utilidad_estimada' => 400, 'total' => 2000]);
        $detail = ProformaDetalle::create(['proforma_id' => $proforma->id, 'hamaca_id' => $hamaca->id, 'receta_hamaca_id' => $recipe->id, 'receta_version_snapshot' => 3, 'hamaca_nombre_snapshot' => 'Pedido snapshot', 'cantidad' => 2, 'precio_unitario' => 1000, 'subtotal' => 2000, 'costo_unitario_estimado' => 750, 'costo_total_estimado' => 1500]);
        ProformaMaterialSnapshot::create(['proforma_id' => $proforma->id, 'origen_tipo' => 'receta', 'origen_id' => $detail->id, 'material_id' => $material->id, 'material_nombre_snapshot' => 'Manila', 'unidad_consumo_snapshot' => 'metro', 'unidad_compra_snapshot' => 'rollo', 'cantidad_base_unitaria' => 125, 'factor_cantidad' => 2, 'porcentaje_merma' => 0, 'cantidad_total_con_merma' => 250, 'contenido_por_compra_snapshot' => 100, 'precio_compra_snapshot' => 600, 'costo_unidad_consumo_snapshot' => 6, 'costo_consumo_total' => 1500]);
        return $proforma;
    }

    private function user(string $role): Usuario
    {
        DB::table('usuarios')->updateOrInsert(['correo' => 'pedido-' . $role . '@example.com'], ['nombre' => ucfirst($role), 'password' => Hash::make('secret123'), 'rol' => $role === 'vendedor2' ? 'vendedor' : $role, 'state' => true, 'created_at' => now(), 'updated_at' => now()]);
        return Usuario::where('correo', 'pedido-' . $role . '@example.com')->firstOrFail();
    }
}
