<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PantallaPermisoRolResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'pantalla_id' => $this->pantalla_id,
            'permiso_id' => $this->permiso_id,
            'rol' => $this->rol,
            'pantalla' => new PantallaResource($this->whenLoaded('pantalla')),
            'permiso' => new PermisoResource($this->whenLoaded('permiso')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
