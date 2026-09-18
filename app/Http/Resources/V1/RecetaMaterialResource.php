<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class RecetaMaterialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'cantidad' => $this->cantidad,
            'porcentaje_merma' => $this->porcentaje_merma,
            'material' => new MaterialResource($this->whenLoaded('material')),
        ];
    }
}
