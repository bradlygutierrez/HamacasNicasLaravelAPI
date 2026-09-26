<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PedidoDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'hamaca_id' => $this->hamaca_id, 'nombre' => $this->hamaca_nombre_snapshot, 'cantidad' => $this->cantidad, 'receta_version_snapshot' => $this->receta_version_snapshot, 'servicios' => PedidoDetalleServicioResource::collection($this->whenLoaded('servicios'))];
    }
}
