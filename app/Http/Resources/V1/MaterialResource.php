<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class MaterialResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'unidad_consumo' => $this->unidad_consumo,
            'unidad_compra' => $this->unidad_compra,
            'contenido_por_compra' => $this->contenido_por_compra,
            'precio_actual' => $this->precio_actual,
            'porcentaje_merma' => $this->porcentaje_merma,
            'state' => $this->state,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
