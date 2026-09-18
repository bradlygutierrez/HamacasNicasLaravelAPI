<?php
namespace App\Http\Resources\V1;
use Illuminate\Http\Resources\Json\JsonResource;
class ProformaDetalleServicioResource extends JsonResource { public function toArray($request): array { $internal = in_array($request->user()?->rol, ['admin', 'socio'], true); $data = ['id' => $this->id, 'servicio_adicional_id' => $this->servicio_adicional_id, 'nombre' => $this->servicio_nombre_snapshot, 'metodo_calculo' => $this->metodo_calculo_snapshot, 'detalle' => $this->detalle, 'cantidad' => $this->cantidad, 'precio_unitario' => $this->precio_unitario, 'descuento' => $this->descuento, 'subtotal' => $this->subtotal]; if ($internal) $data['costo_base_unitario_snapshot'] = $this->costo_base_unitario_snapshot; return $data; } }
