<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\PedidoProceso;
use App\Models\Usuario;
use App\Support\DecimalMoney;

class PedidoProcesoService
{
    public function createPlan(Pedido $pedido, $snapshots): void
    {
        $groups = [];
        foreach ($snapshots as $snapshot) {
            $key = $snapshot->proceso_produccion_id !== null ? 'proceso:' . $snapshot->proceso_produccion_id : 'snapshot:' . $snapshot->proceso_nombre_snapshot;
            if (!isset($groups[$key])) $groups[$key] = ['proceso_produccion_id' => $snapshot->proceso_produccion_id, 'proceso_nombre_snapshot' => $snapshot->proceso_nombre_snapshot, 'orden' => $snapshot->orden, 'costo_estimado' => '0.00'];
            if ($snapshot->orden !== null && ($groups[$key]['orden'] === null || $snapshot->orden < $groups[$key]['orden'])) $groups[$key]['orden'] = $snapshot->orden;
            $groups[$key]['costo_estimado'] = DecimalMoney::add($groups[$key]['costo_estimado'], (string) $snapshot->costo_total);
        }
        foreach ($groups as $group) $pedido->procesos()->create($group);
    }

    public function update(Pedido $pedido, PedidoProceso $process, array $data, Usuario $user): PedidoProceso
    {
        if ($pedido->estado !== 'en_produccion') abort(409, 'El pedido aún no está en producción.');
        $row = $pedido->procesos()->findOrFail($process->id); $next = $data['estado'] ?? $row->estado;
        if ($row->estado !== $next && !in_array([$row->estado, $next], [['pendiente', 'en_proceso'], ['pendiente', 'completado'], ['en_proceso', 'completado']], true)) abort(409, 'La transición del proceso no es válida.');
        $changes = array_intersect_key($data, array_flip(['estado', 'observaciones'])); if ($next === 'en_proceso' && !$row->iniciado_at) $changes['iniciado_at'] = now(); if ($next === 'completado') { if (!$row->iniciado_at) $changes['iniciado_at'] = now(); $changes['completado_at'] = now(); } $changes['actualizado_por_id'] = $user->id; $row->update($changes); return $row->fresh();
    }
    public function allComplete(Pedido $pedido): bool { return !$pedido->procesos()->exists() || !$pedido->procesos()->where('estado', '!=', 'completado')->exists(); }
}
