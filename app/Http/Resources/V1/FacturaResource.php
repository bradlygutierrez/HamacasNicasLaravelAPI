<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacturaResource extends JsonResource
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
            'numero' => $this->numero,
            'cliente' => $this->cliente ? [
                'id' => $this->cliente->id,
                'nombre' => $this->cliente->nombre,
                'ruc' => $this->cliente->ruc,
                'direccion' => $this->cliente->direccion,
            ] : null,
            'vendedor' => $this->usuario ? $this->usuario->nombre : null,
            'canal' => $this->canal,
            'nombre_cliente' => $this->nombre_cliente,
            'ruc' => $this->ruc,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'metodo_pago' => $this->metodo_pago,
            'subtotal' => $this->subtotal,
            'descuento' => $this->descuento,
            'monto_iva' => $this->monto_iva,
            'monto_ir' => $this->monto_ir,
            'total' => $this->total,
            'fecha' => $this->fecha,
            'detalles' => $this->whenLoaded(
                'detalles',
                fn () => DetalleFacturaResource::collection($this->detalles)
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
