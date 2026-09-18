<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProformaResource extends JsonResource
{
    public function toArray($request): array
    {
        $internal = in_array($request->user()?->rol, ['admin', 'socio'], true);
        $analysis = $internal ? ['tasa_comision_vendedor' => $this->tasa_comision_vendedor, 'monto_comision_vendedor' => $this->monto_comision_vendedor, 'costo_materiales_estimado' => $this->costo_materiales_estimado, 'costo_mano_de_obra_estimado' => $this->costo_mano_de_obra_estimado, 'costo_servicios_base_estimado' => $this->costo_servicios_base_estimado, 'costo_total_estimado' => $this->costo_total_estimado, 'costo_compra_estimado' => $this->costo_compra_estimado, 'utilidad_estimada' => $this->utilidad_estimada, 'materiales' => $this->whenLoaded('materialesSnapshot'), 'mano_obra' => $this->whenLoaded('manoObraSnapshot'), 'materiales_agrupados' => $this->materiales_agrupados()] : null;
        return ['id' => $this->id, 'numero' => $this->numero, 'estado' => $this->estado, 'cliente_id' => $this->cliente_id, 'vendedor_id' => $this->vendedor_id, 'cliente' => $this->nombre_cliente, 'nombre_cliente' => $this->nombre_cliente, 'ruc' => $this->ruc, 'direccion' => $this->direccion, 'telefono' => $this->telefono, 'correo' => $this->correo, 'fecha' => $this->fecha, 'valida_hasta' => $this->valida_hasta, 'observaciones' => $this->observaciones, 'subtotal_productos' => $this->subtotal_productos, 'subtotal_servicios' => $this->subtotal_servicios, 'subtotal_bruto' => $this->subtotal_bruto, 'descuento_global' => $this->descuento_global ?? $this->descuento, 'descuento_lineas' => $this->descuento_lineas ?? 0, 'descuento_total' => $this->descuento_total ?? $this->descuento, 'descuento' => $this->descuento_total ?? $this->descuento, 'base_neta' => $this->base_neta, 'aplica_iva' => $this->aplica_iva, 'tasa_iva' => $this->tasa_iva, 'monto_iva' => $this->monto_iva, 'aplica_ir' => $this->aplica_ir, 'tasa_ir' => $this->tasa_ir, 'monto_ir' => $this->monto_ir, 'tasa_comision_vendedor' => $this->tasa_comision_vendedor, 'monto_comision_vendedor' => $this->monto_comision_vendedor, 'total' => $this->total, 'vendedor' => $this->whenLoaded('vendedor', fn () => ['id' => $this->vendedor?->id, 'nombre' => $this->vendedor?->nombre]), 'detalles' => ProformaDetalleResource::collection($this->whenLoaded('detalles')), 'servicios_pedido' => ProformaServicioResource::collection($this->whenLoaded('servicios')), 'analisis_interno' => $analysis, 'emitida_at' => $this->emitida_at];
    }

    private function materiales_agrupados(): array
    {
        if (!$this->relationLoaded('materialesSnapshot')) return [];
        $groups = [];
        foreach ($this->materialesSnapshot as $snapshot) {
            $key = (string) $snapshot->material_id;
            if (!isset($groups[$key])) $groups[$key] = ['material_id' => $snapshot->material_id, 'nombre' => $snapshot->material_nombre_snapshot, 'unidad_consumo' => $snapshot->unidad_consumo_snapshot, 'cantidad_requerida' => 0.0, 'unidad_compra' => $snapshot->unidad_compra_snapshot, 'contenido_por_compra' => (float) $snapshot->contenido_por_compra_snapshot, 'cantidad_compra' => 0, 'precio_compra' => (float) $snapshot->precio_compra_snapshot, 'costo_consumo' => 0.0, 'costo_compra' => 0.0];
            $groups[$key]['cantidad_requerida'] += (float) $snapshot->cantidad_total_con_merma; $groups[$key]['costo_consumo'] += (float) $snapshot->costo_consumo_total;
        }
        foreach ($groups as &$group) { $group['cantidad_compra'] = (int) ceil($group['cantidad_requerida'] / $group['contenido_por_compra']); $group['costo_consumo'] = number_format($group['costo_consumo'], 2, '.', ''); $group['costo_compra'] = number_format($group['cantidad_compra'] * $group['precio_compra'], 2, '.', ''); $group['cantidad_requerida'] = number_format($group['cantidad_requerida'], 4, '.', ''); }
        return array_values($groups);
    }
}
