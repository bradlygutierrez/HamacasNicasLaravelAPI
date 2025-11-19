<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FacturaCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'data' => $this -> collection, 
            'meta' =>[
                'Organization' => 'Hamacas Nica',
                'author' => 'Bradly Gutierrez',
            ],
            'type' => 'Facturas Collection',
        ];
    }
}
