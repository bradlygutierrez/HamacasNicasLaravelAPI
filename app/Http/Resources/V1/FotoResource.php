<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FotoResource extends JsonResource
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
            'ruta' => $this->ruta,

            // Relación vieja, temporal/legacy
            'hamacas' => $this->whenLoaded(
                'hamacas',
                fn () => HamacaResource::collection($this->hamacas)
            ),

            // Nueva relación correcta: fotos por variante
            'variantes' => $this->whenLoaded(
                'variantes',
                fn () => $this->variantes->map(function ($variante) {
                    return [
                        'id' => $variante->id,
                        'nombre' => $variante->nombre,
                        'hamaca_id' => $variante->hamaca_id,
                        'composicion_clave' => $variante->composicion_clave,
                        'state' => (bool) $variante->state,

                        'hamaca' => $variante->relationLoaded('hamaca') && $variante->hamaca
                            ? [
                                'id' => $variante->hamaca->id,
                                'nombre' => $variante->hamaca->nombre,
                                'descripcion' => $variante->hamaca->descripcion,
                                'precio' => $variante->hamaca->precio,
                            ]
                            : null,

                        'colores' => $variante->relationLoaded('colores')
                            ? $variante->colores->map(fn ($color) => [
                                'id' => $color->id,
                                'nombre' => $color->nombre,
                            ])->values()
                            : [],
                    ];
                })->values()
            ),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}