<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HamacaResource extends JsonResource
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
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,

            'categoria_id' => $this->categoria_id,
            'tamano_id' => $this->tamano_id,

            'categoria' => $this->whenLoaded(
                'categoria',
                fn () => $this->categoria?->nombre
            ),

            'tamano' => $this->whenLoaded(
                'tamano',
                fn () => $this->tamano?->nombre
            ),

            'precio' => $this->precio,

            // Legacy: fotos directas de la hamaca
            'fotos' => $this->whenLoaded(
                'fotos',
                fn () => $this->fotos->map(fn ($foto) => [
                    'id' => $foto->id,
                    'ruta' => $foto->ruta,
                ])->values()
            ),

            // Nuevo: variantes del modelo
            'variantes' => $this->whenLoaded(
                'variantes',
                fn () => $this->variantes->map(fn ($variante) => [
                    'id' => $variante->id,
                    'nombre' => $variante->nombre,
                    'hamaca_id' => $variante->hamaca_id,
                    'composicion_clave' => $variante->composicion_clave,
                    'state' => (bool) $variante->state,

                    'colores' => $variante->relationLoaded('colores')
                        ? $variante->colores->map(fn ($color) => [
                            'id' => $color->id,
                            'nombre' => $color->nombre,
                        ])->values()
                        : [],

                    'fotos' => $variante->relationLoaded('fotos')
                        ? $variante->fotos->map(fn ($foto) => [
                            'id' => $foto->id,
                            'ruta' => $foto->ruta,
                        ])->values()
                        : [],
                ])->values()
            ),

            // Inventario real disponible
            'inventario' => $this->whenLoaded(
                'inventarios',
                fn () => $this->inventarios->map(function ($inventario) {
                    return [
                        'id' => $inventario->id,
                        'hamaca_id' => $inventario->hamaca_id,
                        'hamaca_variante_id' => $inventario->hamaca_variante_id,
                        'usuario_id' => $inventario->usuario_id,
                        'ubicacion_id' => $inventario->ubicacion_id,
                        'composicion_clave' => $inventario->composicion_clave,
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
                                'rol' => $inventario->usuario->rol,
                            ]
                            : null,

                        // Legacy: colores directos del inventario
                        'colores' => $inventario->relationLoaded('colores')
                            ? $inventario->colores->map(fn ($color) => [
                                'id' => $color->id,
                                'nombre' => $color->nombre,
                            ])->values()
                            : [],

                        // Nuevo: variante real del inventario
                        'variante' => $inventario->relationLoaded('variante') && $inventario->variante
                            ? [
                                'id' => $inventario->variante->id,
                                'nombre' => $inventario->variante->nombre,
                                'hamaca_id' => $inventario->variante->hamaca_id,
                                'composicion_clave' => $inventario->variante->composicion_clave,
                                'state' => (bool) $inventario->variante->state,

                                'colores' => $inventario->variante->relationLoaded('colores')
                                    ? $inventario->variante->colores->map(fn ($color) => [
                                        'id' => $color->id,
                                        'nombre' => $color->nombre,
                                    ])->values()
                                    : [],

                                'fotos' => $inventario->variante->relationLoaded('fotos')
                                    ? $inventario->variante->fotos->map(fn ($foto) => [
                                        'id' => $foto->id,
                                        'ruta' => $foto->ruta,
                                    ])->values()
                                    : [],
                            ]
                            : null,
                    ];
                })->values()
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}