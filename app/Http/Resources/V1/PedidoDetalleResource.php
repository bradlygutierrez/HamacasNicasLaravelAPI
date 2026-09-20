<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PedidoDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        $role = $request->user()?->rol;
        $commercial = in_array($role, ['admin', 'socio', 'vendedor'], true);
        $internal = in_array($role, ['admin', 'socio'], true);
        $data = ['id' => $this->id, 'nombre' => $this->hamaca_nombre_snapshot, 'cantidad' => $this->cantidad, 'receta_version_snapshot' => $this->receta_version_snapshot, 'servicios' => PedidoDetalleServicioResource::collection($this->whenLoaded('servicios'))];
        if ($commercial) $data += ['precio_unitario' => $this->precio_unitario, 'descuento' => $this->descuento, 'subtotal' => $this->subtotal];
        if ($internal) $data += ['costo_unitario_estimado' => $this->costo_unitario_estimado, 'costo_total_estimado' => $this->costo_total_estimado];
        return $data;
    }
}
