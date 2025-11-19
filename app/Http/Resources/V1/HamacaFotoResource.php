<?php

namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;

class HamacaFotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'hamaca' => $this->hamaca ? $this->hamaca->only(['id', 'nombre']) : null,
            'url' => $this->ruta_foto,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}