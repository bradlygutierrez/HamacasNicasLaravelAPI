<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ServicioAdicionalResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'alcance' => $this->alcance,
            'metodo_calculo' => $this->metodo_calculo,
            'unidad' => $this->unidad,
            'precio_venta_actual' => $this->precio_venta_actual,
            'costo_actual' => $this->costo_actual,
            'state' => $this->state,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
