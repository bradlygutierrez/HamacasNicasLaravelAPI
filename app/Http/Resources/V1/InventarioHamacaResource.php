<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioHamacaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'hamaca_id' => $this->hamaca_id, 'usuario_id' => $this->usuario_id, 'ubicacion_id' => $this->ubicacion_id, 'cantidad' => $this->cantidad,
            'hamaca' => $this->whenLoaded('hamaca', fn () => ['id' => $this->hamaca->id, 'nombre' => $this->hamaca->nombre, 'descripcion' => $this->hamaca->descripcion, 'precio' => $this->hamaca->precio, 'categoria' => $this->hamaca->categoria?->only(['id', 'nombre']), 'tamano' => $this->hamaca->tamano?->only(['id', 'nombre']), 'colores' => $this->hamaca->colores?->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values(), 'fotos' => $this->hamaca->fotos?->map(fn ($f) => ['id' => $f->id, 'ruta' => $f->ruta])->values()]),
            'ubicacion' => $this->whenLoaded('ubicacion', fn () => $this->ubicacion?->only(['id', 'nombre'])), 'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario?->only(['id', 'nombre', 'rol'])), 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
