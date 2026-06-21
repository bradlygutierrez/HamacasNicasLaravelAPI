<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HamacaVarianteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hamaca_id' => $this->hamaca_id,
            'nombre' => $this->nombre,
            'composicion_clave' => $this->composicion_clave,
            'state' => (bool) $this->state,

            'hamaca' => $this->whenLoaded('hamaca', function () {
                return [
                    'id' => $this->hamaca->id,
                    'nombre' => $this->hamaca->nombre,
                    'descripcion' => $this->hamaca->descripcion,
                    'precio' => $this->hamaca->precio,
                    'categoria_id' => $this->hamaca->categoria_id,
                    'tamano_id' => $this->hamaca->tamano_id,

                    'categoria' => $this->hamaca->relationLoaded('categoria') && $this->hamaca->categoria
                        ? [
                            'id' => $this->hamaca->categoria->id,
                            'nombre' => $this->hamaca->categoria->nombre,
                        ]
                        : null,

                    'tamano' => $this->hamaca->relationLoaded('tamano') && $this->hamaca->tamano
                        ? [
                            'id' => $this->hamaca->tamano->id,
                            'nombre' => $this->hamaca->tamano->nombre,
                        ]
                        : null,
                ];
            }),

            'colores' => $this->whenLoaded('colores', function () {
                return $this->colores->map(fn ($color) => [
                    'id' => $color->id,
                    'nombre' => $color->nombre,
                ])->values();
            }),

            'fotos' => $this->whenLoaded('fotos', function () {
                return $this->fotos->map(fn ($foto) => [
                    'id' => $foto->id,
                    'ruta' => $foto->ruta,
                ])->values();
            }),

            'inventarios' => $this->whenLoaded('inventarios', function () {
                return $this->inventarios->map(fn ($inventario) => [
                    'id' => $inventario->id,
                    'cantidad' => $inventario->cantidad,
                    'ubicacion' => $inventario->relationLoaded('ubicacion') && $inventario->ubicacion
                        ? [
                            'id' => $inventario->ubicacion->id,
                            'nombre' => $inventario->ubicacion->nombre,
                        ]
                        : null,
                    'usuario' => $inventario->relationLoaded('usuario') && $inventario->usuario
                        ? [
                            'id' => $inventario->usuario->id,
                            'nombre' => $inventario->usuario->nombre,
                        ]
                        : null,
                ])->values();
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}