<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PantallaPermisoRolCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => PantallaPermisoRolResource::collection($this->collection),
            'meta' => [
                'Organization' => 'Hamacas Nica',
                'author' => 'Bradly Gutierrez',
            ],
            'type' => 'Pantalla Permiso Roles Collection',
            'message' => $this->collection->isEmpty()
                ? 'No hay accesos configurados.'
                : null,
        ];
    }
}
