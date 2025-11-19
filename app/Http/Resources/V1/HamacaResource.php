<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HamacaResource extends JsonResource
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
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'categoria' => $this->categoria ? $this->categoria->nombre : null,
            'ubicacion' => $this->ubicacion ? $this->ubicacion->nombre : null,
            'tamano' => $this->tamano ? $this->tamano->nombre : null,
            'cantidad' => $this->cantidad,
            'precio' => $this->precio,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
