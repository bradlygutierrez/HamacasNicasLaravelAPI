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
            'pedido_id' => $this->pedido_id,
            'pedido_numero' => $this->pedido?->numero,
            'origen' => $this->origen ?? 'venta_directa',
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
            'aplica_iva' => $this->aplica_iva,
            'total' => $this->total,
            'fecha' => $this->fecha,
            'detalles' => $this->whenLoaded(
                'detalles',
                fn () => DetalleFacturaResource::collection($this->detalles)
            ),
            'servicios' => $this->whenLoaded('servicios', fn () => $this->servicios->map(fn ($service) => ['id' => $service->id, 'nombre' => $service->servicio_nombre_snapshot, 'detalle' => $service->detalle, 'cantidad' => $service->cantidad, 'precio_unitario' => $service->precio_unitario, 'descuento' => $service->descuento, 'subtotal' => $service->subtotal])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
