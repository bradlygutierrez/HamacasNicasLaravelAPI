<?php

namespace Tests\Feature\Sales;

use App\Models\Hamaca;
use App\Models\Material;
use App\Models\Pedido;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\ProformaMaterialSnapshot;
use App\Models\RecetaHamaca;
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
