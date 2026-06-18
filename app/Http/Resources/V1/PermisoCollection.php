<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PermisoCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => PermisoResource::collection($this->collection),
            'meta' => [
                'Organization' => 'Hamacas Nica',
                'author' => 'Bradly Gutierrez',
            ],
            'type' => 'Permisos Collection',
            'message' => $this->collection->isEmpty()
                ? 'No hay permisos disponibles.'
                : null,
        ];
    }
}
