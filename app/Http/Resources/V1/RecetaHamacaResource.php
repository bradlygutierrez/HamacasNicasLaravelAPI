<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class RecetaHamacaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'hamaca_id' => $this->hamaca_id,
            'hamaca_variante_id' => $this->hamaca_variante_id,
            'version' => (int) $this->version,
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'usuario_id' => $this->usuario_id,
            'activado_por_id' => $this->activado_por_id,
            'activated_at' => $this->activated_at,
            'hamaca' => new HamacaResource($this->whenLoaded('hamaca')),
            'variante' => new HamacaVarianteResource($this->whenLoaded('hamacaVariante')),
            'materiales' => RecetaMaterialResource::collection($this->whenLoaded('detallesMateriales')),
            'mano_obra' => RecetaManoObraResource::collection($this->whenLoaded('detallesManoObra')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
