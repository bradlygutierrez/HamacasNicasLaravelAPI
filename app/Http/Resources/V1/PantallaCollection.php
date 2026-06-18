<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PantallaCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => PantallaResource::collection($this->collection),
            'meta' => [
                'Organization' => 'Hamacas Nica',
                'author' => 'Bradly Gutierrez',
            ],
            'type' => 'Pantallas Collection',
            'message' => $this->collection->isEmpty()
                ? 'No hay pantallas disponibles.'
                : null,
        ];
    }
}
