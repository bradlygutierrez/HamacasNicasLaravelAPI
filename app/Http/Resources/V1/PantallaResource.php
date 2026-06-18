<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PantallaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            'ruta' => $this->ruta,
            'icono' => $this->icono,
            'orden' => (int) $this->orden,
            'state' => (bool) $this->state,
            'permisos' => PermisoResource::collection($this->whenLoaded('permisos')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
