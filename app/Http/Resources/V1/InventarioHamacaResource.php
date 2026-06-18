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

            'cantidad' => $this->cantidad,

            /*
            |--------------------------------------------------------------------------
            | HAMACA
            |--------------------------------------------------------------------------
            */

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

                    'fotos' => $this->hamaca->relationLoaded('fotos')
                        ? $this->hamaca->fotos->map(function ($foto) {

                            return [
                                'id' => $foto->id,
                                'ruta' => $foto->ruta,
                            ];
                        })
                        : [],
                ];
            }),

            /*
            |--------------------------------------------------------------------------
            | UBICACION
            |--------------------------------------------------------------------------
            */

            'ubicacion' => $this->whenLoaded('ubicacion', function () {

                return [
                    'id' => $this->ubicacion->id,
                    'nombre' => $this->ubicacion->nombre,
                ];
            }),

            /*
            |--------------------------------------------------------------------------
            | USUARIO
            |--------------------------------------------------------------------------
            */

            'usuario' => $this->whenLoaded('usuario', function () {

                return [
                    'id' => $this->usuario->id,
                    'nombre' => $this->usuario->nombre,
                    'rol' => $this->usuario->rol,
                ];
            }),

            'colores' => $this->whenLoaded('colores', function () {
                return $this->colores->map(function ($color) {
                    return [
                        'id' => $color->id,
                        'nombre' => $color->nombre,
                    ];
                });
            }),

            /*
            |--------------------------------------------------------------------------
            | FECHAS
            |--------------------------------------------------------------------------
            */

            'created_at' => $this->created_at,

            'updated_at' => $this->updated_at,
        ];
    }
}
