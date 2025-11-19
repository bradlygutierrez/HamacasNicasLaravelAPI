<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UbicacionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => UbicacionResource::collection($this->collection),
            'organization' => 'Hamacas Nica',
            'author' => 'Bradly Gutierrez',
            'type' => 'Ubicacion Collection Resource',
        ];
    }
}
