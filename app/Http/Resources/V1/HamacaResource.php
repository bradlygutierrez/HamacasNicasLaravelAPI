<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HamacaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'nombre' => $this->nombre, 'descripcion' => $this->descripcion,
            'categoria_id' => $this->categoria_id, 'tamano_id' => $this->tamano_id,
            'categoria' => $this->whenLoaded('categoria', fn () => $this->categoria?->nombre),
            'tamano' => $this->whenLoaded('tamano', fn () => $this->tamano?->nombre), 'precio' => $this->precio,
            'colores' => $this->whenLoaded('colores', fn () => $this->colores->map(fn ($color) => ['id' => $color->id, 'nombre' => $color->nombre])->values()),
            'fotos' => $this->whenLoaded('fotos', fn () => $this->fotos->map(fn ($foto) => ['id' => $foto->id, 'ruta' => $foto->ruta])->values()),
            'inventario' => $this->whenLoaded('inventarios', fn () => $this->inventarios->map(fn ($stock) => [
                'id' => $stock->id, 'hamaca_id' => $stock->hamaca_id, 'usuario_id' => $stock->usuario_id,
                'ubicacion_id' => $stock->ubicacion_id, 'cantidad' => $stock->cantidad,
                'ubicacion' => $stock->relationLoaded('ubicacion') && $stock->ubicacion ? ['id' => $stock->ubicacion->id, 'nombre' => $stock->ubicacion->nombre] : null,
                'usuario' => $stock->relationLoaded('usuario') && $stock->usuario ? ['id' => $stock->usuario->id, 'nombre' => $stock->usuario->nombre, 'rol' => $stock->usuario->rol] : null,
            ])->values()),
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
