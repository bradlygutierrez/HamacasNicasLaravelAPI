<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;
class ProformaServicioResource extends JsonResource { public function toArray($request): array { return ['id' => $this->id, 'servicio_adicional_id' => $this->servicio_adicional_id, 'nombre' => $this->servicio_nombre_snapshot, 'metodo_calculo' => $this->metodo_calculo_snapshot, 'detalle' => $this->detalle, 'cantidad' => $this->cantidad, 'precio_unitario' => $this->precio_unitario, 'descuento' => $this->descuento, 'subtotal' => $this->subtotal, 'costo_base_unitario' => in_array($request->user()?->rol, ['admin', 'socio'], true) ? $this->costo_base_unitario_snapshot : null]; } }
