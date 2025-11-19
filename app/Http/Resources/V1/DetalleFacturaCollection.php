<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DetalleFacturaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => DetalleFacturaResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'organization' => 'Hamacas Nica',
                'author' => 'Bradly Gutierrez',
            ],
            'type' => 'Detalle Factura Collection',
        ];
    }
}
