<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class RecetaManoObraResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'proceso_produccion_id' => $this->proceso_produccion_id,
            'costo_unitario' => $this->costo_unitario,
            'orden' => $this->orden,
            'proceso' => new ProcesoProduccionResource($this->whenLoaded('proceso')),
        ];
    }
}
