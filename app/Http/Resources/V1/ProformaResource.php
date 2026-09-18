<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ProformaResource extends JsonResource
{
    public function toArray($request): array
    {
        $internal = in_array($request->user()?->rol, ['admin', 'socio'], true);
        return ['id' => $this->id, 'numero' => $this->numero, 'estado' => $this->estado, 'cliente_id' => $this->cliente_id, 'vendedor_id' => $this->vendedor_id, 'cliente' => $this->nombre_cliente, 'nombre_cliente' => $this->nombre_cliente, 'ruc' => $this->ruc, 'direccion' => $this->direccion, 'telefono' => $this->telefono, 'correo' => $this->correo, 'fecha' => $this->fecha, 'valida_hasta' => $this->valida_hasta, 'observaciones' => $this->observaciones, 'subtotal_productos' => $this->subtotal_productos, 'subtotal_servicios' => $this->subtotal_servicios, 'subtotal_bruto' => $this->subtotal_bruto, 'descuento' => $this->descuento, 'base_neta' => $this->base_neta, 'aplica_iva' => $this->aplica_iva, 'tasa_iva' => $this->tasa_iva, 'monto_iva' => $this->monto_iva, 'aplica_ir' => $this->aplica_ir, 'tasa_ir' => $this->tasa_ir, 'monto_ir' => $this->monto_ir, 'total' => $this->total, 'vendedor' => $this->whenLoaded('vendedor', fn () => ['id' => $this->vendedor?->id, 'nombre' => $this->vendedor?->nombre]), 'detalles' => ProformaDetalleResource::collection($this->whenLoaded('detalles')), 'servicios_pedido' => ProformaServicioResource::collection($this->whenLoaded('servicios')), 'analisis_interno' => $internal ? ['tasa_comision_vendedor' => $this->tasa_comision_vendedor, 'monto_comision_vendedor' => $this->monto_comision_vendedor, 'costo_materiales_estimado' => $this->costo_materiales_estimado, 'costo_mano_de_obra_estimado' => $this->costo_mano_de_obra_estimado, 'costo_servicios_base_estimado' => $this->costo_servicios_base_estimado, 'costo_total_estimado' => $this->costo_total_estimado, 'costo_compra_estimado' => $this->costo_compra_estimado, 'utilidad_estimada' => $this->utilidad_estimada, 'materiales' => $this->whenLoaded('materialesSnapshot'), 'mano_obra' => $this->whenLoaded('manoObraSnapshot')] : null, 'emitida_at' => $this->emitida_at];
    }
}
