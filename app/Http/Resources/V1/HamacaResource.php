<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HamacaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'categoria' => $this->categoria ? $this->categoria->nombre : null,
            'tamano' => $this->tamano ? $this->tamano->nombre : null,
            'precio' => $this->precio,
            'colores' => $this->whenLoaded('colores', function () {
                return $this->colores->map(function ($color) {
                    return [
                        'id' => $color->id,
                        'nombre' => $color->nombre,
                    ];
                });
            }),

            'fotos' => $this->whenLoaded('fotos', function () {
                return $this->fotos->map(function ($foto) {
                    return [
                        'id' => $foto->id,
                        'ruta' => $foto->ruta,
                    ];
                });
            }),

            'inventario' => $this->whenLoaded('inventarios', function () {
                return $this->inventarios->map(function ($inventario) {
                    return [
                        'id' => $inventario->id,

                        'cantidad' => $inventario->cantidad,

                        'ubicacion' => $inventario->ubicacion
                            ? [
                                'id' => $inventario->ubicacion->id,
                                'nombre' => $inventario->ubicacion->nombre,
                            ]
                            : null,

                        'usuario' => $inventario->usuario
                            ? [
                                'id' => $inventario->usuario->id,
                                'nombre' => $inventario->usuario->nombre,
                            ]
                            : null,

                        'colores' => $inventario->relationLoaded('colores')
                            ? $inventario->colores->map(function ($color) {
                                return [
                                    'id' => $color->id,
                                    'nombre' => $color->nombre,
                                ];
                            })
                            : [],
                    ];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
