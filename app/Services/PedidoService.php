<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Pedido;
use App\Models\Proforma;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class PedidoService
{
    public function __construct(private readonly PedidoMaterialService $materials, private readonly PedidoProcesoService $processes) {}

    public function convertFromProforma(Proforma $proforma, Usuario $user, array $data = []): array
    {
        return DB::transaction(function () use ($proforma, $user, $data): array {
            $proforma = Proforma::query()->with(['detalles.servicios', 'servicios', 'materialesSnapshot', 'manoObraSnapshot'])->lockForUpdate()->findOrFail($proforma->id);
            if (!in_array($user->rol, ['admin', 'vendedor'], true)) throw new BusinessRuleException('No tenés permiso para convertir proformas.', [], 403);
            if ($user->rol === 'vendedor' && $proforma->vendedor_id !== $user->id) throw new BusinessRuleException('No tenés acceso a esta proforma.', [], 403);
            $existing = $proforma->pedido()->with($this->relations())->first(); if ($existing) return [$existing, false];
            if ($proforma->estado !== 'aceptada') throw new BusinessRuleException('Solo una proforma aceptada puede convertirse en pedido.');
            $pedido = Pedido::create($this->copyHeader($proforma, $user, $data)); $pedido->update(['numero' => 'PED-' . now()->format('Y') . '-' . str_pad((string) $pedido->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($proforma->detalles as $detail) { $line = $pedido->detalles()->create($detail->only(['hamaca_id','hamaca_variante_id','receta_hamaca_id','receta_version_snapshot','hamaca_nombre_snapshot','hamaca_descripcion_snapshot','cantidad','precio_unitario','descuento','subtotal','costo_unitario_estimado','costo_total_estimado']) + ['proforma_detalle_id' => $detail->id]); foreach ($detail->servicios as $service) $line->servicios()->create($service->only(['servicio_adicional_id','servicio_nombre_snapshot','alcance_snapshot','metodo_calculo_snapshot','unidad_snapshot','detalle','cantidad','precio_unitario','descuento','subtotal','costo_base_unitario_override','costo_base_unitario_snapshot','costo_unitario_estimado','costo_total_estimado']) + ['proforma_detalle_servicio_id' => $service->id]); }
            foreach ($proforma->servicios as $service) $pedido->servicios()->create($service->only(['servicio_adicional_id','servicio_nombre_snapshot','alcance_snapshot','metodo_calculo_snapshot','unidad_snapshot','detalle','cantidad','precio_unitario','descuento','subtotal','costo_base_unitario_override','costo_base_unitario_snapshot','costo_unitario_estimado','costo_total_estimado']) + ['proforma_servicio_id' => $service->id]);
            $this->materials->createPlan($pedido, $proforma->materialesSnapshot); $this->processes->createPlan($pedido, $proforma->manoObraSnapshot); $this->history($pedido, null, 'pendiente', $user);
            $proforma->update(['estado' => 'convertida']);
            return [$pedido->fresh($this->relations()), true];
        });
    }

    public function changeStatus(Pedido $pedido, string $state, Usuario $user, ?string $comment = null): Pedido
    {
        return DB::transaction(function () use ($pedido, $state, $user, $comment): Pedido {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->assertStateAccess($pedido, $state, $user);
            $allowed = ['pendiente' => ['materiales_pendientes', 'materiales_listos', 'cancelado'], 'materiales_pendientes' => ['materiales_listos', 'cancelado'], 'materiales_listos' => ['en_produccion', 'cancelado'], 'en_produccion' => ['terminado', 'cancelado']];
            if (!in_array($state, $allowed[$pedido->estado] ?? [], true)) throw new BusinessRuleException('La transición del pedido no es válida.');
            if ($state === 'materiales_listos' && $pedido->estado === 'pendiente' && $pedido->materiales()->exists()) throw new BusinessRuleException('El pedido debe pasar primero por materiales pendientes.');
            if ($state === 'materiales_listos' && !$this->materials->allReady($pedido)) throw new BusinessRuleException('Todos los materiales deben estar listos.');
            if ($state === 'en_produccion') $pedido->fecha_inicio_produccion ??= now();
            if ($state === 'terminado' && !$this->processes->allComplete($pedido)) throw new BusinessRuleException('Todos los procesos deben estar completados.');
            if ($state === 'terminado') $pedido->fecha_terminado = now();
            if ($state === 'cancelado') { if ($user->rol !== 'admin') throw new BusinessRuleException('Solo admin puede cancelar pedidos.', [], 403); $comment = trim((string) $comment); if ($comment === '') throw new BusinessRuleException('El motivo de cancelación es obligatorio.', [], 422); $pedido->cancelado_at = now(); $pedido->cancelado_por_id = $user->id; $pedido->motivo_cancelacion = $comment; }
            $previous = $pedido->estado; $pedido->estado = $state; $pedido->save(); $this->history($pedido, $previous, $state, $user, $comment); return $pedido->fresh($this->relations());
        });
    }

    public function updateLogistics(Pedido $pedido, array $data, Usuario $user): Pedido { if (!in_array($user->rol, ['admin', 'almacenista'], true)) throw new BusinessRuleException('No tenés permiso para actualizar logística.', [], 403); if (in_array($pedido->estado, ['terminado', 'cancelado'], true)) throw new BusinessRuleException('El pedido ya no puede modificarse.', [], 409); $pedido->update(array_intersect_key($data, array_flip(['fecha_entrega_estimada', 'observaciones_internas']))); return $pedido->fresh($this->relations()); }
    public function cancel(Pedido $pedido, string $reason, Usuario $user): Pedido { return $this->changeStatus($pedido, 'cancelado', $user, $reason); }
    public function canView(Pedido $pedido, Usuario $user): bool { return $user->rol !== 'vendedor' || $pedido->vendedor_id === $user->id; }
    private function copyHeader(Proforma $p, Usuario $user, array $data): array { $fields = ['cliente_id','nombre_cliente','ruc','direccion','telefono','correo','subtotal_productos','subtotal_servicios','subtotal_bruto','descuento_global','descuento_lineas','descuento_total','base_neta','aplica_iva','tasa_iva','monto_iva','aplica_ir','tasa_ir','monto_ir','tasa_comision_vendedor','monto_comision_vendedor','costo_materiales_estimado','costo_servicios_base_estimado','costo_total_estimado','costo_compra_estimado','utilidad_estimada','total']; $values = $p->only($fields); $values['costo_mano_obra_estimado'] = $p->costo_mano_de_obra_estimado; return $values + ['proforma_id' => $p->id, 'proforma_numero_snapshot' => $p->numero, 'vendedor_id' => $p->vendedor_id, 'creado_por_id' => $user->id, 'estado' => 'pendiente', 'fecha_pedido' => now()->toDateString(), 'fecha_entrega_estimada' => $data['fecha_entrega_estimada'] ?? null, 'observaciones_cliente' => $p->observaciones, 'observaciones_internas' => $data['observaciones_internas'] ?? null]; }
    private function history(Pedido $pedido, ?string $from, string $to, Usuario $user, ?string $comment = null): void { $pedido->historial()->create(['estado_anterior' => $from, 'estado_nuevo' => $to, 'usuario_id' => $user->id, 'comentario' => $comment]); }
    private function assertStateAccess(Pedido $pedido, string $state, Usuario $user): void { if (!in_array($user->rol, ['admin', 'almacenista'], true)) throw new BusinessRuleException('No tenés permiso para operar el pedido.', [], 403); }
    private function relations(): array { return ['proforma','vendedor','detalles.servicios','servicios','materiales','procesos','historial']; }
}
