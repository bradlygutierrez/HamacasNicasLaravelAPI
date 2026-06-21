<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioHamacaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hamaca_id' => $this->hamaca_id,
            'hamaca_variante_id' => $this->hamaca_variante_id,
            'usuario_id' => $this->usuario_id,
            'ubicacion_id' => $this->ubicacion_id,
            'composicion_clave' => $this->composicion_clave,
            'cantidad' => $this->cantidad,

            'hamaca' => $this->whenLoaded('hamaca', function () {
                return [
                    'id' => $this->hamaca->id,
                    'nombre' => $this->hamaca->nombre,
                    'descripcion' => $this->hamaca->descripcion,
                    'precio' => $this->hamaca->precio,

                    'categoria' => $this->hamaca->categoria
                        ? [
                            'id' => $this->hamaca->categoria->id,
                            'nombre' => $this->hamaca->categoria->nombre,
                        ]
                        : null,

                    'tamano' => $this->hamaca->tamano
                        ? [
                            'id' => $this->hamaca->tamano->id,
                            'nombre' => $this->hamaca->tamano->nombre,
                        ]
                        : null,

                    // Legacy: fotos viejas asociadas directo a hamaca
                    'fotos' => $this->hamaca->relationLoaded('fotos')
                        ? $this->hamaca->fotos->map(fn ($foto) => [
                            'id' => $foto->id,
                            'ruta' => $foto->ruta,
                        ])->values()
                        : [],
                ];
            }),

            'variante' => $this->whenLoaded('variante', function () {
                return [
                    'id' => $this->variante->id,
                    'nombre' => $this->variante->nombre,
                    'hamaca_id' => $this->variante->hamaca_id,
                    'composicion_clave' => $this->variante->composicion_clave,
                    'state' => (bool) $this->variante->state,

                    'colores' => $this->variante->relationLoaded('colores')
                        ? $this->variante->colores->map(fn ($color) => [
                            'id' => $color->id,
                            'nombre' => $color->nombre,
                        ])->values()
                        : [],

                    'fotos' => $this->variante->relationLoaded('fotos')
                        ? $this->variante->fotos->map(fn ($foto) => [
                            'id' => $foto->id,
                            'ruta' => $foto->ruta,
                        ])->values()
                        : [],
                ];
            }),

            'ubicacion' => $this->whenLoaded('ubicacion', function () {
                return [
                    'id' => $this->ubicacion->id,
                    'nombre' => $this->ubicacion->nombre,
                ];
            }),

            'usuario' => $this->whenLoaded('usuario', function () {
                return [
                    'id' => $this->usuario->id,
                    'nombre' => $this->usuario->nombre,
                    'rol' => $this->usuario->rol,
                ];
            }),

            // Legacy: colores directos del inventario
            'colores' => $this->whenLoaded('colores', function () {
                return $this->colores->map(fn ($color) => [
                    'id' => $color->id,
                    'nombre' => $color->nombre,
                ])->values();
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}