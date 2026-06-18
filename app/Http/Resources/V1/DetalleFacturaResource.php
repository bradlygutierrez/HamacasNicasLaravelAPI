<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetalleFacturaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'factura_id' => $this->factura_id,
            'inventario_hamaca_id' => $this->inventario_hamaca_id,
            'hamaca' => $this->hamaca_nombre,
            'descripcion' => $this->hamaca_descripcion,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'subtotal' => $this->subtotal,
            'colores' => $this->colores_snapshot ? json_decode($this->colores_snapshot, true) : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
