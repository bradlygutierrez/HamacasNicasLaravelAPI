<?php

namespace App\Http\Resources\V1;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UsuarioCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray($request): array
    {
        return [
        'data' => $this->collection,
        'meta' => [
            'Organization' => 'Hamacas Nica',
            'author' => 'Bradly Gutierrez',
        ],
        'type' => 'Usuarios Collection',
        'message' => $this->collection->isEmpty()
            ? 'No hay usuarios activos disponibles.'
            : null,
    ];
    }
}
