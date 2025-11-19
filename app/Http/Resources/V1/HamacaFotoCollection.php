<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\ResourceCollection;

class HamacaFotoCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => HamacaFotoResource::collection($this->collection),
            'meta' => [
                'total' => $this->collection->count(),
                'organization' => 'Hamacas Nica',
                'author' => 'Bradly Gutierrez',
            ],
            'type' => 'HamacaFotoCollection',
        ];
    }
}