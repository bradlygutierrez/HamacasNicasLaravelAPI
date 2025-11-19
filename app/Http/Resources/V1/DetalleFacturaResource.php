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
            'hamaca' => $this->hamaca ? $this->hamaca->nombre : null,
            'cantidad' => $this->cantidad,
            'precio_unitario' => $this->precio_unitario,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
