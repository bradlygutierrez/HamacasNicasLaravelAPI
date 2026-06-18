<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovimientoResource extends JsonResource
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
            'usuario' => $this->usuario_id ? $this->usuario->nombre : null,
            'inventario_hamaca_id' => $this->inventario_hamaca_id,
            'tipo' => $this->tipo,
            'cantidad' => $this->cantidad,
            'fecha' => $this->fecha,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
