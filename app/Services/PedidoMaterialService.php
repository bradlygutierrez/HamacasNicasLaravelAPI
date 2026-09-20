<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoMaterial;
use App\Models\Usuario;
use App\Support\DecimalMoney;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

class PedidoMaterialService
{
    public function createPlan(Pedido $pedido, $snapshots): void
    {
        $groups = [];
        foreach ($snapshots as $snapshot) {
            $key = $snapshot->material_id !== null ? 'material:' . $snapshot->material_id : implode('|', [$snapshot->material_nombre_snapshot, $snapshot->unidad_consumo_snapshot, $snapshot->unidad_compra_snapshot, $snapshot->contenido_por_compra_snapshot, $snapshot->precio_compra_snapshot]);
            if (!isset($groups[$key])) $groups[$key] = ['material_id' => $snapshot->material_id, 'material_nombre_snapshot' => $snapshot->material_nombre_snapshot, 'unidad_consumo_snapshot' => $snapshot->unidad_consumo_snapshot, 'unidad_compra_snapshot' => $snapshot->unidad_compra_snapshot, 'cantidad_requerida' => '0.0000', 'contenido_por_compra_snapshot' => (string) $snapshot->contenido_por_compra_snapshot, 'precio_compra_snapshot' => (string) $snapshot->precio_compra_snapshot, 'costo_consumo_estimado' => '0.00'];
            $groups[$key]['cantidad_requerida'] = DecimalMoney::add($groups[$key]['cantidad_requerida'], (string) $snapshot->cantidad_total_con_merma, 4);
            $groups[$key]['costo_consumo_estimado'] = DecimalMoney::add($groups[$key]['costo_consumo_estimado'], (string) $snapshot->costo_consumo_total);
        }
        foreach ($groups as $group) {
            $plan = DecimalMoney::ceilUnits($group['cantidad_requerida'], $group['contenido_por_compra_snapshot']);
            $pedido->materiales()->create($group + ['cantidad_compra_plan' => $plan, 'costo_compra_estimado' => DecimalMoney::mul((string) $plan, $group['precio_compra_snapshot'])]);
        }
    }

    public function update(Pedido $pedido, PedidoMaterial $material, array $data, Usuario $user): PedidoMaterial
    {
        if (in_array($pedido->estado, ['terminado', 'cancelado'], true)) throw new BusinessRuleException('El pedido ya no permite modificar materiales.', [], 409);
        $row = $pedido->materiales()->findOrFail($material->id);
        $allowed = ['estado', 'cantidad_compra_real', 'observaciones'];
        if ($user->rol === 'admin') $allowed[] = 'costo_compra_real';
        $changes = array_intersect_key($data, array_flip($allowed));
        $this->assertState($row->estado, $changes['estado'] ?? $row->estado);
        if (($changes['estado'] ?? null) === 'listo' && $row->estado !== 'listo') $changes['listo_at'] = now();
        $changes['actualizado_por_id'] = $user->id;
        $row->update($changes);
        return $row->fresh();
    }

    public function allReady(Pedido $pedido): bool { return !$pedido->materiales()->exists() || !$pedido->materiales()->where('estado', '!=', 'listo')->exists(); }
    public function realCost(Pedido $pedido): string { $total = '0.00'; foreach ($pedido->materiales as $material) $total = DecimalMoney::add($total, (string) $material->costo_compra_real); return $total; }
    private function assertState(string $current, string $next): void { if ($current === $next) return; if (!in_array([$current, $next], [['pendiente', 'parcial'], ['pendiente', 'listo'], ['parcial', 'listo']], true)) abort(409, 'La transición del material no es válida.'); }
}
