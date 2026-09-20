<?php

namespace App\Http\Resources\V1;

use App\Support\DecimalMoney;
use Illuminate\Http\Resources\Json\JsonResource;

class PedidoResource extends JsonResource
{
    public function toArray($request): array
    {
        $role = $request->user()?->rol;
        $internal = in_array($role, ['admin', 'socio'], true);
        $commercial = in_array($role, ['admin', 'socio', 'vendedor'], true);
        $operational = $role !== 'vendedor';
        $data = [
            'id' => $this->id,
            'numero' => $this->numero,
            'proforma_id' => $this->proforma_id,
            'proforma_numero' => $this->proforma_numero_snapshot,
            'estado' => $this->estado,
            'cliente_id' => $this->cliente_id,
            'nombre_cliente' => $this->nombre_cliente,
            'ruc' => $this->ruc,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'vendedor_id' => $this->vendedor_id,
            'vendedor' => $this->whenLoaded('vendedor', fn () => ['id' => $this->vendedor?->id, 'nombre' => $this->vendedor?->nombre]),
            'fecha_pedido' => $this->fecha_pedido?->format('Y-m-d'),
            'fecha_entrega_estimada' => $this->fecha_entrega_estimada?->format('Y-m-d'),
            'fecha_inicio_produccion' => $this->fecha_inicio_produccion,
            'fecha_terminado' => $this->fecha_terminado,
            'observaciones_cliente' => $this->observaciones_cliente,
            'observaciones_internas' => $this->when($internal || $role === 'almacenista', $this->observaciones_internas),
            'detalles' => PedidoDetalleResource::collection($this->whenLoaded('detalles')),
            'servicios_pedido' => PedidoServicioResource::collection($this->whenLoaded('servicios')),
            'materiales' => $this->when($operational, fn () => PedidoMaterialResource::collection($this->whenLoaded('materiales'))),
            'procesos' => $this->when($operational, fn () => PedidoProcesoResource::collection($this->whenLoaded('procesos'))),
            'progreso' => $this->progress(),
            'historial' => PedidoHistorialResource::collection($this->whenLoaded('historial')),
        ];
        if ($commercial) { $data['total'] = $this->total; $data['resumen_comercial'] = ['subtotal_productos' => $this->subtotal_productos, 'subtotal_servicios' => $this->subtotal_servicios, 'descuento_total' => $this->descuento_total, 'base_neta' => $this->base_neta, 'monto_iva' => $this->monto_iva, 'monto_ir' => $this->monto_ir, 'total' => $this->total]; }
        if ($internal) $data['analisis_interno'] = ['subtotal_productos' => $this->subtotal_productos, 'subtotal_servicios' => $this->subtotal_servicios, 'subtotal_bruto' => $this->subtotal_bruto, 'descuento_total' => $this->descuento_total, 'base_neta' => $this->base_neta, 'aplica_iva' => $this->aplica_iva, 'tasa_iva' => $this->tasa_iva, 'monto_iva' => $this->monto_iva, 'aplica_ir' => $this->aplica_ir, 'tasa_ir' => $this->tasa_ir, 'monto_ir' => $this->monto_ir, 'tasa_comision_vendedor' => $this->tasa_comision_vendedor, 'monto_comision_vendedor' => $this->monto_comision_vendedor, 'costo_materiales_estimado' => $this->costo_materiales_estimado, 'costo_mano_obra_estimado' => $this->costo_mano_obra_estimado, 'costo_servicios_base_estimado' => $this->costo_servicios_base_estimado, 'costo_total_estimado' => $this->costo_total_estimado, 'costo_compra_estimado' => $this->costo_compra_estimado, 'costo_compra_real_total' => $this->realPurchaseCost(), 'utilidad_estimada' => $this->utilidad_estimada, 'total' => $this->total];
        return $data;
    }

    private function progress(): array
    {
        $materials = $this->relationLoaded('materiales') ? $this->materiales : null;
        $processes = $this->relationLoaded('procesos') ? $this->procesos : null;
        $materialsTotal = $materials?->count() ?? (int) ($this->materiales_total ?? 0);
        $materialsReady = $materials?->where('estado', 'listo')->count() ?? (int) ($this->materiales_listos ?? 0);
        $processesTotal = $processes?->count() ?? (int) ($this->procesos_total ?? 0);
        $processesDone = $processes?->where('estado', 'completado')->count() ?? (int) ($this->procesos_completados ?? 0);
        return ['materiales_total' => $materialsTotal, 'materiales_listos' => $materialsReady, 'procesos_total' => $processesTotal, 'procesos_completados' => $processesDone, 'porcentaje_materiales' => $materialsTotal ? round($materialsReady * 100 / $materialsTotal, 2) : 100, 'porcentaje_produccion' => $processesTotal ? round($processesDone * 100 / $processesTotal, 2) : 100];
    }

    private function realPurchaseCost(): string
    {
        if (!$this->relationLoaded('materiales') && array_key_exists('costo_compra_real_total', $this->getAttributes())) {
            $aggregate = $this->getAttribute('costo_compra_real_total');
            return $aggregate === null ? '0.00' : DecimalMoney::add('0', (string) $aggregate);
        }
        $total = '0.00';
        foreach (($this->relationLoaded('materiales') ? $this->materiales : collect()) as $material) $total = DecimalMoney::add($total, (string) $material->costo_compra_real);
        return $total;
    }
}
